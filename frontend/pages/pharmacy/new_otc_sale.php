<?php
// ================================================================
// FILE: frontend/pages/pharmacy/new_otc_sale.php
// PHARMACY - NEW OTC SALE (WITH DISCOUNT - WIDE INPUT)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

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
// PROCESS OTC SALE
// ================================================================
$message = '';
$message_type = '';
$sale_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $customer_name = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $discount_percent = (float)($_POST['discount_percent'] ?? 0);
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as &$item) {
        $item['total'] = $item['quantity'] * $item['price'];
        $subtotal += $item['total'];
    }
    
    $discount_amount = ($subtotal * $discount_percent) / 100;
    $grand_total = $subtotal - $discount_amount;
    
    // Validation
    $errors = [];
    if (empty($items)) {
        $errors[] = 'Please add at least one medicine';
    }
    
    // Check stock
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
            
            // Insert OTC sale
            $stmt = $db->prepare("
                INSERT INTO otc_sales (
                    sale_number, customer_name, customer_phone, 
                    total_amount, discount_amount, net_amount,
                    payment_method, payment_status, sold_by, branch_id, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sale_number,
                $customer_name,
                $customer_phone,
                $subtotal,
                $discount_amount,
                $grand_total,
                $payment_method,
                $user_id,
                $user_branch_id,
                'OTC Sale - ' . date('Y-m-d H:i:s')
            ]);
            $sale_id = $db->lastInsertId();
            
            // Insert items and update stock
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO otc_sale_items (sale_id, inventory_id, medicine_name, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sale_id,
                    $item['inventory_id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['total']
                ]);
                
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity - ? 
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$item['quantity'], $item['inventory_id'], $user_branch_id]);
                
                $stmt = $db->prepare("
                    INSERT INTO stock_movements 
                    (inventory_id, sale_type, sale_id, quantity, movement_type, performed_by, notes)
                    VALUES (?, 'otc', ?, ?, 'out', ?, 'OTC Sale')
                ");
                $stmt->execute([$item['inventory_id'], $sale_id, $item['quantity'], $user_id]);
            }
            
            $db->commit();
            
            $message = "✅ OTC Sale completed successfully!";
            $message_type = 'success';
            
            // Redirect to print receipt
            echo '<script>
                setTimeout(function() {
                    window.location.href = "print_receipt.php?type=otc&id=' . $sale_id . '";
                }, 1500);
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
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

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

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
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
    
    .btn-add-medicine:active {
        transform: scale(0.97);
    }
    
    /* ================================================================
       CART ITEMS
       ================================================================ */
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
        background: var(--primary-bg);
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
        background: var(--danger-dark);
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
    
    /* ================================================================
       DISCOUNT SECTION - WIDE INPUT
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
    
    /* ✅ DISCOUNT INPUT - WIDE & CLEAR */
    .discount-section .discount-input-group .discount-input {
        width: 160px;
        max-width: 200px;
        padding: 10px 16px;
        font-size: 1.2rem;
        font-weight: 700;
        text-align: center;
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
    
    .discount-section .discount-input-group .percent-sign {
        font-weight: 700;
        color: var(--text-secondary);
        font-size: 1.2rem;
        font-family: 'Courier New', monospace;
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
    
    .btn-apply-discount:active {
        transform: scale(0.97);
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
        background: var(--danger-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }
    
    /* Discount Display */
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
    
    .discount-display .info-item .discount-percent-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 600;
    }
    
    /* ================================================================
       CART SUMMARY
       ================================================================ */
    .cart-summary {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 16px;
        border: 2px solid var(--border-color);
    }
    
    .cart-summary .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.9rem;
    }
    
    .cart-summary .summary-row .label {
        color: var(--text-secondary);
    }
    
    .cart-summary .summary-row .value {
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .cart-summary .summary-row.total-row {
        border-top: 2px solid var(--border-color);
        padding-top: 8px;
        margin-top: 4px;
        font-size: 1.05rem;
    }
    
    .cart-summary .summary-row.total-row .value {
        color: var(--success);
        font-weight: 700;
    }
    
    .cart-summary .summary-row.discount-row .value {
        color: var(--gold);
    }
    
    /* ================================================================
       PAYMENT METHODS
       ================================================================ */
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
        background: var(--primary-bg);
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
        background: var(--danger-dark);
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
       MESSAGE BOX
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
    
    /* ================================================================
       ANIMATIONS
       ================================================================ */
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
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
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
        .payment-methods {
            justify-content: center;
        }
        .cart-summary .summary-row {
            flex-direction: row;
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
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2" style="color: var(--otc-color);"></i> New OTC Sale
            </h1>
            <p class="page-subtitle">
                Sell medicines over-the-counter with discount
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-pills mr-1"></i> <?= count($medicines) ?> medicines in stock
                </span>
            </p>
        </div>
        <div>
            <a href="otc_history.php" class="btn btn-outline btn-sm">
                <i class="fas fa-history"></i> OTC History
            </a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
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
    <!-- OTC SALE FORM -->
    <!-- ================================================================ -->
    <div class="sale-form-card animate-fade-in-up">
        <form method="POST" action="" id="otcSaleForm">
            <input type="hidden" name="action" value="complete_sale">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            <input type="hidden" name="discount_percent" id="discountPercentHidden" value="0">
            
            <!-- ================================================================ -->
            <!-- CUSTOMER INFORMATION -->
            <!-- ================================================================ -->
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
            
            <!-- ================================================================ -->
            <!-- ADD MEDICINE SECTION -->
            <!-- ================================================================ -->
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
            
            <!-- ================================================================ -->
            <!-- CART ITEMS -->
            <!-- ================================================================ -->
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
            
            <!-- ================================================================ -->
            <!-- DISCOUNT SECTION - WIDE INPUT -->
            <!-- ================================================================ -->
            <div class="discount-section">
                <div class="discount-row">
                    <span class="discount-label">
                        <i class="fas fa-percent"></i> Discount (%)
                    </span>
                    <div class="discount-input-group">
                        <input type="number" id="discountPercentInput" class="form-control discount-input" 
                               placeholder="0" min="0" max="100" value="0" step="0.5">
                        <span class="percent-sign">%</span>
                        <button type="button" class="btn-apply-discount" onclick="applyDiscount()">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button type="button" class="btn-remove-discount" onclick="removeDiscount()">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                </div>
                
                <!-- Discount Display -->
                <div class="discount-display" id="discountDisplay">
                    <div class="info-item">
                        <span class="label">Subtotal:</span>
                        <span class="value subtotal-value" id="displaySubtotal">TSh 0</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Discount:</span>
                        <span class="value discount-value" id="displayDiscount">TSh 0</span>
                        <span class="discount-percent-label" id="displayDiscountPercent">(0%)</span>
                    </div>
                    <div class="info-item" style="border-color: var(--success); background: var(--success-light);">
                        <span class="label" style="font-weight:700;">Grand Total:</span>
                        <span class="value grand-total" id="displayGrandTotal">TSh 0</span>
                    </div>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- PAYMENT METHOD -->
            <!-- ================================================================ -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="section-title">
                    <i class="fas fa-credit-card"></i>
                    Payment Method
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
            </div>
            
            <!-- ================================================================ -->
            <!-- ACTION BUTTONS -->
            <!-- ================================================================ -->
            <div class="action-buttons">
                <button type="submit" class="btn-complete-sale" id="completeSaleBtn" disabled>
                    <i class="fas fa-check-circle"></i> Complete Sale
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

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
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
    // CART DATA
    // ================================================================
    var cart = [];
    var itemIdCounter = 0;
    var currentDiscountPercent = 0;
    var subtotal = 0;
    var grandTotal = 0;

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
        
        // Check if item already in cart
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
        
        // Update stock display
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
        currentDiscountPercent = 0;
        document.getElementById('discountPercentInput').value = 0;
        document.getElementById('discountPercentHidden').value = 0;
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
    // UPDATE TOTALS (Subtotal, Discount, Grand Total)
    // ================================================================
    function updateTotals() {
        // Calculate subtotal
        subtotal = 0;
        cart.forEach(function(item) {
            subtotal += item.total;
        });
        
        // Calculate discount
        var discountPercent = currentDiscountPercent;
        var discountAmount = (subtotal * discountPercent) / 100;
        grandTotal = subtotal - discountAmount;
        
        // Update display
        document.getElementById('displaySubtotal').textContent = 'TSh ' + subtotal.toLocaleString();
        document.getElementById('displayDiscount').textContent = 'TSh ' + discountAmount.toLocaleString();
        document.getElementById('displayDiscountPercent').textContent = '(' + discountPercent + '%)';
        document.getElementById('displayGrandTotal').textContent = 'TSh ' + grandTotal.toLocaleString();
        
        // Update items_json
        document.getElementById('itemsJson').value = JSON.stringify(cart);
        
        // Update discount hidden
        document.getElementById('discountPercentHidden').value = discountPercent;
    }

    // ================================================================
    // APPLY DISCOUNT
    // ================================================================
    function applyDiscount() {
        var input = document.getElementById('discountPercentInput');
        var discount = parseFloat(input.value) || 0;
        
        if (discount < 0) {
            showToast('Error', 'Discount cannot be negative', 'error');
            return;
        }
        
        if (discount > 100) {
            showToast('Error', 'Discount cannot exceed 100%', 'error');
            return;
        }
        
        if (cart.length === 0) {
            showToast('Error', 'Cart is empty! Add items first.', 'error');
            return;
        }
        
        currentDiscountPercent = discount;
        document.getElementById('discountPercentHidden').value = discount;
        updateTotals();
        
        // Highlight the grand total
        var grandTotalEl = document.getElementById('displayGrandTotal');
        grandTotalEl.parentElement.classList.add('animate-pulse-once');
        
        showToast('Success', discount + '% discount applied!', 'success');
    }

    // ================================================================
    // REMOVE DISCOUNT
    // ================================================================
    function removeDiscount() {
        currentDiscountPercent = 0;
        document.getElementById('discountPercentInput').value = 0;
        document.getElementById('discountPercentHidden').value = 0;
        updateTotals();
        showToast('Info', 'Discount removed', 'info');
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
        if (e.key === 'Enter' && document.activeElement?.id === 'discountPercentInput') {
            applyDiscount();
        }
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💊 Braick - New OTC Sale (With Discount - Wide Input)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Medicines in stock: <?= count($medicines) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Discount input width: 160px (clear and readable)', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>