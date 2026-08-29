<?php
// ================================================================
// FILE: frontend/pages/pharmacy/new_otc_sale.php
// PHARMACY - NEW OTC SALE
// ✅ Auto-format money with commas (1,000,000,000)
// ✅ Discount input auto-format with commas
// ✅ Auto-dismiss messages after 5 seconds
// ✅ INSTRUCTIONS: Large text area with suggestions
// ✅ Can pick and continue typing
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// PRE-DEFINED INSTRUCTIONS (for suggestions)
// ================================================================
$predefined_instructions = [
    '1x daily',
    '2x daily',
    '3x daily',
    '4x daily',
    'After meals',
    'Before meals',
    'With food',
    'Empty stomach',
    'Before sleep',
    'After breakfast',
    'After lunch',
    'After dinner',
    'Morning dose',
    'Evening dose',
    'As needed',
    'With water',
    'Chew well',
    'Swallow whole',
    'Dissolve in water',
    'Apply externally',
    'Injection only',
    'IV infusion',
    'Oral drops',
    'Eye drops',
    'Ear drops',
    'Nasal spray',
    'Inhale',
    'Topical cream',
    'Massage gently',
    'Wash hands before',
    'Shake well',
    'Refrigerate',
    'Store cool',
    'Avoid sunlight',
    'Not for children',
    'Pregnant caution',
    'Take with meal',
    'Take after exercise',
    'Take at bedtime',
    'Take upon waking',
    'Take before food',
    'Take after food',
    'With milk',
    'Without food',
    'At night',
    'In the morning',
    'Twice a day',
    'Thrice a day',
    'Every 4 hours',
    'Every 6 hours',
    'Every 8 hours',
    'Every 12 hours',
    'Every 24 hours'
];

// ================================================================
// GET MEDICINES INVENTORY - GROUPED BY NAME
// ================================================================
$medicines = [];
$stmt = $db->prepare("
    SELECT id, medication_name, quantity, selling_price, batch_number, expiry_date,
           DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active' AND quantity > 0
    ORDER BY medication_name, expiry_date ASC
");
$stmt->execute([$user_branch_id]);
$all_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by medication_name - for dropdown
$medicines_grouped = [];
foreach ($all_medicines as $med) {
    $name = $med['medication_name'];
    if (!isset($medicines_grouped[$name])) {
        $medicines_grouped[$name] = [];
    }
    $medicines_grouped[$name][] = $med;
}

// For dropdown - simple list
$medicines_list = [];
foreach ($medicines_grouped as $name => $batches) {
    $total_qty = array_sum(array_column($batches, 'quantity'));
    $medicines_list[] = [
        'name' => $name,
        'total_qty' => $total_qty,
        'batches' => $batches,
        'price' => $batches[0]['selling_price'] ?? 0
    ];
}

// ================================================================
// GET LOW STOCK COUNT
// ================================================================
$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

// ================================================================
// GET PENDING PRESCRIPTIONS COUNT
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// PROCESS OTC SALE
// ================================================================
$message = '';
$message_type = '';
$sale_id = 0;
$sale_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $customer_name = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $discount_amount = (float)str_replace(',', '', $_POST['discount_amount'] ?? 0);
    $payment_option = $_POST['payment_option'] ?? 'cashier';
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    
    $subtotal = 0;
    foreach ($items as &$item) {
        $item['total'] = $item['quantity'] * $item['price'];
        $subtotal += $item['total'];
    }
    
    if ($discount_amount > $subtotal) {
        $discount_amount = $subtotal;
    }
    $grand_total = $subtotal - $discount_amount;
    if ($grand_total < 0) $grand_total = 0;
    
    $errors = [];
    if (empty($items)) {
        $errors[] = 'Please add at least one medicine';
    }
    
    // Check stock for each item
    $stock_errors = [];
    foreach ($items as $item) {
        $stmt = $db->prepare("
            SELECT SUM(quantity) as total_qty 
            FROM medications_inventory 
            WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
        ");
        $stmt->execute([$item['name'], $user_branch_id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        $available = $stock['total_qty'] ?? 0;
        
        if ($available < $item['quantity']) {
            $stock_errors[] = "Insufficient stock for {$item['name']} (Available: " . $available . ")";
        }
    }
    
    if (!empty($stock_errors)) {
        $errors = array_merge($errors, $stock_errors);
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $sale_number = 'OTC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $bill_number = 'BILL-OTC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // OTC CUSTOMER - NO PATIENT TABLE
            $patient_id = null;
            
            // Determine status based on payment option
            $payment_status = ($payment_option === 'self') ? 'paid' : 'pending';
            $bill_status = ($payment_option === 'self') ? 'paid' : 'pending';
            $balance = ($payment_option === 'self') ? 0 : $grand_total;
            
            // CREATE BILL
            $stmt = $db->prepare("
                INSERT INTO bills (
                    bill_number, patient_id, visit_id,
                    branch_id, created_by,
                    subtotal, discount_amount, total_amount, paid_amount, balance,
                    status, payment_method, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $bill_number,
                $patient_id,
                null,
                $user_branch_id,
                $user_id,
                $subtotal,
                $discount_amount,
                $grand_total,
                ($payment_option === 'self') ? $grand_total : 0,
                $balance,
                $bill_status,
                $payment_method,
                'OTC Sale - ' . ($payment_option === 'self' ? 'Paid by Pharmacy' : 'Pending Cashier Payment') . ' - Customer: ' . $customer_name
            ]);
            $bill_id = $db->lastInsertId();
            
            // CREATE BILL ITEMS
            foreach ($items as $item) {
                $is_paid = ($payment_option === 'self') ? 1 : 0;
                $item_payment_status = ($payment_option === 'self') ? 'paid' : 'pending';
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id,
                        item_type, item_id, item_name,
                        quantity, unit_price, total_price,
                        status, reference_type, created_at
                    ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, 'otc_sale', NOW())
                ");
                $stmt->execute([
                    $bill_id,
                    $patient_id,
                    $user_branch_id,
                    null,
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['total'],
                    $item_payment_status
                ]);
            }
            
            // CREATE OTC SALE
            $otc_payment_status = ($payment_option === 'self') ? 'paid' : 'pending';
            $payment_notes = ($payment_option === 'self') ? 'Paid by Pharmacy (Self)' : 'OTC Sale - Bill sent to Cashier';
            
            $stmt = $db->prepare("
                INSERT INTO otc_sales (
                    sale_number, customer_name, customer_phone, 
                    patient_id, subtotal, discount_amount, total_amount, bill_id,
                    payment_method, payment_status, sold_by, branch_id, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sale_number,
                $customer_name,
                $customer_phone,
                $patient_id,
                $subtotal,
                $discount_amount,
                $grand_total,
                $bill_id,
                $payment_method,
                $otc_payment_status,
                $user_id,
                $user_branch_id,
                $payment_notes . ' - Customer: ' . $customer_name
            ]);
            $sale_id = $db->lastInsertId();
            
            // CREATE OTC SALE ITEMS WITH INSTRUCTIONS
            foreach ($items as $item) {
                $instructions = $item['instructions'] ?? '';
                
                $stmt = $db->prepare("
                    INSERT INTO otc_sale_items (
                        sale_id, patient_id, branch_id,
                        item_name, quantity, unit_price, total_price, instructions, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $sale_id,
                    $patient_id,
                    $user_branch_id,
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['total'],
                    $instructions
                ]);
            }
            
            // INVENTORY DEDUCTION WITH FIFO
            if ($payment_option === 'self') {
                foreach ($items as $item) {
                    $remaining_qty = $item['quantity'];
                    
                    $stmt = $db->prepare("
                        SELECT id, medication_name, quantity, batch_number, expiry_date
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                        ORDER BY expiry_date ASC
                    ");
                    $stmt->execute([$item['name'], $user_branch_id]);
                    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($batches as $batch) {
                        if ($remaining_qty <= 0) break;
                        
                        $deduct_qty = min($remaining_qty, $batch['quantity']);
                        
                        $stmt_update = $db->prepare("
                            UPDATE medications_inventory 
                            SET quantity = quantity - ?, updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_update->execute([$deduct_qty, $batch['id'], $user_branch_id]);
                        
                        $stmt_log = $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id, patient_id,
                                movement_type, quantity,
                                reference_type, reference_id,
                                performed_by, branch_id, notes, created_at
                            ) VALUES (?, ?, 'out', ?, 'otc', ?, ?, ?, ?, NOW())
                        ");
                        $stmt_log->execute([
                            $batch['id'],
                            $patient_id,
                            $deduct_qty,
                            $sale_id,
                            $user_id,
                            $user_branch_id,
                            'OTC Sale - Paid: ' . $sale_number . ' - Customer: ' . $customer_name
                        ]);
                        
                        $remaining_qty -= $deduct_qty;
                    }
                }
            } else {
                // Send to Cashier - Reserve stock only
                foreach ($items as $item) {
                    $stmt = $db->prepare("
                        SELECT id, quantity, batch_number
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                        ORDER BY expiry_date ASC
                    ");
                    $stmt->execute([$item['name'], $user_branch_id]);
                    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $remaining_qty = $item['quantity'];
                    foreach ($batches as $batch) {
                        if ($remaining_qty <= 0) break;
                        
                        $reserve_qty = min($remaining_qty, $batch['quantity']);
                        
                        $stmt_log = $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id, patient_id,
                                movement_type, quantity,
                                reference_type, reference_id,
                                performed_by, branch_id, notes, created_at
                            ) VALUES (?, ?, 'reserved', ?, 'otc_pending', ?, ?, ?, ?, NOW())
                        ");
                        $stmt_log->execute([
                            $batch['id'],
                            $patient_id,
                            $reserve_qty,
                            $sale_id,
                            $user_id,
                            $user_branch_id,
                            'OTC Sale - Pending Payment: ' . $sale_number . ' - Customer: ' . $customer_name
                        ]);
                        
                        $remaining_qty -= $reserve_qty;
                    }
                }
            }
            
            // IF SELF PAYMENT, CREATE PAYMENT RECORD
            if ($payment_option === 'self' && $grand_total > 0) {
                $receipt_number = 'RCP-OTC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO payments (
                        receipt_number, bill_id, patient_id, amount, 
                        payment_method, received_by, branch_id, received_at, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $receipt_number,
                    $bill_id,
                    $patient_id,
                    $grand_total,
                    $payment_method,
                    $user_id,
                    $user_branch_id,
                    'OTC Sale - Paid by Pharmacy (Self) - Customer: ' . $customer_name
                ]);
                
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET status = 'paid', updated_at = NOW()
                    WHERE bill_id = ?
                ");
                $stmt->execute([$bill_id]);
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET status = 'paid', paid_amount = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$grand_total, $bill_id]);
            }
            
            $db->commit();
            
            // MESSAGES
            if ($payment_option === 'self') {
                $message = "✅ OTC Sale completed successfully! Bill Paid.";
                $message_type = 'success';
            } else {
                $message = "✅ OTC Sale completed successfully! Bill sent to Cashier.";
                $message_type = 'success';
            }
            
            $_SESSION['otc_sale_message'] = $message;
            $_SESSION['otc_sale_message_type'] = $message_type;
            $_SESSION['otc_sale_message_time'] = time();
            
            header('Location: otc_history.php?success=1&auto_dismiss=1');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// CHECK FOR SESSION MESSAGES
// ================================================================
if (isset($_SESSION['otc_sale_message'])) {
    $message = $_SESSION['otc_sale_message'];
    $message_type = $_SESSION['otc_sale_message_type'] ?? 'success';
    unset($_SESSION['otc_sale_message']);
    unset($_SESSION['otc_sale_message_type']);
    unset($_SESSION['otc_sale_message_time']);
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New OTC Sale - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A3D8A;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            --purple-light: #EDE9FE;
            --gold: #F59E0B;
            --gold-light: #FEF3C7;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
            --primary-light: #1E3A5F;
            --success-light: #1A3A2A;
            --warning-light: #3D2E0A;
            --danger-light: #3A1A1A;
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
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(124, 58, 237, 0.25);
            position: relative;
            overflow: hidden;
            color: white;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .stat-chip {
            background: rgba(255,255,255,0.12);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
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
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-2-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card-2 {
            border-radius: 14px;
            padding: 18px 22px;
            border: none;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 100px;
            cursor: default;
        }
        
        .stat-card-2::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        
        .stat-card-2:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 32px rgba(0,0,0,0.2);
        }
        
        .stat-card-2 .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.18);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .stat-card-2:hover .stat-icon {
            transform: scale(1.05) rotate(-2deg);
            background: rgba(255,255,255,0.3);
        }
        
        .stat-card-2 .stat-content {
            position: relative;
            z-index: 1;
            flex: 1;
        }
        
        .stat-card-2 .stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0;
        }
        
        .stat-card-2 .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        
        .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* ================================================================
           SALE FORM CARD
           ================================================================ */
        .sale-form-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 28px 32px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .sale-form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-bottom: 10px;
            margin-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .section-title .badge-count {
            background: var(--primary);
            color: white;
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            margin-left: auto;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.88rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-row {
            margin-bottom: 16px;
        }
        
        .medicine-select-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .medicine-select-row .form-group {
            flex: 1;
            min-width: 160px;
        }
        
        .medicine-select-row .form-group.qty-group {
            max-width: 120px;
        }
        
        .medicine-select-row .form-group.price-group {
            max-width: 160px;
        }
        
        .btn-add-medicine {
            background: var(--primary);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.88rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 44px;
            white-space: nowrap;
        }
        
        .btn-add-medicine:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 94, 215, 0.3);
        }
        
        /* ================================================================
           CART
           ================================================================ */
        .cart-container {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            min-height: 80px;
        }
        
        .cart-item {
            display: flex;
            flex-direction: column;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            gap: 8px;
        }
        
        .cart-item:hover {
            background: var(--primary-light);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .cart-item .item-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
        }
        
        .cart-item .item-info .item-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .cart-item .item-info .item-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 10px;
            border-radius: 6px;
        }
        
        .cart-item .item-info .item-price {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.85rem;
        }
        
        .cart-item .item-total {
            font-weight: 700;
            color: var(--success);
            font-size: 0.95rem;
            min-width: 80px;
            text-align: right;
        }
        
        .cart-item .btn-remove {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 4px 12px;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .cart-item .btn-remove:hover {
            background: #B91C1C;
            transform: scale(1.05);
        }
        
        /* ================================================================
           INSTRUCTIONS - LARGE TEXT AREA WITH SUGGESTIONS
           ================================================================ */
        .instructions-section {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--border-color);
            width: 100%;
        }
        
        .instructions-section .instr-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        
        .instructions-section .instr-label i {
            color: var(--primary);
        }
        
        .instructions-section .instr-label .instr-count {
            font-size: 0.6rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0 8px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .instructions-section .instr-textarea-wrapper {
            position: relative;
        }
        
        .instructions-section .instr-textarea-wrapper textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 55px;
            max-height: 120px;
            line-height: 1.5;
        }
        
        .instructions-section .instr-textarea-wrapper textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .instructions-section .instr-textarea-wrapper textarea::placeholder {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .instructions-section .instr-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }
        
        .instructions-section .instr-suggestions .suggestion-btn {
            padding: 3px 12px;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-size: 0.65rem;
            background: var(--bg-body);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .instructions-section .instr-suggestions .suggestion-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        
        .instructions-section .instr-suggestions .suggestion-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .instructions-section .instr-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }
        
        .instructions-section .instr-tags .instr-tag {
            background: var(--primary-light);
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--primary);
        }
        
        .instructions-section .instr-tags .instr-tag .remove-instr {
            cursor: pointer;
            font-size: 0.7rem;
            color: var(--danger);
            font-weight: 700;
            padding: 0 2px;
        }
        
        .instructions-section .instr-tags .instr-tag .remove-instr:hover {
            color: #B91C1C;
            transform: scale(1.2);
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-cart i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-cart p {
            font-size: 0.95rem;
        }
        
        .empty-cart .sub-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* ================================================================
           DISCOUNT SECTION - WITH MONEY FORMAT
           ================================================================ */
        .discount-section {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            margin-top: 16px;
            transition: all 0.3s ease;
        }
        
        .discount-section:hover {
            border-color: var(--gold);
        }
        
        .discount-section .discount-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
        }
        
        .discount-section .discount-label {
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
        }
        
        .discount-section .discount-label i {
            color: var(--gold);
            font-size: 1.1rem;
        }
        
        .discount-section .discount-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            flex-wrap: wrap;
        }
        
        .discount-section .discount-input-group .discount-input {
            width: 250px;
            max-width: 350px;
            padding: 10px 16px;
            font-size: 1.2rem;
            font-weight: 700;
            text-align: right;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        .discount-section .discount-input-group .discount-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        }
        
        .discount-section .discount-input-group .discount-input::placeholder {
            font-weight: 400;
            font-size: 0.9rem;
            color: var(--text-muted);
            letter-spacing: 0;
        }
        
        .btn-apply-discount {
            background: var(--gold);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-apply-discount:hover {
            background: #D97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.35);
        }
        
        .btn-remove-discount {
            background: var(--danger);
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .btn-remove-discount:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }
        
        .discount-display {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 2px dashed var(--border-color);
        }
        
        .discount-display .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            background: var(--bg-card);
            padding: 6px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .discount-display .info-item .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .discount-display .info-item .value {
            font-weight: 700;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 1rem;
        }
        
        .discount-display .info-item .value.grand-total {
            color: var(--success);
            font-size: 1.15rem;
            font-weight: 800;
        }
        
        /* ================================================================
           PAYMENT OPTIONS
           ================================================================ */
        .payment-options {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .payment-option-card {
            flex: 1;
            min-width: 200px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .payment-option-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .payment-option-card.active {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .payment-option-card .option-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .payment-option-card .option-icon.cashier {
            background: var(--purple-light);
            color: var(--purple);
        }
        
        .payment-option-card .option-icon.self {
            background: var(--success-light);
            color: var(--success);
        }
        
        .payment-option-card .option-content h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .payment-option-card .option-content p {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin: 2px 0 0 0;
        }
        
        .payment-option-card .option-radio {
            margin-left: auto;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .payment-option-card.active .option-radio {
            border-color: var(--primary);
            background: var(--primary);
        }
        
        .payment-option-card.active .option-radio::after {
            content: '✓';
            color: white;
            font-size: 12px;
            font-weight: 700;
        }
        
        .payment-methods {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .payment-methods .method-btn {
            padding: 8px 18px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .payment-methods .method-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .payment-methods .method-btn.active {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        /* ================================================================
           ACTION BUTTONS
           ================================================================ */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
        }
        
        .btn-complete-sale {
            padding: 12px 36px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .btn-complete-sale:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .btn-complete-sale:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-complete-sale.cashier-mode {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
        }
        
        .btn-complete-sale.self-mode {
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .btn-clear-cart {
            background: var(--danger);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-clear-cart:hover {
            background: #B91C1C;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           MESSAGE BOX - WITH AUTO-DISMISS
           ================================================================ */
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
            position: relative;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .message-box.hide {
            opacity: 0;
            transform: translateY(-20px);
            display: none;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-box.success {
            background: var(--success-light);
            color: #065F46;
            border: 2px solid #6EE7B7;
        }
        
        .message-box.error {
            background: var(--danger-light);
            color: #991B1B;
            border: 2px solid #FCA5A5;
        }
        
        .message-box i {
            font-size: 1.3rem;
        }
        
        .message-box .message-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            padding: 0 4px;
        }
        
        .message-box .message-close:hover {
            opacity: 1;
        }
        
        .message-box .message-timer {
            font-size: 0.6rem;
            opacity: 0.6;
            margin-left: 8px;
        }
        
        [data-theme="dark"] .message-box.success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        
        [data-theme="dark"] .message-box.error {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .sale-form-card { padding: 16px 18px; }
            .medicine-select-row { flex-direction: column; }
            .medicine-select-row .form-group { max-width: 100% !important; }
            .stats-2-cards { grid-template-columns: 1fr; }
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.3rem; }
            .action-buttons { flex-direction: column; align-items: stretch; }
            .action-buttons .btn-complete-sale,
            .action-buttons .btn-clear-cart,
            .action-buttons .btn-outline { width: 100%; justify-content: center; }
            .cart-item .item-row { flex-direction: column; align-items: flex-start; }
            .cart-item .item-total { text-align: left; width: 100%; }
            .discount-section .discount-row { flex-direction: column; align-items: stretch; }
            .discount-section .discount-input-group { flex-wrap: wrap; }
            .discount-section .discount-input-group .discount-input { width: 100%; max-width: 100%; }
            .discount-display { flex-direction: column; align-items: stretch; gap: 8px; }
            .discount-display .info-item { justify-content: space-between; }
            .payment-options { flex-direction: column; }
            .payment-option-card { min-width: unset; }
            .payment-methods { justify-content: center; }
            .instructions-section .instr-suggestions { gap: 3px; }
            .instructions-section .instr-suggestions .suggestion-btn { font-size: 0.55rem; padding: 2px 8px; }
        }
        
        @media (max-width: 480px) {
            .payment-methods .method-btn { font-size: 0.7rem; padding: 4px 10px; }
            .stats-2-cards { grid-template-columns: 1fr; }
            .page-header .page-title { font-size: 1.1rem; }
            .page-header .page-subtitle { font-size: 0.8rem; }
            .page-header .stat-chip { font-size: 0.6rem; padding: 2px 10px; }
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
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
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
    </style>
</head>
<body>

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
                <i class="fas fa-plus-circle"></i>
                New OTC Sale
            </h1>
            <p class="page-subtitle">
                Sell medicines over-the-counter
                <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="stat-chip">
                    <i class="fas fa-pills"></i> <?= count($medicines_list) ?> medicines
                </span>
                <span class="stat-chip">
                    <i class="fas fa-cash-register"></i> 2 Payment Options
                </span>
                <span class="stat-chip" style="background:rgba(255,255,255,0.2);">
                    <i class="fas fa-user-slash"></i> No Patient Registration
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="otc_history.php" class="btn-outline-light">
                <i class="fas fa-history"></i> History
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE WITH AUTO-DISMISS -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
            <span class="message-timer">(auto-dismiss in 5s)</span>
            <span class="message-close" onclick="dismissMessage()">&times;</span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 2 STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-2-cards">
        <div class="stat-card-2 card-blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Medicines in Stock</p>
                <p class="stat-number"><?= count($medicines_list) ?></p>
            </div>
        </div>
        <div class="stat-card-2 card-orange">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Low Stock Alerts</p>
                <p class="stat-number"><?= $low_stock_count ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALE FORM -->
    <!-- ================================================================ -->
    <div class="sale-form-card">
        <form method="POST" action="" id="otcSaleForm">
            <input type="hidden" name="action" value="complete_sale">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            <input type="hidden" name="discount_amount" id="discountAmountHidden" value="0">
            <input type="hidden" name="payment_option" id="paymentOptionHidden" value="cashier">
            
            <!-- Customer Information -->
            <div class="section-title">
                <i class="fas fa-user"></i>
                Customer Information
                <span class="badge-count" style="background:var(--warning);">OTC Only</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-row">
                    <label class="form-label">Customer Name <span class="required">*</span></label>
                    <input type="text" name="customer_name" class="form-control" 
                           placeholder="Walk-in Customer" value="Walk-in Customer" required>
                </div>
                <div class="form-row">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="customer_phone" class="form-control" 
                           placeholder="e.g. 0759 154 160">
                </div>
            </div>
            
            <!-- Add Medicine Section -->
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="section-title">
                    <i class="fas fa-pills"></i>
                    Add Medicine
                    <span class="badge-count"><?= count($medicines_list) ?> available</span>
                </div>
                
                <div class="medicine-select-row">
                    <div class="form-group">
                        <label class="form-label">Select Medicine <span class="required">*</span></label>
                        <select id="medicineSelect" class="form-control">
                            <option value="">-- Select Medicine --</option>
                            <?php foreach ($medicines_list as $med): ?>
                                <option value="<?= htmlspecialchars($med['name']) ?>" 
                                        data-name="<?= htmlspecialchars($med['name']) ?>"
                                        data-total-stock="<?= $med['total_qty'] ?>"
                                        data-price="<?= $med['price'] ?>">
                                    <?= htmlspecialchars($med['name']) ?> 
                                    (Stock: <?= $med['total_qty'] ?>) 
                                    - TSh <?= number_format($med['price'] ?? 0) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group qty-group">
                        <label class="form-label">Qty <span class="required">*</span></label>
                        <input type="number" id="medicineQty" class="form-control" value="1" min="1">
                    </div>
                    
                    <div class="form-group price-group">
                        <label class="form-label">Price (TSh)</label>
                        <input type="number" id="medicinePrice" class="form-control" value="0" step="100" readonly>
                    </div>
                    
                    <button type="button" onclick="addToCart()" class="btn-add-medicine">
                        <i class="fas fa-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
            
            <!-- Cart Items -->
            <div class="mt-4">
                <div class="section-title">
                    <i class="fas fa-shopping-cart"></i>
                    Cart
                    <span class="badge-count" id="cartCount">0 items</span>
                </div>
                
                <div class="cart-container" id="cartContainer">
                    <div class="empty-cart" id="emptyCart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No items added yet</p>
                        <p class="sub-text">Select a medicine and click "Add to Cart"</p>
                    </div>
                    <div id="cartItems" style="display:none;"></div>
                </div>
            </div>
            
            <!-- Discount Section -->
            <div class="discount-section">
                <div class="discount-row">
                    <span class="discount-label">
                        <i class="fas fa-tags"></i> Discount (TSh)
                    </span>
                    <div class="discount-input-group">
                        <span class="currency-prefix" style="font-weight:700;color:var(--text-secondary);font-size:1rem;font-family:'Courier New',monospace;">TSh</span>
                        <input type="text" id="discountAmountInput" class="form-control discount-input" 
                               placeholder="0" value="0"
                               oninput="formatMoneyInput(this)" 
                               onfocus="this.select()"
                               autocomplete="off">
                        <button type="button" class="btn-apply-discount" onclick="applyDiscount()">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button type="button" class="btn-remove-discount" onclick="removeDiscount()">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                </div>
                
                <div class="discount-display" id="discountDisplay">
                    <div class="info-item">
                        <span class="label">Subtotal:</span>
                        <span class="value subtotal-value" id="displaySubtotal">TSh 0</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Discount:</span>
                        <span class="value discount-value" id="displayDiscount">TSh 0</span>
                    </div>
                    <div class="info-item" style="border-color: var(--success); background: var(--success-light);">
                        <span class="label" style="font-weight:700;">Grand Total:</span>
                        <span class="value grand-total" id="displayGrandTotal">TSh 0</span>
                    </div>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- PAYMENT OPTIONS -->
            <!-- ================================================================ -->
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="section-title">
                    <i class="fas fa-credit-card"></i>
                    Payment Option
                    <span class="badge-count">Choose</span>
                </div>
                
                <div class="payment-options">
                    <!-- Option 1: Send to Cashier -->
                    <div class="payment-option-card active" data-option="cashier" onclick="selectPaymentOption('cashier')">
                        <div class="option-icon cashier">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div class="option-content">
                            <h4>Send to Cashier</h4>
                            <p>Bill sent to Cashier for payment</p>
                            <p style="font-size:0.6rem;color:var(--warning);margin-top:2px;">
                                <i class="fas fa-info-circle"></i> Inventory held until payment
                            </p>
                        </div>
                        <div class="option-radio"></div>
                    </div>
                    
                    <!-- Option 2: Pay Now (Self) -->
                    <div class="payment-option-card" data-option="self" onclick="selectPaymentOption('self')">
                        <div class="option-icon self">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="option-content">
                            <h4>Pay Now (Self)</h4>
                            <p>Pharmacy collects payment immediately</p>
                            <p style="font-size:0.6rem;color:var(--success);margin-top:2px;">
                                <i class="fas fa-check-circle"></i> Inventory deducted instantly
                            </p>
                        </div>
                        <div class="option-radio"></div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Method -->
            <div class="mt-3" id="paymentMethodSection">
                <div class="section-title" style="border-bottom: none; padding-bottom: 4px; margin-bottom: 8px;">
                    <i class="fas fa-money-bill-wave"></i>
                    Payment Method
                    <span class="badge-count" style="background:var(--success);">Optional</span>
                </div>
                
                <div class="payment-methods">
                    <button type="button" class="method-btn active" data-method="cash" onclick="selectPaymentMethod('cash')">
                        <i class="fas fa-money-bill-wave"></i> Cash
                    </button>
                    <button type="button" class="method-btn" data-method="m-pesa" onclick="selectPaymentMethod('m-pesa')">
                        <i class="fas fa-mobile-alt"></i> M-Pesa
                    </button>
                    <button type="button" class="method-btn" data-method="airtel_money" onclick="selectPaymentMethod('airtel_money')">
                        <i class="fas fa-mobile-alt"></i> Airtel Money
                    </button>
                    <button type="button" class="method-btn" data-method="tigo_pesa" onclick="selectPaymentMethod('tigo_pesa')">
                        <i class="fas fa-mobile-alt"></i> Tigo Pesa
                    </button>
                    <button type="button" class="method-btn" data-method="halopesa" onclick="selectPaymentMethod('halopesa')">
                        <i class="fas fa-mobile-alt"></i> Halopesa
                    </button>
                    <button type="button" class="method-btn" data-method="bank" onclick="selectPaymentMethod('bank')">
                        <i class="fas fa-university"></i> Bank
                    </button>
                    <button type="button" class="method-btn" data-method="card" onclick="selectPaymentMethod('card')">
                        <i class="fas fa-credit-card"></i> Card
                    </button>
                </div>
                <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="cash">
                <p class="text-xs text-gray-400 mt-2">
                    <i class="fas fa-info-circle"></i> Payment method is used when "Pay Now (Self)" is selected
                </p>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="submit" class="btn-complete-sale cashier-mode" id="completeSaleBtn" disabled>
                    <i class="fas fa-receipt"></i> Send to Cashier
                </button>
                <button type="button" class="btn-clear-cart" onclick="clearCart()">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
                <a href="dashboard.php" class="btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            New OTC Sale
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
    // MONEY FORMAT - Auto format with commas
    // ================================================================
    function formatMoneyInput(input) {
        var raw = input.value.replace(/,/g, '');
        raw = raw.replace(/[^0-9.]/g, '');
        
        if (raw === '' || raw === '.') {
            input.value = '0';
            return;
        }
        
        var num = parseFloat(raw);
        if (isNaN(num)) {
            input.value = '0';
            return;
        }
        
        var formatted = num.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        
        input.value = formatted;
    }
    
    function getRawNumber(value) {
        return parseFloat(value.replace(/,/g, '')) || 0;
    }

    // ================================================================
    // AUTO-DISMISS MESSAGES AFTER 5 SECONDS
    // ================================================================
    function dismissMessage() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            messageBox.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            messageBox.style.opacity = '0';
            messageBox.style.transform = 'translateY(-20px)';
            setTimeout(function() {
                messageBox.style.display = 'none';
            }, 500);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            var dismissTimer = setTimeout(function() {
                dismissMessage();
            }, 5000);
            
            messageBox.addEventListener('click', function(e) {
                if (e.target.classList.contains('message-close') || e.target === this) {
                    clearTimeout(dismissTimer);
                    dismissMessage();
                }
            });
            
            messageBox.addEventListener('mouseenter', function() {
                clearTimeout(dismissTimer);
            });
            
            messageBox.addEventListener('mouseleave', function() {
                dismissTimer = setTimeout(function() {
                    dismissMessage();
                }, 3000);
            });
        }
    });

    // ================================================================
    // CART DATA
    // ================================================================
    var cart = [];
    var itemIdCounter = 0;
    var currentDiscountAmount = 0;
    var subtotal = 0;
    var grandTotal = 0;
    var selectedPaymentOption = 'cashier';

    // ================================================================
    // MEDICINE SELECT - UPDATE PRICE
    // ================================================================
    document.getElementById('medicineSelect')?.addEventListener('change', function() {
        var option = this.options[this.selectedIndex];
        if (option.value) {
            var price = parseFloat(option.dataset.price) || 0;
            document.getElementById('medicinePrice').value = price;
        }
    });

    // ================================================================
    // SELECT PAYMENT OPTION
    // ================================================================
    function selectPaymentOption(option) {
        selectedPaymentOption = option;
        document.getElementById('paymentOptionHidden').value = option;
        
        document.querySelectorAll('.payment-option-card').forEach(function(card) {
            card.classList.remove('active');
        });
        document.querySelector('[data-option="' + option + '"]').classList.add('active');
        
        var btn = document.getElementById('completeSaleBtn');
        if (option === 'self') {
            btn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Pay Now & Complete Sale';
            btn.className = 'btn-complete-sale self-mode';
        } else {
            btn.innerHTML = '<i class="fas fa-receipt"></i> Send to Cashier';
            btn.className = 'btn-complete-sale cashier-mode';
        }
    }

    // ================================================================
    // SELECT PAYMENT METHOD
    // ================================================================
    function selectPaymentMethod(method) {
        document.querySelectorAll('.method-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        var btn = document.querySelector('[data-method="' + method + '"]');
        if (btn) btn.classList.add('active');
        document.getElementById('selectedPaymentMethod').value = method;
    }

    // ================================================================
    // INSTRUCTION FUNCTIONS - TEXTAREA WITH SUGGESTIONS
    // ================================================================
    function getInstructionText(id) {
        var textarea = document.getElementById('instr_textarea_' + id);
        return textarea ? textarea.value : '';
    }
    
    function setInstructionText(id, value) {
        var textarea = document.getElementById('instr_textarea_' + id);
        if (textarea) {
            textarea.value = value;
            // Update the item in cart
            var item = cart.find(function(i) { return i.id === id; });
            if (item) {
                item.instructions = value;
            }
        }
    }
    
    function addSuggestionToInstruction(id, suggestion) {
        var textarea = document.getElementById('instr_textarea_' + id);
        if (!textarea) return;
        
        var currentValue = textarea.value;
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        
        // Check if suggestion already exists in the text
        if (currentValue.toLowerCase().includes(suggestion.toLowerCase())) {
            showToast('Info', 'Instruction already added: ' + suggestion, 'info');
            return;
        }
        
        // Add with comma separator
        if (currentValue.length > 0 && !currentValue.endsWith(' ')) {
            textarea.value = currentValue + ', ' + suggestion;
        } else if (currentValue.length > 0) {
            textarea.value = currentValue + suggestion;
        } else {
            textarea.value = suggestion;
        }
        
        // Update the item
        item.instructions = textarea.value;
        
        // Update the instruction display
        updateInstructionDisplay(id);
        
        // Auto-resize the textarea
        autoResizeTextarea(textarea);
        
        showToast('Success', 'Added instruction: ' + suggestion, 'success');
    }
    
    function removeInstructionPart(id, partToRemove) {
        var textarea = document.getElementById('instr_textarea_' + id);
        if (!textarea) return;
        
        var currentValue = textarea.value;
        var parts = currentValue.split(',').map(function(s) { return s.trim(); });
        
        // Filter out the part to remove
        var newParts = parts.filter(function(p) { 
            return p.toLowerCase() !== partToRemove.toLowerCase().trim();
        });
        
        textarea.value = newParts.join(', ');
        
        // Update the item
        var item = cart.find(function(i) { return i.id === id; });
        if (item) {
            item.instructions = textarea.value;
        }
        
        // Update the instruction display
        updateInstructionDisplay(id);
        
        // Auto-resize
        autoResizeTextarea(textarea);
        
        showToast('Info', 'Removed instruction: ' + partToRemove, 'info');
    }
    
    function updateInstructionDisplay(id) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        
        var displayDiv = document.getElementById('instr_display_' + id);
        if (!displayDiv) return;
        
        var text = item.instructions || '';
        var parts = text.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
        
        if (parts.length === 0) {
            displayDiv.innerHTML = '<span style="font-size:0.7rem;color:var(--text-muted);">No instructions added</span>';
            return;
        }
        
        var html = '';
        parts.forEach(function(part) {
            var escapedPart = part.replace(/'/g, "\\'");
            html += '<span class="instr-tag">' + part + ' <span class="remove-instr" onclick="removeInstructionPart(' + id + ', \'' + escapedPart + '\')">&times;</span></span>';
        });
        displayDiv.innerHTML = html;
    }
    
    function autoResizeTextarea(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // ================================================================
    // ADD TO CART
    // ================================================================
    function addToCart() {
        var select = document.getElementById('medicineSelect');
        var qtyInput = document.getElementById('medicineQty');
        var priceInput = document.getElementById('medicinePrice');
        
        var option = select.options[select.selectedIndex];
        if (!option.value) {
            showToast('Error', 'Please select a medicine', 'error');
            return;
        }
        
        var qty = parseInt(qtyInput.value) || 1;
        var price = parseFloat(priceInput.value) || 0;
        var totalStock = parseInt(option.dataset.totalStock) || 0;
        var name = option.dataset.name;
        
        if (qty <= 0) {
            showToast('Error', 'Quantity must be greater than 0', 'error');
            return;
        }
        
        if (qty > totalStock) {
            showToast('Error', 'Not enough stock! Available: ' + totalStock, 'error');
            return;
        }
        
        if (price <= 0) {
            showToast('Error', 'Price must be greater than 0', 'error');
            return;
        }
        
        var existing = cart.find(function(item) { return item.name === name; });
        if (existing) {
            var newQty = existing.quantity + qty;
            if (newQty > totalStock) {
                showToast('Error', 'Not enough stock! Available: ' + totalStock + ', Already in cart: ' + existing.quantity, 'error');
                return;
            }
            existing.quantity = newQty;
            existing.total = existing.quantity * existing.price;
        } else {
            cart.push({
                id: ++itemIdCounter,
                name: name,
                price: price,
                quantity: qty,
                total: price * qty,
                instructions: ''
            });
        }
        
        renderCart();
        updateTotals();
        
        showToast('Success', name + ' added to cart', 'success');
    }

    // ================================================================
    // REMOVE FROM CART
    // ================================================================
    function removeFromCart(id) {
        cart = cart.filter(function(item) { return item.id !== id; });
        renderCart();
        updateTotals();
    }

    // ================================================================
    // CLEAR CART
    // ================================================================
    function clearCart() {
        if (cart.length === 0) return;
        if (!confirm('Clear all items from cart?')) return;
        cart = [];
        currentDiscountAmount = 0;
        document.getElementById('discountAmountInput').value = '0';
        document.getElementById('discountAmountHidden').value = 0;
        renderCart();
        updateTotals();
        showToast('Info', 'Cart cleared', 'info');
    }

    // ================================================================
    // RENDER CART - WITH LARGE TEXTAREA + SUGGESTIONS
    // ================================================================
    function renderCart() {
        var itemsDiv = document.getElementById('cartItems');
        var emptyDiv = document.getElementById('emptyCart');
        var countEl = document.getElementById('cartCount');
        var btn = document.getElementById('completeSaleBtn');
        
        countEl.textContent = cart.length + ' items';
        
        if (cart.length === 0) {
            emptyDiv.style.display = 'block';
            itemsDiv.style.display = 'none';
            btn.disabled = true;
            return;
        }
        
        emptyDiv.style.display = 'none';
        itemsDiv.style.display = 'block';
        
        var html = '';
        var suggestions = <?= json_encode($predefined_instructions) ?>;
        
        cart.forEach(function(item) {
            var instrText = item.instructions || '';
            
            // Build suggestion buttons (limit to 15 for cleanliness)
            var suggestionHtml = '';
            var displaySuggestions = suggestions.slice(0, 15);
            displaySuggestions.forEach(function(sug) {
                var escapedSug = sug.replace(/'/g, "\\'");
                suggestionHtml += '<button type="button" class="suggestion-btn" onclick="addSuggestionToInstruction(' + item.id + ', \'' + escapedSug + '\')">' + sug + '</button>';
            });
            
            html += `
                <div class="cart-item">
                    <div class="item-row">
                        <div class="item-info">
                            <span class="item-name">${item.name}</span>
                            <span class="item-meta">Qty: ${item.quantity}</span>
                            <span class="item-price">TSh ${item.price.toLocaleString()}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="item-total">TSh ${item.total.toLocaleString()}</span>
                            <button class="btn-remove" onclick="removeFromCart(${item.id})">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    </div>
                    
                    <!-- INSTRUCTIONS SECTION - LARGE TEXTAREA WITH SUGGESTIONS -->
                    <div class="instructions-section">
                        <div class="instr-label">
                            <i class="fas fa-sticky-note"></i> Instructions
                            <span class="instr-count" id="instr_count_${item.id}">0</span>
                            <span style="font-size:0.6rem;color:var(--text-muted);margin-left:4px;">(Click suggestions or type manually, separated by commas)</span>
                        </div>
                        
                        <div class="instr-textarea-wrapper">
                            <textarea 
                                id="instr_textarea_${item.id}"
                                class="form-control"
                                placeholder="e.g. 2x daily, After meals, With water..."
                                oninput="updateInstructionsFromTextarea(${item.id}, this.value)"
                                onfocus="this.select()"
                                style="min-height:55px;max-height:120px;resize:vertical;font-size:0.85rem;padding:8px 12px;"
                            >${instrText}</textarea>
                        </div>
                        
                        <div class="instr-suggestions">
                            ${suggestionHtml}
                            <button type="button" class="suggestion-btn" style="background:var(--success);color:white;border-color:var(--success);" onclick="addSuggestionToInstruction(${item.id}, 'Custom')">+ Custom</button>
                        </div>
                        
                        <div class="instr-tags" id="instr_display_${item.id}">
                            ${instrText ? instrText.split(',').map(function(p) { 
                                var part = p.trim();
                                if (!part) return '';
                                var escapedPart = part.replace(/'/g, "\\'");
                                return '<span class="instr-tag">' + part + ' <span class="remove-instr" onclick="removeInstructionPart(' + item.id + ', \'' + escapedPart + '\')">&times;</span></span>';
                            }).join('') : '<span style="font-size:0.7rem;color:var(--text-muted);">No instructions added</span>'}
                        </div>
                    </div>
                </div>
            `;
        });
        itemsDiv.innerHTML = html;
        btn.disabled = false;
    }

    // ================================================================
    // UPDATE INSTRUCTIONS FROM TEXTAREA
    // ================================================================
    function updateInstructionsFromTextarea(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        
        // Limit total characters to prevent abuse
        if (value.length > 500) {
            value = value.substring(0, 500);
            var textarea = document.getElementById('instr_textarea_' + id);
            if (textarea) textarea.value = value;
        }
        
        item.instructions = value;
        
        // Update count
        var parts = value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
        var countEl = document.getElementById('instr_count_' + id);
        if (countEl) {
            countEl.textContent = parts.length;
        }
        
        // Update display
        updateInstructionDisplay(id);
    }

    // ================================================================
    // UPDATE TOTALS - WITH MONEY FORMAT
    // ================================================================
    function updateTotals() {
        subtotal = 0;
        cart.forEach(function(item) {
            subtotal += item.total;
        });
        
        var discountInput = document.getElementById('discountAmountInput');
        var discountAmount = getRawNumber(discountInput.value);
        
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
            discountInput.value = discountAmount.toLocaleString();
        }
        
        currentDiscountAmount = discountAmount;
        grandTotal = subtotal - discountAmount;
        if (grandTotal < 0) grandTotal = 0;
        
        document.getElementById('displaySubtotal').textContent = 'TSh ' + subtotal.toLocaleString();
        document.getElementById('displayDiscount').textContent = 'TSh ' + discountAmount.toLocaleString();
        document.getElementById('displayGrandTotal').textContent = 'TSh ' + grandTotal.toLocaleString();
        
        var itemsForJson = cart.map(function(item) {
            return {
                name: item.name,
                price: item.price,
                quantity: item.quantity,
                total: item.total,
                instructions: item.instructions || ''
            };
        });
        document.getElementById('itemsJson').value = JSON.stringify(itemsForJson);
        document.getElementById('discountAmountHidden').value = discountAmount;
    }

    // ================================================================
    // DISCOUNT FUNCTIONS
    // ================================================================
    function applyDiscount() {
        var input = document.getElementById('discountAmountInput');
        var discount = getRawNumber(input.value);
        
        if (discount < 0) {
            showToast('Error', 'Discount cannot be negative', 'error');
            return;
        }
        if (cart.length === 0) {
            showToast('Error', 'Cart is empty! Add items first.', 'error');
            return;
        }
        if (discount > subtotal) {
            showToast('Warning', 'Discount cannot exceed subtotal. Adjusted to ' + subtotal.toLocaleString(), 'warning');
            discount = subtotal;
            input.value = discount.toLocaleString();
        }
        currentDiscountAmount = discount;
        document.getElementById('discountAmountHidden').value = discount;
        updateTotals();
        showToast('Success', 'Discount TSh ' + discount.toLocaleString() + ' applied!', 'success');
    }

    function removeDiscount() {
        currentDiscountAmount = 0;
        document.getElementById('discountAmountInput').value = '0';
        document.getElementById('discountAmountHidden').value = 0;
        updateTotals();
        showToast('Info', 'Discount removed', 'info');
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        }
    });

    // ================================================================
    // TOAST
    // ================================================================
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

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && document.activeElement?.id === 'discountAmountInput') {
            e.preventDefault();
            applyDiscount();
        }
    });

    console.log('%c💊 Braick - New OTC Sale (Improved Instructions)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📝 Instructions: Large text area with suggestions', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Click suggestion → adds to text area (can continue typing)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Type manually with commas to add multiple', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Remove individual instructions with × button', 'font-size:13px; color:#34D399;');
    console.log('%c💰 Auto-format money with commas: 1000 → 1,000', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Messages auto-dismiss after 5 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Hover to pause auto-dismiss', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>