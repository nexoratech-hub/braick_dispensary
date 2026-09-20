<?php
// ================================================================
// FILE: frontend/pages/pharmacy/new_otc_sale.php
// PHARMACY - NEW OTC SALE (V2 - Fixed)
// ✅ BLUE THEME
// ✅ REMOVED: Redirect to otc_history (stays on page after sale)
// ✅ REMOVED: "Send to Cashier" — Only "Pay Now (Self)" remains
// ✅ FIXED: Multiple items stock deduction
// ✅ FIXED: Quantity input starts empty, no scroll change
// ✅ FIXED: Better FIFO - handles all expiry dates correctly
// ✅ Auto-format money with commas
// ✅ Premium/Extra Bill feature
// ✅ Search bar + Checkbox + Quantity/Dosage/Frequency/Route
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// PRE-DEFINED OPTIONS
// ================================================================
$predefined_frequencies = [
    '1x daily', '2x daily', '3x daily', '4x daily',
    'Twice a day', 'Thrice a day', 'Once a week',
    'Every 4 hours', 'Every 6 hours', 'Every 8 hours', 'Every 12 hours',
    'Morning only', 'Evening only', 'Night only', 'As needed', 'SOS'
];

$predefined_routes = [
    'Oral', 'Topical', 'Injection (IM)', 'Injection (IV)', 'Injection (SC)',
    'Sublingual', 'Rectal', 'Vaginal', 'Nasal', 'Ophthalmic (Eye)',
    'Otic (Ear)', 'Inhalation', 'Transdermal', 'Subcutaneous'
];

$predefined_dosages = [
    '1 tablet', '2 tablets', '1 capsule', '2 capsules',
    '5ml', '10ml', '15ml', '20ml',
    '1 teaspoon', '2 teaspoons', '1 tablespoon',
    '1 drop', '2 drops', '1 puff', '2 puffs',
    '1 sachet', '1 vial', '1 ampoule', 'Apply thin layer', 'Apply generously'
];

$predefined_instructions = [
    '1x daily', '2x daily', '3x daily', '4x daily',
    'After meals', 'Before meals', 'With food', 'Empty stomach',
    'Before sleep', 'After breakfast', 'After lunch', 'After dinner',
    'Morning dose', 'Evening dose', 'As needed', 'With water',
    'Chew well', 'Swallow whole', 'Dissolve in water', 'Apply externally',
    'Injection only', 'IV infusion', 'Oral drops', 'Eye drops',
    'Ear drops', 'Nasal spray', 'Inhale', 'Topical cream',
    'Massage gently', 'Wash hands before', 'Shake well', 'Refrigerate',
    'Store cool', 'Avoid sunlight', 'Not for children', 'Pregnant caution',
    'Take with meal', 'Take at bedtime', 'Take upon waking',
    'Take before food', 'Take after food', 'With milk', 'Without food',
    'At night', 'In the morning', 'Twice a day', 'Thrice a day',
    'Every 4 hours', 'Every 6 hours', 'Every 8 hours', 'Every 12 hours'
];

// ================================================================
// GET ALL MEDICINES
// ================================================================
$stmt_meds = $db->prepare("
    SELECT id, medication_name, quantity, selling_price, batch_number, expiry_date,
           DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active'
    ORDER BY medication_name, expiry_date ASC
");
$stmt_meds->execute([$user_branch_id]);
$all_medicines = $stmt_meds->fetchAll(PDO::FETCH_ASSOC);

$medicines_grouped = [];
foreach ($all_medicines as $med) {
    $name = $med['medication_name'];
    if (!isset($medicines_grouped[$name])) {
        $medicines_grouped[$name] = [];
    }
    $medicines_grouped[$name][] = $med;
}

$medicines_list = [];
foreach ($medicines_grouped as $name => $batches) {
    $total_qty = array_sum(array_column($batches, 'quantity'));
    $first_batch = $batches[0];
    $medicines_list[] = [
        'id' => $first_batch['id'],
        'name' => $name,
        'total_qty' => $total_qty,
        'batches' => $batches,
        'price' => $first_batch['selling_price'] ?? 0,
        'batch_number' => $first_batch['batch_number'] ?? '',
        'expiry_date' => $first_batch['expiry_date'] ?? '',
        'days_remaining' => $first_batch['days_remaining'] ?? null,
        'is_available' => $total_qty > 0
    ];
}

usort($medicines_list, function($a, $b) {
    if ($a['is_available'] !== $b['is_available']) {
        return $b['is_available'] - $a['is_available'];
    }
    return strcasecmp($a['name'], $b['name']);
});

// ================================================================
// STATISTICS
// ================================================================
$low_stock_count = 0;
try {
    $stmt_low = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
    ");
    $stmt_low->execute([$user_branch_id]);
    $low_stock_count = $stmt_low->fetch()['count'] ?? 0;
} catch (Exception $e) { $low_stock_count = 0; }

// ================================================================
// PROCESS OTC SALE - PAY NOW (SELF) ONLY
// ✅ NO REDIRECT after sale — stays on page
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $customer_name = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $discount_amount = (float)str_replace(',', '', $_POST['discount_amount'] ?? 0);
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    
    $premium_amount = (float)str_replace(',', '', $_POST['premium_amount'] ?? 0);
    $premium_note = trim($_POST['premium_note'] ?? '');
    
    if ($premium_amount < 0) $premium_amount = 0;
    
    $subtotal = 0;
    foreach ($items as &$item) {
        $item['total'] = $item['quantity'] * $item['price'];
        $subtotal += $item['total'];
    }
    unset($item);
    
    if ($discount_amount > $subtotal) $discount_amount = $subtotal;
    
    $grand_total = $subtotal - $discount_amount + $premium_amount;
    if ($grand_total < 0) $grand_total = 0;
    
    $errors = [];
    if (empty($items)) $errors[] = 'Please add at least one medicine';
    
    // Validate quantity
    foreach ($items as $it) {
        if (empty($it['quantity']) || $it['quantity'] <= 0) {
            $errors[] = "Please enter quantity for: " . $it['name'];
        }
    }
    
    // Check stock
    $stock_errors = [];
    foreach ($items as $item) {
        $stmt_check_stock = $db->prepare("
            SELECT SUM(quantity) as total_qty 
            FROM medications_inventory 
            WHERE medication_name = ? 
              AND branch_id = ? 
              AND status = 'active' 
              AND quantity > 0
        ");
        $stmt_check_stock->execute([$item['name'], $user_branch_id]);
        $stock = $stmt_check_stock->fetch(PDO::FETCH_ASSOC);
        $available = (int)($stock['total_qty'] ?? 0);
        
        if ($available < $item['quantity']) {
            $stock_errors[] = "Insufficient stock for {$item['name']} (Available: $available, Requested: {$item['quantity']})";
        }
    }
    
    if (!empty($stock_errors)) $errors = array_merge($errors, $stock_errors);
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $sale_number = 'OTC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $patient_id = null;
            $otc_payment_status = 'paid'; // ✅ Always PAID (self-pay only)
            $payment_notes = 'Paid by Pharmacy (Self)';
            
            // ✅ OTC SALE INSERT
            $stmt_otc = $db->prepare("
                INSERT INTO otc_sales (
                    sale_number, customer_name, customer_phone, 
                    patient_id, subtotal, discount_amount, premium_amount, premium_note, total_amount, bill_id,
                    payment_method, payment_status, sold_by, branch_id, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt_otc->execute([
                $sale_number, $customer_name, $customer_phone, $patient_id,
                $subtotal, $discount_amount, $premium_amount, $premium_note, $grand_total,
                $payment_method, $otc_payment_status, $user_id, $user_branch_id,
                $payment_notes . ' - Customer: ' . $customer_name . ($premium_amount > 0 ? ' | Premium: TSh ' . number_format($premium_amount) . ' - ' . $premium_note : '')
            ]);
            $sale_id = $db->lastInsertId();
            
            // ✅ OTC SALE ITEMS INSERT
            foreach ($items as $item) {
                $stmt_otc_item = $db->prepare("
                    INSERT INTO otc_sale_items (
                        sale_id, patient_id, branch_id,
                        item_name, quantity, unit_price, total_price, 
                        dosage, frequency, route, instructions, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt_otc_item->execute([
                    $sale_id, $patient_id, $user_branch_id,
                    $item['name'], $item['quantity'], $item['price'], $item['total'],
                    $item['dosage'] ?? '', $item['frequency'] ?? '', $item['route'] ?? '', $item['instructions'] ?? ''
                ]);
            }
            
            // ================================================================
            // STOCK DEDUCTION - FIFO (Always deduct since payment is PAID)
            // ================================================================
            error_log("=== OTC STOCK DEDUCTION START ===");
            error_log("Sale #$sale_id ($sale_number) | Branch: $user_branch_id");
            error_log("Total items: " . count($items));
            
            foreach ($items as $item_index => $item) {
                $remaining_qty = (int)$item['quantity'];
                $item_name = $item['name'];
                
                error_log("--- ITEM " . ($item_index + 1) . ": $item_name | Need: $remaining_qty ---");
                
                $stmt_fetch_batches = $db->prepare("
                    SELECT id, medication_name, quantity, batch_number, expiry_date
                    FROM medications_inventory 
                    WHERE medication_name = ? 
                      AND branch_id = ? 
                      AND status = 'active' 
                      AND quantity > 0
                    ORDER BY 
                        CASE 
                            WHEN expiry_date IS NULL THEN 2
                            WHEN expiry_date = '0000-00-00' THEN 2
                            ELSE 1
                        END ASC,
                        expiry_date ASC,
                        id ASC
                ");
                $stmt_fetch_batches->execute([$item_name, $user_branch_id]);
                $batches = $stmt_fetch_batches->fetchAll(PDO::FETCH_ASSOC);
                $stmt_fetch_batches->closeCursor();
                
                error_log("    Batches found: " . count($batches));
                
                if (empty($batches)) {
                    error_log("    ⚠️ NO BATCHES FOUND for $item_name!");
                    continue;
                }
                
                $total_deducted = 0;
                $batch_index = 0;
                
                foreach ($batches as $batch) {
                    if ($remaining_qty <= 0) break;
                    
                    $batch_index++;
                    $batch_id = (int)$batch['id'];
                    $batch_qty_available = (int)$batch['quantity'];
                    $deduct_qty = min($remaining_qty, $batch_qty_available);
                    
                    error_log("    Batch #$batch_index (ID: $batch_id): deducting $deduct_qty from $batch_qty_available");
                    
                    $stmt_deduct = $db->prepare("
                        UPDATE medications_inventory 
                        SET quantity = quantity - ?, updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt_deduct->execute([$deduct_qty, $batch_id, $user_branch_id]);
                    $rows_affected = $stmt_deduct->rowCount();
                    $stmt_deduct->closeCursor();
                    
                    error_log("       UPDATE rows affected: $rows_affected");
                    
                    $stmt_verify = $db->prepare("SELECT quantity, medication_name FROM medications_inventory WHERE id = ?");
                    $stmt_verify->execute([$batch_id]);
                    $verified = $stmt_verify->fetch(PDO::FETCH_ASSOC);
                    $stmt_verify->closeCursor();
                    
                    if ($verified) {
                        error_log("       ✅ VERIFIED: {$verified['medication_name']} now has qty = {$verified['quantity']}");
                    }
                    
                    $stmt_log_move = $db->prepare("
                        INSERT INTO stock_movements (
                            inventory_id, patient_id,
                            movement_type, quantity,
                            reference_type, reference_id,
                            performed_by, branch_id, notes, created_at
                        ) VALUES (?, ?, 'out', ?, 'otc', ?, ?, ?, ?, NOW())
                    ");
                    $stmt_log_move->execute([
                        $batch_id, $patient_id,
                        $deduct_qty,
                        $sale_id,
                        $user_id, $user_branch_id,
                        'OTC Sale - PAID: ' . $sale_number . ' - Customer: ' . $customer_name
                    ]);
                    $stmt_log_move->closeCursor();
                    
                    $remaining_qty -= $deduct_qty;
                    $total_deducted += $deduct_qty;
                }
                
                error_log("    ✅ TOTAL DEDUCTED for $item_name: $total_deducted");
                
                if ($remaining_qty > 0) {
                    error_log("    ⚠️ WARNING: Could not fully deduct for $item_name. Remaining: $remaining_qty");
                }
            }
            
            error_log("=== OTC STOCK DEDUCTION END ===");
            
            $db->commit();
            
            $message = "✅ OTC Sale completed! Sale: <strong>$sale_number</strong> | Total: <strong>TSh " . number_format($grand_total) . "</strong> | Stock deducted for all items.";
            if ($premium_amount > 0) $message .= " Premium: TSh " . number_format($premium_amount) . " added.";
            $message_type = 'success';
            
            // ✅ NO REDIRECT — stays on page
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
            error_log("OTC Sale Error: " . $e->getMessage());
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

$unread_notifications = 0;
try {
    $stmt_notif = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_notif->execute([$user_id]);
    $unread_notifications = $stmt_notif->fetch()['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
            --primary: #0B5ED7; --primary-dark: #0A3D8A; --primary-light: #E8F0FE;
            --success: #059669; --success-dark: #047857; --success-light: #D1FAE5;
            --warning: #D97706; --warning-light: #FEF3C7;
            --danger: #DC2626; --danger-light: #FEE2E2;
            --purple: #7C3AED; --purple-light: #EDE9FE;
            --gold: #F59E0B; --gold-light: #FEF3C7;
            --sky: #0288D1; --sky-light: #E1F5FE;
            --bg-body: #F1F5F9; --bg-card: #FFFFFF; --border-color: #E2E8F0;
            --text-primary: #0F172A; --text-secondary: #475569; --text-muted: #94A3B8;
        }
        [data-theme="dark"] {
            --bg-body: #0F172A; --bg-card: #1E293B; --border-color: #334155;
            --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
            --primary-light: #1E3A5F; --success-light: #1A3A2A;
            --warning-light: #3D2E0A; --danger-light: #3A1A1A;
            --purple-light: #2A1A3A; --sky-light: #0A2A3A;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif; background: var(--bg-body); color: var(--text-primary); transition: background 0.3s ease; }
        .main-content { margin-left: 270px; margin-top: 68px; padding: 28px 32px; min-height: calc(100vh - 68px); }
        
        /* PAGE HEADER - BLUE THEME */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A3D8A);
            border-radius: 16px; padding: 24px 32px; margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            color: white; display: flex; flex-wrap: wrap;
            justify-content: space-between; align-items: center; gap: 16px;
            position: relative; overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: -60%; right: -10%;
            width: 400px; height: 400px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
            pointer-events: none;
        }
        .page-header .page-title { color: white; font-size: 1.6rem; font-weight: 700; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; }
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        .page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
        .page-header .stat-chip { background: rgba(255,255,255,0.12); padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 500; color: rgba(255,255,255,0.9); border: 1px solid rgba(255,255,255,0.1); display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); }
        .page-header .btn-outline-light { background: rgba(255,255,255,0.12); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 10px; font-weight: 500; font-size: 0.82rem; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); position: relative; z-index: 1; }
        .page-header .btn-outline-light:hover { background: rgba(255,255,255,0.25); transform: translateY(-2px); }
        
        /* STATS - BLUE THEME CARDS */
        .stats-2-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card-2 { border-radius: 14px; padding: 18px 22px; display: flex; align-items: center; gap: 16px; color: white; min-height: 100px; transition: all 0.4s; }
        .stat-card-2:hover { transform: translateY(-4px) scale(1.01); }
        .stat-card-2 .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; background: rgba(255,255,255,0.18); }
        .stat-card-2 .stat-label { font-size: 0.65rem; color: rgba(255,255,255,0.85); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
        .stat-card-2 .stat-number { font-size: 2.2rem; font-weight: 800; color: white; margin: 0; line-height: 1.1; }
        
        .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-blue-light { background: linear-gradient(135deg, #1E88E5, #1565C0); }
        
        .sale-form-card { background: var(--bg-card); border-radius: 16px; padding: 28px 32px; border: 2px solid var(--border-color); margin-bottom: 20px; transition: all 0.3s; }
        .sale-form-card:hover { border-color: var(--primary); box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
        .section-title { font-size: 0.85rem; font-weight: 700; color: var(--text-primary); padding-bottom: 10px; margin-bottom: 16px; border-bottom: 2px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .section-title i { color: var(--primary); font-size: 1.1rem; }
        .section-title .badge-count { background: var(--primary); color: white; font-size: 0.6rem; padding: 1px 10px; border-radius: 12px; margin-left: auto; }
        .form-label { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; display: block; }
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-control { width: 100%; padding: 10px 16px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 0.88rem; outline: none; background: var(--bg-card); color: var(--text-primary); }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1); }
        .form-row { margin-bottom: 16px; }
        
        .medicine-picker { position: relative; }
        .medicine-picker .picker-trigger { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 10px 16px; border: 2px solid var(--border-color); border-radius: 10px; background: var(--bg-card); color: var(--text-primary); font-size: 0.88rem; cursor: pointer; min-height: 44px; }
        .medicine-picker .picker-trigger:hover { border-color: var(--primary); }
        .medicine-picker .picker-trigger .picker-label { display: flex; align-items: center; gap: 8px; color: var(--text-secondary); }
        .medicine-picker .picker-trigger .picker-label.has-selection { color: var(--primary); font-weight: 600; }
        .medicine-picker .picker-trigger .picker-arrow { transition: transform 0.3s; color: var(--text-secondary); }
        .medicine-picker .picker-trigger.open .picker-arrow { transform: rotate(180deg); }
        .medicine-picker .picker-dropdown { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--bg-card); border: 2px solid var(--primary); border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.2); z-index: 1000; display: none; max-height: 480px; overflow: hidden; flex-direction: column; }
        .medicine-picker .picker-dropdown.show { display: flex; }
        .medicine-picker .picker-search { padding: 12px 14px; border-bottom: 2px solid var(--border-color); background: var(--bg-body); position: relative; }
        .medicine-picker .picker-search input { width: 100%; padding: 10px 14px 10px 38px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; background: var(--bg-card); color: var(--text-primary); outline: none; }
        .medicine-picker .picker-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        .medicine-picker .picker-search .search-icon { position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
        .medicine-picker .picker-search .clear-search { position: absolute; right: 24px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1rem; cursor: pointer; padding: 4px 8px; border-radius: 50%; display: none; }
        .medicine-picker .picker-search .clear-search.show { display: block; }
        .medicine-picker .picker-actions { display: flex; align-items: center; gap: 8px; padding: 8px 14px; border-bottom: 1px solid var(--border-color); background: var(--bg-body); flex-wrap: wrap; }
        .medicine-picker .picker-actions .action-chip { font-size: 0.65rem; padding: 3px 12px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
        .medicine-picker .picker-actions .action-chip:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .medicine-picker .picker-actions .selected-count { margin-left: auto; font-size: 0.7rem; font-weight: 700; color: var(--primary); background: var(--primary-light); padding: 3px 12px; border-radius: 12px; }
        .medicine-picker .picker-list { overflow-y: auto; flex: 1; padding: 6px; min-height: 100px; max-height: 300px; }
        .medicine-picker .picker-list::-webkit-scrollbar { width: 6px; }
        .medicine-picker .picker-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        .medicine-picker .med-option { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 8px; cursor: pointer; border: 1px solid transparent; margin-bottom: 3px; }
        .medicine-picker .med-option:hover { background: var(--primary-light); border-color: var(--primary); }
        .medicine-picker .med-option.selected { background: var(--primary-light); border-color: var(--primary); }
        .medicine-picker .med-option.out-of-stock { opacity: 0.6; cursor: not-allowed; background: var(--danger-light); }
        .medicine-picker .med-option .med-checkbox { width: 20px; height: 20px; border-radius: 6px; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--bg-card); }
        .medicine-picker .med-option.selected .med-checkbox { background: var(--primary); border-color: var(--primary); }
        .medicine-picker .med-option.selected .med-checkbox::after { content: '✓'; color: white; font-size: 12px; font-weight: 700; }
        .medicine-picker .med-option .med-info { flex: 1; min-width: 0; }
        .medicine-picker .med-option .med-name { font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .medicine-picker .med-option .med-meta { font-size: 0.68rem; color: var(--text-secondary); display: flex; align-items: center; gap: 10px; margin-top: 2px; }
        .medicine-picker .med-option .med-price { font-size: 0.85rem; font-weight: 700; color: var(--success); white-space: nowrap; font-family: 'Courier New', monospace; }
        .stock-badge-pill { display: inline-flex; align-items: center; gap: 3px; font-size: 0.58rem; padding: 1px 8px; border-radius: 10px; font-weight: 700; }
        .stock-badge-pill.ok { background: var(--success-light); color: var(--success); }
        .stock-badge-pill.low { background: var(--warning-light); color: var(--warning); }
        .stock-badge-pill.out { background: var(--danger-light); color: var(--danger); }
        .picker-footer { padding: 10px 14px; border-top: 2px solid var(--border-color); background: var(--bg-body); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .picker-footer .footer-info { font-size: 0.7rem; color: var(--text-secondary); }
        .btn-add-selected { background: var(--primary); color: white; padding: 8px 20px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-add-selected:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-add-selected:disabled { opacity: 0.4; cursor: not-allowed; }
        .no-med-results { text-align: center; padding: 24px 16px; color: var(--text-secondary); }
        .no-med-results i { font-size: 2rem; color: var(--border-color); display: block; margin-bottom: 8px; }
        
        .cart-container { border: 2px solid var(--border-color); border-radius: 12px; overflow: hidden; min-height: 80px; }
        .cart-item { padding: 18px 20px; border-bottom: 2px solid var(--border-color); background: var(--bg-card); }
        .cart-item:last-child { border-bottom: none; }
        .cart-item:hover { background: var(--primary-light); }
        .cart-item .item-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px dashed var(--border-color); }
        .cart-item .item-header .item-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1; }
        .cart-item .item-header .item-name { font-weight: 700; font-size: 1rem; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .cart-item .item-header .item-price-badge { font-size: 0.75rem; background: var(--success-light); color: var(--success); padding: 3px 12px; border-radius: 12px; font-weight: 700; font-family: 'Courier New', monospace; }
        .cart-item .item-header .item-total { font-weight: 800; color: var(--success); font-size: 1.1rem; font-family: 'Courier New', monospace; background: var(--success-light); padding: 4px 14px; border-radius: 8px; }
        .cart-item .item-header .btn-remove { background: var(--danger); color: white; border: none; border-radius: 8px; padding: 6px 14px; cursor: pointer; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .cart-item .item-header .btn-remove:hover { background: #B91C1C; transform: scale(1.05); }
        
        .cart-item .item-details-grid { display: grid; grid-template-columns: 110px 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        @media (max-width: 1024px) { .cart-item .item-details-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 640px) { .cart-item .item-details-grid { grid-template-columns: 1fr; } }
        .cart-item .detail-field { display: flex; flex-direction: column; gap: 4px; }
        .cart-item .detail-field label { font-size: 0.65rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; display: flex; align-items: center; gap: 4px; }
        .cart-item .detail-field label i { color: var(--primary); font-size: 0.7rem; }
        .cart-item .detail-field input, .cart-item .detail-field select { padding: 8px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.82rem; background: var(--bg-card); color: var(--text-primary); outline: none; width: 100%; height: 38px; }
        .cart-item .detail-field input:focus, .cart-item .detail-field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1); }
        .cart-item .detail-field input.qty-input { text-align: center; font-weight: 700; font-size: 1rem; color: var(--primary); -moz-appearance: textfield; }
        .cart-item .detail-field input.qty-input::-webkit-outer-spin-button,
        .cart-item .detail-field input.qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .cart-item .detail-field .select-with-manual { display: flex; gap: 4px; align-items: center; }
        .cart-item .detail-field .select-with-manual select, .cart-item .detail-field .select-with-manual input { flex: 1; }
        .cart-item .detail-field .btn-manual-toggle { background: var(--primary); color: white; border: none; border-radius: 8px; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; font-size: 0.7rem; }
        .cart-item .detail-field .manual-input { display: none; }
        .cart-item .detail-field .manual-input.show { display: block; }
        
        .instructions-section { margin-top: 10px; padding-top: 12px; border-top: 1px dashed var(--border-color); }
        .instructions-section .instr-label { font-size: 0.72rem; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-weight: 700; text-transform: uppercase; }
        .instructions-section .instr-label i { color: var(--primary); }
        .instructions-section .instr-textarea-wrapper textarea { width: 100%; padding: 10px 14px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 0.85rem; background: var(--bg-card); color: var(--text-primary); outline: none; resize: vertical; min-height: 55px; max-height: 120px; line-height: 1.5; }
        .instructions-section .instr-textarea-wrapper textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1); }
        .instructions-section .instr-suggestions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }
        .instructions-section .instr-suggestions .suggestion-btn { padding: 3px 12px; border: 1px solid var(--border-color); border-radius: 14px; font-size: 0.65rem; background: var(--bg-body); color: var(--text-secondary); cursor: pointer; }
        .instructions-section .instr-suggestions .suggestion-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .instructions-section .instr-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .instructions-section .instr-tags .instr-tag { background: var(--primary-light); color: var(--primary); padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--primary); }
        .instructions-section .instr-tags .instr-tag .remove-instr { cursor: pointer; font-size: 0.7rem; color: var(--danger); font-weight: 700; }
        
        .empty-cart { text-align: center; padding: 40px 20px; color: var(--text-secondary); }
        .empty-cart i { font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 12px; }
        
        .discount-section, .premium-section { background: var(--bg-body); border-radius: 12px; padding: 18px 22px; border: 2px solid var(--border-color); margin-top: 16px; }
        .discount-section:hover { border-color: var(--gold); }
        .premium-section:hover { border-color: var(--purple); }
        .discount-section .discount-row, .premium-section .premium-row { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; }
        .discount-section .discount-label, .premium-section .premium-label { font-weight: 700; color: var(--text-secondary); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; min-width: 120px; }
        .discount-section .discount-label i { color: var(--gold); font-size: 1.1rem; }
        .premium-section .premium-label i { color: var(--purple); font-size: 1.1rem; }
        .discount-section .discount-input-group, .premium-section .premium-input-group { display: flex; align-items: center; gap: 8px; flex: 1; flex-wrap: wrap; }
        .discount-section .discount-input-group .discount-input, .premium-section .premium-input-group .premium-input { width: 250px; max-width: 350px; padding: 10px 16px; font-size: 1.2rem; font-weight: 700; text-align: right; border: 2px solid var(--border-color); border-radius: 10px; background: var(--bg-card); color: var(--text-primary); outline: none; font-family: 'Courier New', monospace; }
        .discount-section .discount-input-group .discount-input:focus { border-color: var(--gold); }
        .premium-section .premium-input-group .premium-input:focus { border-color: var(--purple); }
        .btn-apply-discount { background: var(--gold); color: white; padding: 10px 24px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-remove-discount, .btn-remove-premium { background: var(--danger); color: white; padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-add-premium { background: linear-gradient(135deg, #0B5ED7, #0A3D8A); color: white; padding: 10px 24px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        
        .discount-display { display: flex; flex-wrap: wrap; align-items: center; gap: 20px; margin-top: 14px; padding-top: 14px; border-top: 2px dashed var(--border-color); }
        .discount-display .info-item { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; background: var(--bg-card); padding: 6px 16px; border-radius: 8px; border: 1px solid var(--border-color); }
        .discount-display .info-item .label { color: var(--text-secondary); font-weight: 500; }
        .discount-display .info-item .value { font-weight: 700; color: var(--text-primary); font-family: 'Courier New', monospace; font-size: 1rem; }
        .discount-display .info-item .value.grand-total { color: var(--success); font-size: 1.15rem; font-weight: 800; }
        
        .premium-display { display: none; margin-top: 10px; padding-top: 10px; border-top: 2px dashed var(--border-color); }
        .premium-display .premium-info { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        
        /* PAYMENT INFO - SINGLE OPTION (PAY NOW) */
        .payment-info-box {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            border: 2px solid var(--success);
            border-radius: 12px;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        [data-theme="dark"] .payment-info-box {
            background: linear-gradient(135deg, #1A3A2A, #0F2A1A);
        }
        .payment-info-box .pay-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        }
        .payment-info-box .pay-content h4 {
            font-size: 1rem;
            font-weight: 800;
            margin: 0;
            color: var(--success);
        }
        .payment-info-box .pay-content p {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 4px 0 0 0;
            font-weight: 500;
        }
        .payment-info-box .pay-badge {
            margin-left: auto;
            background: var(--success);
            color: white;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .payment-methods { display: flex; gap: 8px; flex-wrap: wrap; }
        .payment-methods .method-btn { padding: 8px 18px; border: 2px solid var(--border-color); border-radius: 10px; background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-weight: 500; font-size: 0.82rem; display: flex; align-items: center; gap: 6px; }
        .payment-methods .method-btn:hover { border-color: var(--primary); color: var(--primary); }
        .payment-methods .method-btn.active { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
        
        .action-buttons { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 2px solid var(--border-color); }
        .btn-complete-sale { padding: 12px 36px; border-radius: 12px; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; color: white; background: linear-gradient(135deg, #059669, #047857); }
        .btn-complete-sale:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4); }
        .btn-complete-sale:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-clear-cart { background: var(--danger); color: white; padding: 12px 24px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); padding: 10px 24px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        
        .message-box { padding: 14px 20px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; font-weight: 500; animation: slideDown 0.4s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .message-box.success { background: var(--success-light); color: #065F46; border: 2px solid #6EE7B7; }
        .message-box.error { background: var(--danger-light); color: #991B1B; border: 2px solid #FCA5A5; }
        .message-box .message-close { margin-left: auto; cursor: pointer; font-size: 1.2rem; opacity: 0.6; }
        
        .footer { padding: 14px 0; border-top: 2px solid var(--border-color); margin-top: 20px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .toast-custom { position: fixed; bottom: 24px; right: 24px; padding: 14px 20px; border-radius: 12px; z-index: 999; max-width: 400px; transform: translateY(100px); opacity: 0; transition: all 0.4s; display: flex; align-items: center; gap: 12px; color: white; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 16px; } }
        @media (max-width: 768px) {
            .stats-2-cards { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-complete-sale, .action-buttons .btn-clear-cart, .action-buttons .btn-outline { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER - BLUE THEME -->
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fas fa-plus-circle"></i> New OTC Sale</h1>
            <p class="page-subtitle">
                Sell medicines over-the-counter
                <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="stat-chip"><i class="fas fa-pills"></i> <?= count($medicines_list) ?> medicines</span>
                <span class="stat-chip" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                    <i class="fas fa-hand-holding-usd"></i> Pay Now (Self)
                </span>
                <span class="stat-chip" style="background:rgba(52,211,153,0.2);color:#A7F3D0;">
                    <i class="fas fa-boxes"></i> Stock: <span id="stockModeDisplay">Deduct Instantly</span>
                </span>
                <span class="stat-chip" style="background:rgba(255,255,255,0.15);color:white;">
                    <i class="fas fa-star"></i> Premium: Optional
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="otc_history.php" class="btn-outline-light"><i class="fas fa-history"></i> History</a>
            <a href="dashboard.php" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
            <span class="message-close" onclick="dismissMessage()">&times;</span>
        </div>
    <?php endif; ?>

    <!-- STATS - BLUE THEME -->
    <div class="stats-2-cards">
        <div class="stat-card-2 card-blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div>
                <p class="stat-label">Medicines in Stock</p>
                <p class="stat-number"><?= count(array_filter($medicines_list, function($m) { return $m['is_available']; })) ?></p>
            </div>
        </div>
        <div class="stat-card-2 card-blue-light">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <p class="stat-label">Low Stock Alerts</p>
                <p class="stat-number"><?= $low_stock_count ?></p>
            </div>
        </div>
    </div>

    <div class="sale-form-card">
        <form method="POST" action="" id="otcSaleForm">
            <input type="hidden" name="action" value="complete_sale">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            <input type="hidden" name="discount_amount" id="discountAmountHidden" value="0">
            <input type="hidden" name="premium_amount" id="premiumAmountHidden" value="0">
            <input type="hidden" name="premium_note" id="premiumNoteHidden" value="">
            
            <div class="section-title">
                <i class="fas fa-user"></i> Customer Information
                <span class="badge-count" style="background:var(--success);">Pay Now (Self)</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-row">
                    <label class="form-label">Customer Name <span class="required">*</span></label>
                    <input type="text" name="customer_name" class="form-control" value="Walk-in Customer" required>
                </div>
                <div class="form-row">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="customer_phone" class="form-control" placeholder="e.g. 0759 154 160">
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="section-title">
                    <i class="fas fa-pills"></i> Add Medicine
                    <span class="badge-count"><?= count($medicines_list) ?> available</span>
                </div>
                
                <div class="medicine-picker" id="medicinePicker">
                    <div class="picker-trigger" id="pickerTrigger" onclick="toggleMedicinePicker()">
                        <div class="picker-label" id="pickerLabel">
                            <i class="fas fa-search"></i>
                            <span id="pickerLabelText">Click to select medicine(s)...</span>
                        </div>
                        <i class="fas fa-chevron-down picker-arrow"></i>
                    </div>
                    
                    <div class="picker-dropdown" id="pickerDropdown">
                        <div class="picker-search">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="medSearchInput" placeholder="Search medicine..." autocomplete="off" oninput="filterMedicineList(this.value)">
                            <span class="clear-search" id="clearSearchBtn" onclick="clearSearch()">&times;</span>
                        </div>
                        
                        <div class="picker-actions">
                            <button type="button" class="action-chip" onclick="selectAllAvailable()"><i class="fas fa-check-double"></i> Select All Available</button>
                            <button type="button" class="action-chip" onclick="clearAllSelected()"><i class="fas fa-times"></i> Clear Selection</button>
                            <button type="button" class="action-chip" onclick="showOnlyAvailable()"><i class="fas fa-filter"></i> Available Only</button>
                            <button type="button" class="action-chip" onclick="showAllMedicines()"><i class="fas fa-list"></i> Show All</button>
                            <span class="selected-count" id="selectedCount">0 selected</span>
                        </div>
                        
                        <div class="picker-list" id="pickerList">
                            <?php foreach ($medicines_list as $med): ?>
                                <?php
                                    $is_available = $med['is_available'];
                                    $stock_class = 'out'; $stock_text = 'Out of Stock';
                                    if ($is_available) {
                                        if ($med['total_qty'] <= 10) { $stock_class = 'low'; $stock_text = 'Low: ' . $med['total_qty']; }
                                        else { $stock_class = 'ok'; $stock_text = 'In Stock: ' . $med['total_qty']; }
                                    }
                                    $search_text = strtolower($med['name'] . ' ' . ($med['batch_number'] ?? '') . ' ' . ($med['price'] ?? ''));
                                ?>
                                <div class="med-option <?= $is_available ? '' : 'out-of-stock' ?>" 
                                     data-med-id="<?= $med['id'] ?>"
                                     data-med-name="<?= htmlspecialchars($med['name']) ?>"
                                     data-med-price="<?= $med['price'] ?>"
                                     data-med-stock="<?= $med['total_qty'] ?>"
                                     data-med-available="<?= $is_available ? '1' : '0' ?>"
                                     data-search-text="<?= htmlspecialchars($search_text) ?>"
                                     onclick="toggleMedicineSelection(this)">
                                    <div class="med-checkbox"></div>
                                    <div class="med-info">
                                        <div class="med-name">
                                            <?= htmlspecialchars($med['name']) ?>
                                            <span class="stock-badge-pill <?= $stock_class ?>">
                                                <i class="fas <?= $stock_class === 'ok' ? 'fa-check-circle' : ($stock_class === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                                <?= $stock_text ?>
                                            </span>
                                        </div>
                                        <div class="med-meta">
                                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($med['batch_number'] ?: 'No batch') ?></span>
                                            <?php if ($med['expiry_date'] && $med['expiry_date'] !== '0000-00-00'): ?>
                                                <span><i class="fas fa-calendar"></i> Exp: <?= date('d/m/Y', strtotime($med['expiry_date'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="med-price">TSh <?= number_format($med['price'] ?? 0) ?></div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="no-med-results" id="noMedResults" style="display:none;">
                                <i class="fas fa-search-minus"></i>
                                <p style="font-size:0.85rem;font-weight:600;">No medicines match your search</p>
                            </div>
                        </div>
                        
                        <div class="picker-footer">
                            <div class="footer-info"><i class="fas fa-info-circle"></i> <span id="footerInfoText">Select medicines and click "Add Selected"</span></div>
                            <button type="button" class="btn-add-selected" id="addSelectedBtn" onclick="addSelectedToCart()" disabled>
                                <i class="fas fa-cart-plus"></i> Add Selected to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <div class="section-title">
                    <i class="fas fa-shopping-cart"></i> Cart
                    <span class="badge-count" id="cartCount">0 items</span>
                </div>
                
                <div class="cart-container" id="cartContainer">
                    <div class="empty-cart" id="emptyCart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No items added yet</p>
                    </div>
                    <div id="cartItems" style="display:none;"></div>
                </div>
            </div>
            
            <div class="discount-section">
                <div class="discount-row">
                    <span class="discount-label"><i class="fas fa-tags"></i> Discount (TSh)</span>
                    <div class="discount-input-group">
                        <span style="font-weight:700;color:var(--text-secondary);font-size:1rem;font-family:'Courier New',monospace;">TSh</span>
                        <input type="text" id="discountAmountInput" class="form-control discount-input" placeholder="0" value="0" oninput="formatMoneyInput(this)" onfocus="this.select()">
                        <button type="button" class="btn-apply-discount" onclick="applyDiscount()"><i class="fas fa-check"></i> Apply</button>
                        <button type="button" class="btn-remove-discount" onclick="removeDiscount()"><i class="fas fa-times"></i> Remove</button>
                    </div>
                </div>
                
                <div class="discount-display">
                    <div class="info-item"><span class="label">Subtotal:</span> <span class="value" id="displaySubtotal">TSh 0</span></div>
                    <div class="info-item"><span class="label">Discount:</span> <span class="value" id="displayDiscount">TSh 0</span></div>
                    <div class="info-item" style="border-color: var(--primary); background: var(--primary-light);">
                        <span class="label" style="color:var(--primary);font-weight:700;"><i class="fas fa-star"></i> Premium:</span>
                        <span class="value" id="displayPremium" style="color:var(--primary);font-weight:700;">TSh 0</span>
                    </div>
                    <div class="info-item" style="border-color: var(--success); background: var(--success-light);">
                        <span class="label" style="font-weight:700;">Grand Total:</span>
                        <span class="value grand-total" id="displayGrandTotal">TSh 0</span>
                    </div>
                </div>
            </div>
            
            <div class="premium-section">
                <div class="premium-row">
                    <span class="premium-label">
                        <i class="fas fa-plus-circle"></i> Premium / Extra Bill
                        <span style="font-size:0.55rem; background:var(--primary-light); color:var(--primary); padding:1px 10px; border-radius:10px; font-weight:600;">Optional</span>
                    </span>
                    <div class="premium-input-group">
                        <span style="font-weight:700;color:var(--text-secondary);font-size:1rem;font-family:'Courier New',monospace;">TSh</span>
                        <input type="text" id="premiumAmountInput" class="form-control premium-input" placeholder="0" value="0" oninput="formatMoneyInput(this)" onfocus="this.select()">
                    </div>
                    <button type="button" class="btn-add-premium" onclick="applyPremium()"><i class="fas fa-plus"></i> Add Premium</button>
                    <button type="button" class="btn-remove-premium" onclick="removePremium()"><i class="fas fa-times"></i> Remove</button>
                </div>
                
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                    <span style="font-size:0.75rem; color:var(--text-secondary); font-weight:500; min-width:100px;"><i class="fas fa-pen"></i> Note:</span>
                    <input type="text" id="premiumNoteInput" class="form-control" placeholder="e.g. Premium consultation, Extra service..." style="flex:1; min-width:200px; padding: 8px 14px; border: 2px solid var(--border-color); border-radius: 8px; font-size:0.85rem; background: var(--bg-card); color: var(--text-primary); outline: none;">
                </div>
                
                <div class="premium-display" id="premiumDisplay">
                    <div class="premium-info">
                        <span style="font-size:0.8rem; font-weight:600; color:var(--primary);"><i class="fas fa-star"></i> Premium Added:</span>
                        <span style="font-weight:700; color:var(--primary); font-size:1.1rem; font-family:'Courier New',monospace;" id="premiumDisplayAmount">TSh 0</span>
                        <span style="font-size:0.75rem; color:var(--text-secondary); background:var(--bg-body); padding:2px 12px; border-radius:6px;" id="premiumDisplayNote"></span>
                        <button type="button" style="background:transparent;border:none;color:var(--danger);cursor:pointer;font-size:0.8rem;font-weight:600;" onclick="removePremium()"><i class="fas fa-times-circle"></i> Remove</button>
                    </div>
                </div>
            </div>
            
            <!-- ✅ PAYMENT INFO — SINGLE OPTION (PAY NOW) -->
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="section-title"><i class="fas fa-credit-card"></i> Payment Method <span class="badge-count" style="background:var(--success);">Pay Now</span></div>
                
                <div class="payment-info-box">
                    <div class="pay-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="pay-content">
                        <h4>Pay Now (Self)</h4>
                        <p>Pharmacy collects payment immediately — Stock deducted instantly</p>
                    </div>
                    <span class="pay-badge"><i class="fas fa-check-circle"></i> Active</span>
                </div>
                
                <div class="mt-3">
                    <div class="section-title" style="border-bottom: none; padding-bottom: 4px; margin-bottom: 8px;">
                        <i class="fas fa-money-bill-wave"></i> Payment Method
                    </div>
                    
                    <div class="payment-methods">
                        <button type="button" class="method-btn active" data-method="cash" onclick="selectPaymentMethod('cash')"><i class="fas fa-money-bill-wave"></i> Cash</button>
                        <button type="button" class="method-btn" data-method="m-pesa" onclick="selectPaymentMethod('m-pesa')"><i class="fas fa-mobile-alt"></i> M-Pesa</button>
                        <button type="button" class="method-btn" data-method="airtel_money" onclick="selectPaymentMethod('airtel_money')"><i class="fas fa-mobile-alt"></i> Airtel Money</button>
                        <button type="button" class="method-btn" data-method="tigo_pesa" onclick="selectPaymentMethod('tigo_pesa')"><i class="fas fa-mobile-alt"></i> Tigo Pesa</button>
                        <button type="button" class="method-btn" data-method="halopesa" onclick="selectPaymentMethod('halopesa')"><i class="fas fa-mobile-alt"></i> Halopesa</button>
                        <button type="button" class="method-btn" data-method="bank" onclick="selectPaymentMethod('bank')"><i class="fas fa-university"></i> Bank</button>
                        <button type="button" class="method-btn" data-method="card" onclick="selectPaymentMethod('card')"><i class="fas fa-credit-card"></i> Card</button>
                    </div>
                    <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="cash">
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn-complete-sale" id="completeSaleBtn" disabled>
                    <i class="fas fa-hand-holding-usd"></i> Complete Sale & Deduct Stock
                </button>
                <button type="button" class="btn-clear-cart" onclick="clearCart()"><i class="fas fa-trash"></i> Clear Cart</button>
                <a href="dashboard.php" class="btn-outline"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span> New OTC Sale
            <span>|</span>
            <span style="color:var(--success);font-size:0.6rem;"><i class="fas fa-hand-holding-usd"></i> Pay Now (Self) Only</span>
            <span>|</span>
            <span style="color:var(--success);font-size:0.6rem;"><i class="fas fa-boxes"></i> Stock: <span id="stockStatusDisplay">Deduct Instantly</span></span>
            <span>|</span> &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:700;">Notification</p>
        <p id="toastMessage" style="font-size:0.85rem;"></p>
    </div>
</div>

<script>
    function formatMoneyInput(input) {
        var raw = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
        if (raw === '' || raw === '.') { input.value = '0'; return; }
        var num = parseFloat(raw);
        if (isNaN(num)) { input.value = '0'; return; }
        input.value = num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
    
    function getRawNumber(value) {
        return parseFloat(String(value).replace(/,/g, '')) || 0;
    }

    function dismissMessage() {
        var mb = document.getElementById('messageBox');
        if (mb) { mb.style.opacity = '0'; setTimeout(function() { mb.style.display = 'none'; }, 500); }
    }
    
    // ✅ AUTO-DISMISS SUCCESS MESSAGE (No redirect)
    document.addEventListener('DOMContentLoaded', function() {
        var mb = document.getElementById('messageBox');
        if (mb) setTimeout(dismissMessage, 6000);
    });

    var cart = [];
    var itemIdCounter = 0;
    var currentDiscountAmount = 0;
    var subtotal = 0;
    var grandTotal = 0;
    var currentPremiumAmount = 0;
    var currentPremiumNote = '';
    var selectedMedicines = {};
    var showOnlyAvailableFilter = false;
    
    var predefinedFrequencies = <?= json_encode($predefined_frequencies) ?>;
    var predefinedRoutes = <?= json_encode($predefined_routes) ?>;
    var predefinedDosages = <?= json_encode($predefined_dosages) ?>;
    var predefinedInstructions = <?= json_encode($predefined_instructions) ?>;

    function toggleMedicinePicker() {
        var trigger = document.getElementById('pickerTrigger');
        var dropdown = document.getElementById('pickerDropdown');
        trigger.classList.toggle('open');
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            setTimeout(function() { document.getElementById('medSearchInput').focus(); }, 100);
        }
    }
    
    document.addEventListener('click', function(e) {
        var picker = document.getElementById('medicinePicker');
        if (picker && !picker.contains(e.target)) {
            document.getElementById('pickerTrigger').classList.remove('open');
            document.getElementById('pickerDropdown').classList.remove('show');
        }
    });
    
    function filterMedicineList(query) {
        query = query.toLowerCase().trim();
        var options = document.querySelectorAll('.med-option');
        var visibleCount = 0;
        var clearBtn = document.getElementById('clearSearchBtn');
        
        if (query.length > 0) clearBtn.classList.add('show');
        else clearBtn.classList.remove('show');
        
        options.forEach(function(opt) {
            var searchText = (opt.dataset.searchText || '').toLowerCase();
            var matches = query === '' || searchText.includes(query);
            if (showOnlyAvailableFilter && opt.dataset.medAvailable !== '1') matches = false;
            
            if (matches) { opt.style.display = ''; visibleCount++; }
            else opt.style.display = 'none';
        });
        
        document.getElementById('noMedResults').style.display = (visibleCount === 0) ? 'block' : 'none';
        
        var footerText = document.getElementById('footerInfoText');
        if (query) footerText.textContent = 'Found ' + visibleCount + ' medicine(s)';
        else footerText.textContent = 'Select medicines and click "Add Selected"';
    }
    
    function clearSearch() {
        var input = document.getElementById('medSearchInput');
        input.value = '';
        filterMedicineList('');
        input.focus();
    }
    
    function toggleMedicineSelection(element) {
        var medId = element.dataset.medId;
        var isAvailable = element.dataset.medAvailable === '1';
        var medName = element.dataset.medName;
        
        if (!isAvailable) {
            showToast('Warning', medName + ' is out of stock', 'warning');
            return;
        }
        
        if (selectedMedicines[medId]) {
            delete selectedMedicines[medId];
            element.classList.remove('selected');
        } else {
            selectedMedicines[medId] = true;
            element.classList.add('selected');
        }
        updateSelectedCount();
    }
    
    function updateSelectedCount() {
        var count = Object.keys(selectedMedicines).length;
        document.getElementById('selectedCount').textContent = count + ' selected';
        document.getElementById('addSelectedBtn').disabled = count === 0;
        
        var label = document.getElementById('pickerLabel');
        var labelText = document.getElementById('pickerLabelText');
        
        if (count > 0) {
            label.classList.add('has-selection');
            labelText.textContent = count + ' medicine(s) selected';
        } else {
            label.classList.remove('has-selection');
            labelText.textContent = 'Click to select medicine(s)...';
        }
    }
    
    function selectAllAvailable() {
        document.querySelectorAll('.med-option').forEach(function(opt) {
            if (opt.dataset.medAvailable === '1' && opt.style.display !== 'none') {
                selectedMedicines[opt.dataset.medId] = true;
                opt.classList.add('selected');
            }
        });
        updateSelectedCount();
        showToast('Success', 'All available medicines selected', 'success');
    }
    
    function clearAllSelected() {
        selectedMedicines = {};
        document.querySelectorAll('.med-option').forEach(function(opt) { opt.classList.remove('selected'); });
        updateSelectedCount();
    }
    
    function showOnlyAvailable() {
        showOnlyAvailableFilter = true;
        document.querySelectorAll('.action-chip').forEach(function(chip) { chip.classList.remove('active'); });
        event.target.closest('.action-chip').classList.add('active');
        filterMedicineList(document.getElementById('medSearchInput').value);
    }
    
    function showAllMedicines() {
        showOnlyAvailableFilter = false;
        document.querySelectorAll('.action-chip').forEach(function(chip) { chip.classList.remove('active'); });
        event.target.closest('.action-chip').classList.add('active');
        filterMedicineList(document.getElementById('medSearchInput').value);
    }
    
    function addSelectedToCart() {
        var selectedIds = Object.keys(selectedMedicines);
        if (selectedIds.length === 0) { showToast('Warning', 'Please select at least one medicine', 'warning'); return; }
        
        var addedCount = 0, skippedCount = 0;
        
        selectedIds.forEach(function(medId) {
            var opt = document.querySelector('.med-option[data-med-id="' + medId + '"]');
            if (!opt) return;
            
            var name = opt.dataset.medName;
            var price = parseFloat(opt.dataset.medPrice) || 0;
            var totalStock = parseInt(opt.dataset.medStock) || 0;
            var isAvailable = opt.dataset.medAvailable === '1';
            
            if (!isAvailable || totalStock <= 0) { skippedCount++; return; }
            var existing = cart.find(function(item) { return item.name === name; });
            if (existing) { skippedCount++; return; }
            if (price <= 0) { skippedCount++; return; }
            
            cart.push({
                id: ++itemIdCounter, name: name, price: price,
                quantity: '',
                maxStock: totalStock, total: 0,
                dosage: '', frequency: '', route: '', instructions: ''
            });
            addedCount++;
        });
        
        clearAllSelected();
        renderCart();
        updateTotals();
        
        document.getElementById('pickerTrigger').classList.remove('open');
        document.getElementById('pickerDropdown').classList.remove('show');
        
        if (addedCount > 0) {
            var msg = addedCount + ' medicine(s) added to cart - Please enter quantity';
            if (skippedCount > 0) msg += ' | ' + skippedCount + ' skipped';
            showToast('Success', msg, 'success');
        } else {
            showToast('Error', 'No medicines were added', 'error');
        }
    }
    
    function selectPaymentMethod(method) {
        document.querySelectorAll('.method-btn').forEach(function(btn) { btn.classList.remove('active'); });
        var btn = document.querySelector('[data-method="' + method + '"]');
        if (btn) btn.classList.add('active');
        document.getElementById('selectedPaymentMethod').value = method;
    }

    function updateQuantity(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        
        if (value === '' || value === null || value === undefined) {
            item.quantity = '';
            item.total = 0;
            var totalEl = document.getElementById('item_total_' + id);
            if (totalEl) totalEl.textContent = 'TSh 0';
            updateTotals();
            return;
        }
        
        var cleanValue = String(value).replace(/[^0-9]/g, '');
        var qty = parseInt(cleanValue) || 0;
        
        if (qty > item.maxStock) {
            qty = item.maxStock;
            showToast('Warning', 'Only ' + item.maxStock + ' available in stock', 'warning');
            var input = document.getElementById('qty_input_' + id);
            if (input) input.value = qty;
        }
        
        item.quantity = qty;
        item.total = item.price * qty;
        
        var totalEl = document.getElementById('item_total_' + id);
        if (totalEl) totalEl.textContent = 'TSh ' + item.total.toLocaleString();
        
        updateTotals();
    }
    
    function attachQuantityWheelBlocker() {
        document.querySelectorAll('.qty-input').forEach(function(input) {
            if (input.dataset.wheelBlocked === '1') return;
            input.dataset.wheelBlocked = '1';
            
            input.addEventListener('wheel', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.blur();
                return false;
            }, { passive: false });
        });
    }
    
    function attachQuantityInputHandler() {
        document.querySelectorAll('.qty-input').forEach(function(input) {
            if (input.dataset.inputHandlerAttached === '1') return;
            input.dataset.inputHandlerAttached = '1';
            
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            
            input.addEventListener('focus', function() {
                this.select();
            });
        });
    }
    
    function updateDosage(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (item) item.dosage = value;
    }
    
    function updateFrequency(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        if (value === '__manual__') {
            document.getElementById('freq_select_' + id).style.display = 'none';
            document.getElementById('freq_manual_' + id).style.display = 'block';
            document.getElementById('freq_manual_' + id).focus();
            item.frequency = '';
        } else { item.frequency = value; }
    }
    
    function updateFrequencyManual(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (item) item.frequency = value;
    }
    
    function toggleFreqManual(id, showManual) {
        var sel = document.getElementById('freq_select_' + id);
        var man = document.getElementById('freq_manual_' + id);
        var toggleBtn = document.getElementById('freq_toggle_' + id);
        
        if (showManual) {
            sel.style.display = 'none'; man.style.display = 'block'; man.focus();
            toggleBtn.innerHTML = '<i class="fas fa-list"></i>';
        } else {
            sel.style.display = 'block'; man.style.display = 'none'; man.value = '';
            toggleBtn.innerHTML = '<i class="fas fa-edit"></i>';
            var item = cart.find(function(i) { return i.id === id; });
            if (item) item.frequency = sel.value;
        }
    }
    
    function updateRoute(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        if (value === '__manual__') {
            document.getElementById('route_select_' + id).style.display = 'none';
            document.getElementById('route_manual_' + id).style.display = 'block';
            document.getElementById('route_manual_' + id).focus();
            item.route = '';
        } else { item.route = value; }
    }
    
    function updateRouteManual(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (item) item.route = value;
    }
    
    function toggleRouteManual(id, showManual) {
        var sel = document.getElementById('route_select_' + id);
        var man = document.getElementById('route_manual_' + id);
        var toggleBtn = document.getElementById('route_toggle_' + id);
        
        if (showManual) {
            sel.style.display = 'none'; man.style.display = 'block'; man.focus();
            toggleBtn.innerHTML = '<i class="fas fa-list"></i>';
        } else {
            sel.style.display = 'block'; man.style.display = 'none'; man.value = '';
            toggleBtn.innerHTML = '<i class="fas fa-edit"></i>';
            var item = cart.find(function(i) { return i.id === id; });
            if (item) item.route = sel.value;
        }
    }
    
    function addSuggestionToInstruction(id, suggestion) {
        var textarea = document.getElementById('instr_textarea_' + id);
        if (!textarea) return;
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        
        var currentValue = textarea.value;
        if (currentValue.toLowerCase().includes(suggestion.toLowerCase())) {
            showToast('Info', 'Already added: ' + suggestion, 'info');
            return;
        }
        
        textarea.value = currentValue.length > 0 ? currentValue + ', ' + suggestion : suggestion;
        item.instructions = textarea.value;
        updateInstructionDisplay(id);
    }
    
    function removeInstructionPart(id, partToRemove) {
        var textarea = document.getElementById('instr_textarea_' + id);
        if (!textarea) return;
        
        var parts = textarea.value.split(',').map(function(s) { return s.trim(); });
        var newParts = parts.filter(function(p) { return p.toLowerCase() !== partToRemove.toLowerCase().trim(); });
        textarea.value = newParts.join(', ');
        
        var item = cart.find(function(i) { return i.id === id; });
        if (item) item.instructions = textarea.value;
        updateInstructionDisplay(id);
    }
    
    function updateInstructionsFromTextarea(id, value) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        item.instructions = value;
        updateInstructionDisplay(id);
    }
    
    function updateInstructionDisplay(id) {
        var item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        
        var displayDiv = document.getElementById('instr_display_' + id);
        if (!displayDiv) return;
        
        var parts = (item.instructions || '').split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
        
        if (parts.length === 0) {
            displayDiv.innerHTML = '<span style="font-size:0.7rem;color:var(--text-muted);">No instructions added</span>';
            return;
        }
        
        displayDiv.innerHTML = parts.map(function(part) {
            var escapedPart = part.replace(/'/g, "\\'");
            return '<span class="instr-tag">' + part + ' <span class="remove-instr" onclick="removeInstructionPart(' + id + ', \'' + escapedPart + '\')">&times;</span></span>';
        }).join('');
    }

    function removeFromCart(id) {
        cart = cart.filter(function(item) { return item.id !== id; });
        renderCart();
        updateTotals();
    }
    
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

    function renderCart() {
        var itemsDiv = document.getElementById('cartItems');
        var emptyDiv = document.getElementById('emptyCart');
        var countEl = document.getElementById('cartCount');
        var btn = document.getElementById('completeSaleBtn');
        
        countEl.textContent = cart.length + ' items';
        
        if (cart.length === 0) {
            emptyDiv.style.display = 'block'; itemsDiv.style.display = 'none'; btn.disabled = true; return;
        }
        
        emptyDiv.style.display = 'none'; itemsDiv.style.display = 'block';
        
        var html = '';
        
        cart.forEach(function(item) {
            var instrText = item.instructions || '';
            
            var freqOptions = '<option value="">-- Select Frequency --</option>';
            predefinedFrequencies.forEach(function(f) {
                var sel = (item.frequency === f) ? 'selected' : '';
                freqOptions += '<option value="' + f + '" ' + sel + '>' + f + '</option>';
            });
            freqOptions += '<option value="__manual__">✏️ Type manually...</option>';
            
            var routeOptions = '<option value="">-- Select Route --</option>';
            predefinedRoutes.forEach(function(r) {
                var sel = (item.route === r) ? 'selected' : '';
                routeOptions += '<option value="' + r + '" ' + sel + '>' + r + '</option>';
            });
            routeOptions += '<option value="__manual__">✏️ Type manually...</option>';
            
            var dosageListId = 'dosage_list_' + item.id;
            var dosageOptions = '';
            predefinedDosages.forEach(function(d) { dosageOptions += '<option value="' + d + '">'; });
            
            var suggestionHtml = '';
            predefinedInstructions.slice(0, 15).forEach(function(sug) {
                var escapedSug = sug.replace(/'/g, "\\'");
                suggestionHtml += '<button type="button" class="suggestion-btn" onclick="addSuggestionToInstruction(' + item.id + ', \'' + escapedSug + '\')">' + sug + '</button>';
            });
            
            var qtyValue = (item.quantity === '' || item.quantity === 0) ? '' : item.quantity;
            var totalDisplay = item.total > 0 ? item.total.toLocaleString() : '0';
            
            html += `
                <div class="cart-item">
                    <div class="item-header">
                        <div class="item-info">
                            <div class="item-name"><i class="fas fa-pills" style="color:var(--primary);"></i> ${item.name}</div>
                            <span class="item-price-badge">TSh ${item.price.toLocaleString()} / unit</span>
                            <span style="font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-boxes"></i> Max: ${item.maxStock}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="item-total" id="item_total_${item.id}">TSh ${totalDisplay}</span>
                            <button class="btn-remove" onclick="removeFromCart(${item.id})"><i class="fas fa-times"></i> Remove</button>
                        </div>
                    </div>
                    
                    <div class="item-details-grid">
                        <div class="detail-field">
                            <label><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                            <input type="text" 
                                   inputmode="numeric" 
                                   pattern="[0-9]*"
                                   id="qty_input_${item.id}" 
                                   class="qty-input" 
                                   value="${qtyValue}" 
                                   placeholder="0"
                                   maxlength="6"
                                   onchange="updateQuantity(${item.id}, this.value)"
                                   oninput="updateQuantity(${item.id}, this.value)">
                        </div>
                        <div class="detail-field">
                            <label><i class="fas fa-prescription-bottle"></i> Dosage</label>
                            <input type="text" id="dosage_input_${item.id}" value="${item.dosage || ''}" placeholder="e.g. 1 tablet, 5ml..." list="${dosageListId}" oninput="updateDosage(${item.id}, this.value)">
                            <datalist id="${dosageListId}">${dosageOptions}</datalist>
                        </div>
                        <div class="detail-field">
                            <label><i class="fas fa-clock"></i> Frequency</label>
                            <div class="select-with-manual">
                                <select id="freq_select_${item.id}" onchange="updateFrequency(${item.id}, this.value)">${freqOptions}</select>
                                <input type="text" id="freq_manual_${item.id}" class="manual-input" placeholder="Type frequency..." value="${item.frequency || ''}" oninput="updateFrequencyManual(${item.id}, this.value)">
                                <button type="button" class="btn-manual-toggle" id="freq_toggle_${item.id}" onclick="toggleFreqManual(${item.id}, document.getElementById('freq_manual_${item.id}').style.display === 'none')"><i class="fas fa-edit"></i></button>
                            </div>
                        </div>
                        <div class="detail-field">
                            <label><i class="fas fa-route"></i> Route</label>
                            <div class="select-with-manual">
                                <select id="route_select_${item.id}" onchange="updateRoute(${item.id}, this.value)">${routeOptions}</select>
                                <input type="text" id="route_manual_${item.id}" class="manual-input" placeholder="Type route..." value="${item.route || ''}" oninput="updateRouteManual(${item.id}, this.value)">
                                <button type="button" class="btn-manual-toggle" id="route_toggle_${item.id}" onclick="toggleRouteManual(${item.id}, document.getElementById('route_manual_${item.id}').style.display === 'none')"><i class="fas fa-edit"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="instructions-section">
                        <div class="instr-label"><i class="fas fa-sticky-note"></i> Instructions</div>
                        <div class="instr-textarea-wrapper">
                            <textarea id="instr_textarea_${item.id}" placeholder="e.g. 2x daily, After meals..." oninput="updateInstructionsFromTextarea(${item.id}, this.value)" style="min-height:55px;max-height:120px;">${instrText}</textarea>
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
        
        setTimeout(function() {
            attachQuantityWheelBlocker();
            attachQuantityInputHandler();
        }, 50);
    }

    function applyPremium() {
        var input = document.getElementById('premiumAmountInput');
        var noteInput = document.getElementById('premiumNoteInput');
        var premium = getRawNumber(input.value);
        
        if (premium <= 0) { showToast('Warning', 'Enter a valid amount', 'warning'); return; }
        
        currentPremiumAmount = premium;
        currentPremiumNote = noteInput.value.trim() || 'Premium added';
        
        document.getElementById('premiumDisplay').style.display = 'block';
        document.getElementById('premiumDisplayAmount').textContent = 'TSh ' + premium.toLocaleString();
        document.getElementById('premiumDisplayNote').textContent = currentPremiumNote;
        
        input.disabled = true;
        document.querySelector('.btn-add-premium').disabled = true;
        noteInput.disabled = true;
        
        document.getElementById('premiumAmountHidden').value = premium;
        document.getElementById('premiumNoteHidden').value = currentPremiumNote;
        
        updateTotals();
        showToast('Success', 'Premium TSh ' + premium.toLocaleString() + ' added!', 'success');
    }
    
    function removePremium() {
        currentPremiumAmount = 0;
        currentPremiumNote = '';
        
        var input = document.getElementById('premiumAmountInput');
        var noteInput = document.getElementById('premiumNoteInput');
        
        input.value = '0';
        input.disabled = false;
        noteInput.value = '';
        noteInput.disabled = false;
        document.querySelector('.btn-add-premium').disabled = false;
        
        document.getElementById('premiumDisplay').style.display = 'none';
        document.getElementById('premiumAmountHidden').value = 0;
        document.getElementById('premiumNoteHidden').value = '';
        
        updateTotals();
        showToast('Info', 'Premium removed', 'info');
    }

    function updateTotals() {
        subtotal = 0;
        cart.forEach(function(item) { subtotal += item.total; });
        
        var discountInput = document.getElementById('discountAmountInput');
        var discountAmount = getRawNumber(discountInput.value);
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
            discountInput.value = discountAmount.toLocaleString();
        }
        currentDiscountAmount = discountAmount;
        
        var premiumAmount = currentPremiumAmount || 0;
        grandTotal = subtotal - discountAmount + premiumAmount;
        if (grandTotal < 0) grandTotal = 0;
        
        document.getElementById('displaySubtotal').textContent = 'TSh ' + subtotal.toLocaleString();
        document.getElementById('displayDiscount').textContent = 'TSh ' + discountAmount.toLocaleString();
        document.getElementById('displayPremium').textContent = 'TSh ' + premiumAmount.toLocaleString();
        document.getElementById('displayGrandTotal').textContent = 'TSh ' + grandTotal.toLocaleString();
        
        var itemsForJson = cart.map(function(item) {
            return {
                name: item.name, price: item.price, 
                quantity: parseInt(item.quantity) || 0,
                total: item.total,
                dosage: item.dosage || '', frequency: item.frequency || '',
                route: item.route || '', instructions: item.instructions || ''
            };
        });
        document.getElementById('itemsJson').value = JSON.stringify(itemsForJson);
        document.getElementById('discountAmountHidden').value = discountAmount;
    }

    function applyDiscount() {
        var input = document.getElementById('discountAmountInput');
        var discount = getRawNumber(input.value);
        
        if (cart.length === 0) { showToast('Error', 'Cart is empty!', 'error'); return; }
        if (discount < 0) { showToast('Error', 'Discount cannot be negative', 'error'); return; }
        if (discount > subtotal) {
            discount = subtotal;
            input.value = discount.toLocaleString();
            showToast('Warning', 'Discount adjusted to subtotal', 'warning');
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

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast-custom ' + type;
        toast.style.display = 'flex';
        toast.classList.add('show');
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('pickerTrigger').classList.remove('open');
            document.getElementById('pickerDropdown').classList.remove('show');
        }
        if (e.key === 'Enter' && document.activeElement?.id === 'discountAmountInput') { e.preventDefault(); applyDiscount(); }
        if (e.key === 'Enter' && document.activeElement?.id === 'premiumAmountInput') { e.preventDefault(); applyPremium(); }
    });

    // ✅ CONFIRM BEFORE SUBMIT
    document.getElementById('otcSaleForm').addEventListener('submit', function(e) {
        if (cart.length === 0) {
            e.preventDefault();
            showToast('Error', 'Please add at least one medicine', 'error');
            return false;
        }
        
        var hasZeroQty = cart.some(function(item) { return !item.quantity || item.quantity <= 0; });
        if (hasZeroQty) {
            e.preventDefault();
            showToast('Error', 'Please enter quantity for all items', 'error');
            return false;
        }
        
        if (!confirm('Complete this OTC sale?\n\nStock will be deducted and payment recorded.')) {
            e.preventDefault();
            return false;
        }
    });

    console.log('%c💊 Braick OTC - V2 (Pay Now Only)', 'font-size:18px;font-weight:bold;color:#059669;');
    console.log('%c✅ NO redirect after sale', 'font-size:13px;color:#34D399;font-weight:bold;');
    console.log('%c✅ ONLY "Pay Now (Self)" option', 'font-size:13px;color:#34D399;');
    console.log('%c✅ Stock deducted instantly', 'font-size:13px;color:#34D399;');
    console.log('%c✅ Sale stored in otc_sales table', 'font-size:13px;color:#34D399;');
</script>

</body>
</html>