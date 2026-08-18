<?php
// ================================================================
// FILE: frontend/pages/pharmacy/new_otc_sale.php
// PHARMACY - NEW OTC SALE (WITH 2 OPTIONS: PAY NOW OR SEND TO CASHIER)
// FIXED: Dark Mode works with header button (like inventory.php)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

// ================================================================
// GET MEDICINES INVENTORY (Active & In Stock)
// ================================================================
$medicines = [];
$stmt = $db->prepare("
    SELECT id, medication_name, quantity, selling_price 
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active' AND quantity > 0
    ORDER BY medication_name
");
$stmt->execute([$user_branch_id]);
$medicines = $stmt->fetchAll();

// ================================================================
// GET LOW STOCK COUNT
// ================================================================
$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
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
// PROCESS OTC SALE - WITH 2 OPTIONS
// ================================================================
$message = '';
$message_type = '';
$sale_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $customer_name = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $discount_amount = (float)($_POST['discount_amount'] ?? 0);
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
    
    $stock_errors = [];
    foreach ($items as $item) {
        $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ? AND branch_id = ?");
        $stmt->execute([$item['inventory_id'], $user_branch_id]);
        $stock = $stmt->fetch();
        if (!$stock || $stock['quantity'] < $item['quantity']) {
            $stock_errors[] = "Insufficient stock for {$item['name']} (Available: " . ($stock['quantity'] ?? 0) . ")";
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
            
            $patient_id = null;
            if (!empty($customer_phone)) {
                $stmt_check = $db->prepare("SELECT id FROM patients WHERE phone = ? AND branch_id = ? LIMIT 1");
                $stmt_check->execute([$customer_phone, $user_branch_id]);
                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $patient_id = $existing['id'];
                }
            }
            
            if (!$patient_id) {
                $patient_code = 'PAT-OTC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("
                    INSERT INTO patients (patient_id, full_name, phone, branch_id, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$patient_code, $customer_name, $customer_phone ?: 'N/A', $user_branch_id]);
                $patient_id = $db->lastInsertId();
            }
            
            $payment_status = ($payment_option === 'self') ? 'paid' : 'pending';
            $bill_status = ($payment_option === 'self') ? 'paid' : 'pending';
            $balance = ($payment_option === 'self') ? 0 : $grand_total;
            
            $stmt = $db->prepare("
                INSERT INTO patient_bills (
                    bill_number, patient_id, 
                    subtotal, total_amount, discount_amount, balance, 
                    status, created_by, branch_id,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $bill_number,
                $patient_id,
                $subtotal,
                $grand_total,
                $discount_amount,
                $balance,
                $bill_status,
                $user_id,
                $user_branch_id
            ]);
            $bill_id = $db->lastInsertId();
            
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, item_type, item_name, 
                        quantity, unit_price, total_price,
                        payment_status, is_paid, status, created_at
                    ) VALUES (?, 'medication', ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $is_paid = ($payment_option === 'self') ? 1 : 0;
                $item_payment_status = ($payment_option === 'self') ? 'paid' : 'pending';
                $stmt->execute([
                    $bill_id,
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['total'],
                    $item_payment_status,
                    $is_paid
                ]);
            }
            
            $otc_payment_status = ($payment_option === 'self') ? 'paid' : 'pending';
            $payment_notes = ($payment_option === 'self') ? 'Paid by Pharmacy (Self)' : 'OTC Sale - Bill sent to Cashier';
            
            $stmt = $db->prepare("
                INSERT INTO otc_sales (
                    sale_number, customer_name, customer_phone, 
                    patient_id, total_amount, discount_amount, net_amount, bill_id,
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
                $payment_notes
            ]);
            $sale_id = $db->lastInsertId();
            
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    SELECT id FROM medications_inventory 
                    WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$item['name'], $user_branch_id]);
                $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                $inventory_id = $inv['id'] ?? null;
                
                $stmt = $db->prepare("
                    INSERT INTO otc_sale_items (
                        sale_id, inventory_id, medicine_name, 
                        quantity, unit_price, total_price, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $sale_id,
                    $inventory_id,
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['total']
                ]);
            }
            
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity - ? 
                    WHERE medication_name = ? AND branch_id = ?
                ");
                $stmt->execute([$item['quantity'], $item['name'], $user_branch_id]);
                
                $stmt = $db->prepare("
                    SELECT id FROM medications_inventory 
                    WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$item['name'], $user_branch_id]);
                $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                $inventory_id = $inv['id'] ?? null;
                
                if ($inventory_id) {
                    $stmt = $db->prepare("
                        INSERT INTO stock_movements 
                        (inventory_id, sale_type, sale_id, quantity, movement_type, performed_by, notes)
                        VALUES (?, 'otc', ?, ?, 'out', ?, ?)
                    ");
                    $stmt->execute([
                        $inventory_id, 
                        $sale_id, 
                        $item['quantity'], 
                        $user_id,
                        $payment_notes
                    ]);
                }
            }
            
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
                    'OTC Sale - Paid by Pharmacy (Self)'
                ]);
                
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET payment_status = 'paid', is_paid = 1, paid_at = NOW()
                    WHERE bill_id = ?
                ");
                $stmt->execute([$bill_id]);
            }
            
            $stmt = $db->prepare("
                UPDATE otc_sales 
                SET bill_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$bill_id, $sale_id]);
            
            $db->commit();
            
            if ($payment_option === 'self') {
                $message = "✅ OTC Sale completed and PAID! Receipt #" . ($receipt_number ?? 'N/A');
                $message_type = 'success';
            } else {
                $message = "✅ OTC Sale completed! Bill sent to Cashier.";
                $message_type = 'success';
            }
            
            echo '<script>
                setTimeout(function() {
                    window.location.href = "otc_history.php?success=1";
                }, 2000);
            </script>';
            
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

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES - PAGE-SPECIFIC (Dark mode compatible) -->
<!-- ================================================================ -->
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
        --otc-color: #7C3AED;
        --otc-bg: #EDE9FE;
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
    
    .form-control:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .form-row {
        margin-bottom: 16px;
    }
    
    .form-row:last-child {
        margin-bottom: 0;
    }
    
    select.form-control {
        appearance: auto;
        cursor: pointer;
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
    
    .cart-container {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        min-height: 80px;
    }
    
    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 16px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .cart-item:hover {
        background: var(--primary-light);
    }
    
    .cart-item:last-child {
        border-bottom: none;
    }
    
    .cart-item .item-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .cart-item .item-info .item-name {
        font-weight: 500;
        font-size: 0.9rem;
        color: var(--text-primary);
    }
    
    .cart-item .item-info .item-meta {
        font-size: 0.75rem;
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
        width: 200px;
        max-width: 280px;
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
        transform: scale(1.02);
    }
    
    .discount-section .discount-input-group .discount-input::placeholder {
        font-weight: 400;
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    
    .discount-section .discount-input-group .currency-prefix {
        font-weight: 700;
        color: var(--text-secondary);
        font-size: 1rem;
        font-family: 'Courier New', monospace;
        padding-right: 4px;
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
    
    .discount-display .info-item .value.subtotal-value {
        color: var(--primary);
    }
    
    .discount-display .info-item .value.discount-value {
        color: var(--gold);
    }
    
    .discount-display .info-item .value.grand-total {
        color: var(--success);
        font-size: 1.15rem;
        font-weight: 800;
    }
    
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
    
    [data-theme="dark"] .payment-option-card .option-icon.cashier {
        background: #2D1B5F;
        color: #A78BFA;
    }
    
    [data-theme="dark"] .payment-option-card .option-icon.self {
        background: #1A3A2A;
        color: #34D399;
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
    
    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn-complete-sale {
        background: var(--success);
        color: white;
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
    }
    
    .btn-complete-sale:hover:not(:disabled) {
        background: var(--success-dark);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(5, 150, 105, 0.35);
    }
    
    .btn-complete-sale:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
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
       PAGE HEADER - BLUE BACKGROUND
       ================================================================ */
    .page-header-blue {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 32px;
        margin-bottom: 24px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
        color: white;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }
    
    .page-header-blue::before {
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
    
    .page-header-blue .page-title {
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
    
    .page-header-blue .page-title i {
        font-size: 1.8rem;
        opacity: 0.9;
    }
    
    .page-header-blue .page-subtitle {
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
    
    .page-header-blue .page-subtitle strong {
        color: white;
        font-weight: 600;
    }
    
    .page-header-blue .stat-chip {
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
    
    .page-header-blue .stat-chip i {
        opacity: 0.8;
    }
    
    .page-header-blue .btn-outline-light {
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
    
    .page-header-blue .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    /* ================================================================
       2 CARDS: BLUE + ORANGE
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
    
    .stat-card-2::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -10%;
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }
    
    .stat-card-2:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 10px 32px rgba(0,0,0,0.2);
    }
    
    .stat-card-2:hover::before { transform: scale(1.3); right: -10%; }
    .stat-card-2:hover::after { transform: scale(1.4); bottom: -30%; }
    
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
    
    .stat-card-2 .stat-sub {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.8);
        margin-top: 2px;
    }
    
    .stat-card-2 .stat-arrow {
        position: absolute;
        right: 14px;
        bottom: 14px;
        color: rgba(255,255,255,0.12);
        font-size: 0.8rem;
        transition: all 0.3s ease;
        z-index: 1;
    }
    
    .stat-card-2:hover .stat-arrow {
        transform: translateX(6px);
        color: rgba(255,255,255,0.4);
    }
    
    .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-blue:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
    
    .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
    
    [data-theme="dark"] .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    [data-theme="dark"] .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.4s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
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
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-pulse-once {
        animation: pulseOnce 0.5s ease;
    }
    
    @keyframes pulseOnce {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }
    
    /* Toast */
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
    
    @media (max-width: 768px) {
        .sale-form-card {
            padding: 16px 18px;
        }
        .medicine-select-row {
            flex-direction: column;
        }
        .medicine-select-row .form-group {
            max-width: 100% !important;
        }
        .action-buttons {
            flex-direction: column;
            align-items: stretch;
        }
        .action-buttons .btn-complete-sale,
        .action-buttons .btn-clear-cart,
        .action-buttons .btn-outline {
            width: 100%;
            justify-content: center;
        }
        .cart-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .cart-item .item-total {
            text-align: left;
            width: 100%;
        }
        .discount-section .discount-row {
            flex-direction: column;
            align-items: stretch;
        }
        .discount-section .discount-input-group {
            flex-wrap: wrap;
        }
        .discount-section .discount-input-group .discount-input {
            width: 100%;
            max-width: 100%;
        }
        .discount-display {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }
        .discount-display .info-item {
            justify-content: space-between;
        }
        .payment-options {
            flex-direction: column;
        }
        .payment-option-card {
            min-width: unset;
        }
        .payment-methods {
            justify-content: center;
        }
        .stats-2-cards {
            grid-template-columns: 1fr;
        }
        .page-header-blue {
            padding: 18px 20px;
        }
        .page-header-blue .page-title {
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 480px) {
        .payment-methods .method-btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .discount-section .discount-input-group .discount-input {
            font-size: 1rem;
            padding: 8px 12px;
        }
        .discount-display .info-item {
            font-size: 0.8rem;
            padding: 4px 12px;
        }
        .discount-display .info-item .value.grand-total {
            font-size: 1rem;
        }
        .stats-2-cards {
            grid-template-columns: 1fr;
        }
        .page-header-blue .page-title {
            font-size: 1.1rem;
        }
        .page-header-blue .page-subtitle {
            font-size: 0.8rem;
        }
        .page-header-blue .stat-chip {
            font-size: 0.6rem;
            padding: 2px 10px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - BLUE BACKGROUND -->
    <!-- ================================================================ -->
    <div class="page-header-blue animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                New OTC Sale
            </h1>
            <p class="page-subtitle">
                Sell medicines over-the-counter with discount (TSh amount)
                <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="stat-chip">
                    <i class="fas fa-pills"></i> <?= count($medicines) ?> medicines in stock
                </span>
                <span class="stat-chip">
                    <i class="fas fa-cash-register"></i> 2 Payment Options
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

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 2 CARDS: BLUE + ORANGE -->
    <!-- ================================================================ -->
    <div class="stats-2-cards animate-fade-in-up">
        
        <!-- Card 1: Medicines in Stock - BLUE -->
        <div class="stat-card-2 card-blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Medicines in Stock</p>
                <p class="stat-number"><?= count($medicines) ?></p>
                <p class="stat-sub">Active inventory items</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- Card 2: Low Stock Alerts - ORANGE -->
        <div class="stat-card-2 card-orange">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Low Stock Alerts</p>
                <p class="stat-number"><?= $low_stock_count ?></p>
                <p class="stat-sub">Items below reorder level</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALE FORM -->
    <!-- ================================================================ -->
    <div class="sale-form-card animate-fade-in-up">
        <form method="POST" action="" id="otcSaleForm">
            <input type="hidden" name="action" value="complete_sale">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            <input type="hidden" name="discount_amount" id="discountAmountHidden" value="0">
            <input type="hidden" name="payment_option" id="paymentOptionHidden" value="cashier">
            
            <!-- Customer Information -->
            <div class="section-title">
                <i class="fas fa-user"></i>
                Customer Information
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
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="section-title">
                    <i class="fas fa-pills"></i>
                    Add Medicine
                    <span class="badge-count">Stock: <?= count($medicines) ?></span>
                </div>
                
                <div class="medicine-select-row">
                    <div class="form-group">
                        <label class="form-label">Select Medicine <span class="required">*</span></label>
                        <select id="medicineSelect" class="form-control">
                            <option value="">-- Select Medicine --</option>
                            <?php foreach ($medicines as $med): ?>
                                <option value="<?= $med['id'] ?>" 
                                        data-price="<?= $med['selling_price'] ?? 0 ?>"
                                        data-stock="<?= $med['quantity'] ?>"
                                        data-name="<?= htmlspecialchars($med['medication_name']) ?>">
                                    <?= htmlspecialchars($med['medication_name']) ?> 
                                    (Stock: <?= $med['quantity'] ?>) - TSh <?= number_format($med['selling_price'] ?? 0) ?>
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
                        <span class="currency-prefix">TSh</span>
                        <input type="number" id="discountAmountInput" class="form-control discount-input" 
                               placeholder="0" min="0" value="0" step="100"
                               oninput="applyDiscountFromInput()">
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
            <!-- PAYMENT OPTIONS - 2 OPTIONS -->
            <!-- ================================================================ -->
            <div class="mt-4 pt-4 border-t border-gray-200">
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
                        </div>
                        <div class="option-radio"></div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Method (for self payment) -->
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
                <button type="submit" class="btn-complete-sale" id="completeSaleBtn" disabled>
                    <i class="fas fa-receipt"></i> Complete Sale
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
    // DARK MODE - INAFANYA KAZI NA HEADER BUTTON (kama inventory)
    // ================================================================
    // Dark mode inashughulikiwa na pharmacy_header.php
    // Tuna-hakikisha tu kwamba CSS variables zinafanya kazi
    var htmlElement = document.documentElement;
    console.log('🌙 Dark mode initialized. Current theme:', htmlElement.getAttribute('data-theme'));

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
            btn.style.background = 'linear-gradient(135deg, #059669, #047857)';
        } else {
            btn.innerHTML = '<i class="fas fa-receipt"></i> Send to Cashier';
            btn.style.background = 'linear-gradient(135deg, #7C3AED, #6D28D9)';
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
        var stock = parseInt(option.dataset.stock) || 0;
        
        if (qty <= 0) {
            showToast('Error', 'Quantity must be greater than 0', 'error');
            return;
        }
        
        if (qty > stock) {
            showToast('Error', 'Not enough stock! Available: ' + stock, 'error');
            return;
        }
        
        if (price <= 0) {
            showToast('Error', 'Price must be greater than 0', 'error');
            return;
        }
        
        var name = option.dataset.name;
        var inventory_id = parseInt(option.value);
        var total = price * qty;
        
        var existing = cart.find(function(item) { return item.inventory_id === inventory_id; });
        if (existing) {
            existing.quantity += qty;
            existing.total = existing.quantity * existing.price;
        } else {
            cart.push({
                id: ++itemIdCounter,
                inventory_id: inventory_id,
                name: name,
                price: price,
                quantity: qty,
                total: total
            });
        }
        
        var newStock = stock - qty;
        option.dataset.stock = newStock;
        option.text = option.text.replace(/\(Stock: \d+\)/, '(Stock: ' + newStock + ')');
        
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
        document.getElementById('discountAmountInput').value = 0;
        document.getElementById('discountAmountHidden').value = 0;
        renderCart();
        updateTotals();
        showToast('Info', 'Cart cleared', 'info');
    }

    // ================================================================
    // RENDER CART
    // ================================================================
    function renderCart() {
        var container = document.getElementById('cartContainer');
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
        cart.forEach(function(item) {
            html += `
                <div class="cart-item">
                    <div class="item-info">
                        <span class="item-name">${item.name}</span>
                        <span class="item-meta">Qty: ${item.quantity}</span>
                        <span class="item-price">TSh ${item.price.toLocaleString()}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="item-total">TSh ${item.total.toLocaleString()}</span>
                        <button class="btn-remove" onclick="removeFromCart(${item.id})">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                </div>
            `;
        });
        itemsDiv.innerHTML = html;
        btn.disabled = false;
    }

    // ================================================================
    // UPDATE TOTALS
    // ================================================================
    function updateTotals() {
        subtotal = 0;
        cart.forEach(function(item) {
            subtotal += item.total;
        });
        
        var discountAmount = currentDiscountAmount;
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
            document.getElementById('discountAmountInput').value = discountAmount;
            document.getElementById('discountAmountHidden').value = discountAmount;
        }
        grandTotal = subtotal - discountAmount;
        if (grandTotal < 0) grandTotal = 0;
        
        document.getElementById('displaySubtotal').textContent = 'TSh ' + subtotal.toLocaleString();
        document.getElementById('displayDiscount').textContent = 'TSh ' + discountAmount.toLocaleString();
        document.getElementById('displayGrandTotal').textContent = 'TSh ' + grandTotal.toLocaleString();
        
        document.getElementById('itemsJson').value = JSON.stringify(cart);
        document.getElementById('discountAmountHidden').value = discountAmount;
    }

    // ================================================================
    // APPLY DISCOUNT FROM INPUT
    // ================================================================
    function applyDiscountFromInput() {
        var input = document.getElementById('discountAmountInput');
        var discount = parseFloat(input.value) || 0;
        
        if (discount < 0) {
            discount = 0;
            input.value = 0;
        }
        
        currentDiscountAmount = discount;
        document.getElementById('discountAmountHidden').value = discount;
        updateTotals();
    }

    // ================================================================
    // APPLY DISCOUNT
    // ================================================================
    function applyDiscount() {
        var input = document.getElementById('discountAmountInput');
        var discount = parseFloat(input.value) || 0;
        
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
            input.value = discount;
        }
        
        currentDiscountAmount = discount;
        document.getElementById('discountAmountHidden').value = discount;
        updateTotals();
        
        var grandTotalEl = document.getElementById('displayGrandTotal');
        grandTotalEl.parentElement.classList.add('animate-pulse-once');
        
        showToast('Success', 'Discount TSh ' + discount.toLocaleString() + ' applied!', 'success');
    }

    // ================================================================
    // REMOVE DISCOUNT
    // ================================================================
    function removeDiscount() {
        currentDiscountAmount = 0;
        document.getElementById('discountAmountInput').value = 0;
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
    // DATE & TIME - UPDATED BY HEADER
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.querySelector('.footer .footer-time');
        if (ftEl) {
            ftEl.textContent = timeStr;
        }
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

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
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('.search-wrapper input');
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Enter' && document.activeElement?.id === 'discountAmountInput') {
            applyDiscount();
        }
    });

    console.log('%c💊 Braick - New OTC Sale (Dark Mode FIXED - uses header button)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark Mode: Controlled by header button (like inventory.php)', 'font-size:13px; color:#34D399;');
    console.log('%c🔵 BLUE HEADER with stats', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔵 Card 1: Medicines in Stock (BLUE)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🟠 Card 2: Low Stock Alerts (ORANGE)', 'font-size:13px; color:#D97706;');
    console.log('%c✅ 2 Payment Options: Send to Cashier | Pay Now (Self)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Dark mode toggle works via header button', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>