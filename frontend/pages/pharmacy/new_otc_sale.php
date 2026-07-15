<?php
// ================================================================
// FILE: frontend/pages/pharmacy/new_otc_sale.php
// PHARMACY - NEW OTC SALE (FIXED - Uses patient_bills)
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
// GET MEDICINES INVENTORY
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
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    $discount_percent = (float)($_POST['discount_percent'] ?? 0);
    
    // Validation
    $errors = [];
    if (empty($items)) {
        $errors[] = 'Please add at least one medicine';
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['total'];
    }
    
    $discount_amount = ($subtotal * $discount_percent) / 100;
    $grand_total = $subtotal - $discount_amount;
    
    if ($grand_total <= 0) {
        $errors[] = 'Total amount must be greater than zero';
    }
    
    // Check stock for each item
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
            
            // Generate sale number
            $sale_number = 'OTC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insert OTC sale
            $stmt = $db->prepare("
                INSERT INTO otc_sales (
                    sale_number, customer_name, customer_phone, total_amount, discount_amount, net_amount,
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
            
            // Insert sale items and update stock
            foreach ($items as $item) {
                // Insert sale item
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
                
                // Update stock
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity - ? 
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$item['quantity'], $item['inventory_id'], $user_branch_id]);
                
                // Record stock movement
                $stmt = $db->prepare("
                    INSERT INTO stock_movements 
                    (inventory_id, sale_type, sale_id, quantity, movement_type, performed_by, notes)
                    VALUES (?, 'otc', ?, ?, 'out', ?, 'OTC Sale')
                ");
                $stmt->execute([$item['inventory_id'], $sale_id, $item['quantity'], $user_id]);
            }
            
            // ================================================================
            // CREATE BILL - Using patient_bills (NOT bills)
            // ================================================================
            $bill_number = 'BILL-OTC-' . date('Ymd') . '-' . str_pad($sale_id, 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO patient_bills (
                    bill_number, patient_id, visit_id, branch_id, 
                    subtotal, total_amount, paid_amount, balance, status, created_by, created_at
                ) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([
                $bill_number,
                $user_branch_id,
                $subtotal,
                $grand_total,
                $grand_total,
                0,
                $user_id
            ]);
            $bill_id = $db->lastInsertId();
            
            // ================================================================
            // ADD BILL ITEMS - With correct columns
            // ================================================================
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, item_type, item_name, quantity, unit_price, total_price, amount, description, department
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $bill_id,
                    'pharmacy_otc',
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['total'],
                    $item['total'],
                    $item['name'] . ' - OTC Sale',
                    'Pharmacy'
                ]);
            }
            
            $db->commit();
            
            $message = "OTC Sale completed successfully! Sale #: $sale_number";
            $message_type = 'success';
            
            // Redirect to receipt
            echo '<script>
                setTimeout(function() {
                    window.location.href = "print_receipt.php?type=otc&id=' . $sale_id . '";
                }, 1500);
            </script>';
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
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

// ================================================================
// UNREAD NOTIFICATIONS
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
// PROFILE PICTURE
// ================================================================
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
<!-- STYLES (Same as before) -->
<!-- ================================================================ -->
<style>
    .sale-form-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .sale-form-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-row {
        margin-bottom: 14px;
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
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .medicine-select-row .form-group {
        flex: 1;
        min-width: 150px;
    }
    
    .medicine-select-row .form-group.qty-group {
        max-width: 100px;
    }
    
    .medicine-select-row .form-group.price-group {
        max-width: 150px;
    }
    
    .btn-add-medicine {
        background: var(--primary);
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 42px;
    }
    
    .btn-add-medicine:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .cart-item:hover {
        background: var(--primary-bg);
        border-radius: 8px;
    }
    
    .cart-item:last-child {
        border-bottom: none;
    }
    
    .cart-item .item-info .item-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .cart-item .item-info .item-meta {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .cart-item .item-total {
        font-weight: 600;
        color: var(--primary);
        font-size: 0.95rem;
    }
    
    .cart-item .btn-remove {
        background: #EF4444;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }
    
    .cart-item .btn-remove:hover {
        background: #DC2626;
        transform: scale(1.05);
    }
    
    .cart-summary {
        background: var(--bg-body);
        border-radius: 10px;
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
    
    .cart-summary .summary-row.grand-total {
        border-top: 2px solid var(--border-color);
        padding-top: 8px;
        margin-top: 4px;
        font-size: 1.1rem;
    }
    
    .cart-summary .summary-row.grand-total .value {
        color: var(--primary);
        font-weight: 700;
    }
    
    .cart-summary .summary-row.discount .value {
        color: #059669;
    }
    
    .empty-cart {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-cart i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
    .btn-complete-sale {
        background: #059669;
        color: white;
        padding: 10px 30px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.95rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-complete-sale:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-complete-sale:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-clear-cart {
        background: #EF4444;
        color: white;
        padding: 10px 20px;
        border-radius: 10px;
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
        background: #DC2626;
        transform: translateY(-2px);
    }
    
    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .discount-section {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
        padding: 12px 16px;
        background: var(--bg-body);
        border-radius: 10px;
        border: 2px solid var(--border-color);
    }
    
    .discount-section .discount-label {
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    
    .discount-section .form-control {
        max-width: 100px;
        padding: 6px 10px;
        font-size: 0.85rem;
    }
    
    .btn-apply-discount {
        background: #D97706;
        color: white;
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-apply-discount:hover {
        background: #B45309;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    
    .btn-remove-discount {
        background: #EF4444;
        color: white;
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-remove-discount:hover {
        background: #DC2626;
        transform: translateY(-2px);
    }
    
    .payment-methods {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .payment-methods .method-btn {
        padding: 6px 16px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-card);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 0.8rem;
    }
    
    .payment-methods .method-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .payment-methods .method-btn.active {
        border-color: var(--primary);
        background: var(--primary-bg);
        color: var(--primary);
    }
    
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
        .action-buttons .btn-clear-cart {
            width: 100%;
            justify-content: center;
        }
        .cart-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .cart-summary .summary-row {
            flex-direction: row;
        }
        .discount-section {
            flex-direction: column;
            align-items: stretch;
        }
        .discount-section .form-control {
            max-width: 100%;
        }
        .payment-methods {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .payment-methods .method-btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
    }
</style>

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
            <input type="text" id="searchInput" placeholder="Search medicines...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2" style="color: #059669;"></i> New OTC Sale
            </h1>
            <p class="page-subtitle">
                Sell medicines over-the-counter
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
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
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
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Customer Name -->
                <div class="form-row">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" class="form-control" 
                           placeholder="Walk-in Customer" value="Walk-in Customer">
                </div>
                
                <!-- Customer Phone -->
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
                <h4 class="font-semibold text-gray-700 mb-3">
                    <i class="fas fa-pills mr-2 text-blue-600"></i> Add Medicine
                </h4>
                
                <div class="medicine-select-row">
                    <div class="form-group">
                        <label class="form-label">Medicine</label>
                        <select id="medicineSelect" class="form-control">
                            <option value="">Select Medicine</option>
                            <?php foreach ($medicines as $med): ?>
                                <option value="<?= $med['id'] ?>" 
                                        data-price="<?= $med['selling_price'] ?>" 
                                        data-stock="<?= $med['quantity'] ?>"
                                        data-name="<?= htmlspecialchars($med['medication_name']) ?>">
                                    <?= htmlspecialchars($med['medication_name']) ?> 
                                    (Stock: <?= $med['quantity'] ?>) - 
                                    TSh <?= number_format($med['selling_price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group qty-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="medicineQty" class="form-control" value="1" min="1">
                    </div>
                    
                    <div class="form-group price-group">
                        <label class="form-label">Price (TSh)</label>
                        <input type="number" id="medicinePrice" class="form-control" value="0" step="100">
                    </div>
                    
                    <button type="button" onclick="addToCart()" class="btn-add-medicine">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- CART ITEMS -->
            <!-- ================================================================ -->
            <div class="mt-4">
                <h4 class="font-semibold text-gray-700 mb-2">
                    <i class="fas fa-shopping-cart mr-2 text-blue-600"></i> Cart
                    <span class="text-sm font-normal text-gray-400" id="cartCount">(0 items)</span>
                </h4>
                
                <div id="cartItems" class="border rounded-lg overflow-hidden">
                    <div class="empty-cart" id="emptyCart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No items added yet</p>
                        <p class="text-xs text-gray-400 mt-1">Select a medicine and click "Add"</p>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- DISCOUNT SECTION - WITH APPLY BUTTON -->
                <!-- ================================================================ -->
                <div class="discount-section mt-3">
                    <span class="discount-label"><i class="fas fa-percent mr-1"></i> Discount:</span>
                    <input type="number" id="discountPercentInput" class="form-control" placeholder="%" min="0" max="100" value="0">
                    <button type="button" class="btn-apply-discount" onclick="applyDiscount()">
                        <i class="fas fa-check"></i> Apply Discount
                    </button>
                    <button type="button" class="btn-remove-discount" onclick="removeDiscount()">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
                
                <!-- Cart Summary -->
                <div class="cart-summary" id="cartSummary">
                    <div class="summary-row">
                        <span class="label">Subtotal</span>
                        <span class="value" id="subtotalAmount">TSh 0</span>
                    </div>
                    <div class="summary-row discount">
                        <span class="label">Discount</span>
                        <span class="value" id="discountAmount">TSh 0</span>
                    </div>
                    <div class="summary-row grand-total">
                        <span class="label">Grand Total</span>
                        <span class="value" id="grandTotalAmount">TSh 0</span>
                    </div>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- PAYMENT METHOD -->
            <!-- ================================================================ -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <label class="form-label">Payment Method</label>
                <div class="payment-methods">
                    <button type="button" class="method-btn active" data-method="cash" onclick="selectPaymentMethod('cash')">
                        <i class="fas fa-money-bill-wave mr-1"></i> Cash
                    </button>
                    <button type="button" class="method-btn" data-method="m-pesa" onclick="selectPaymentMethod('m-pesa')">
                        <i class="fas fa-mobile-alt mr-1"></i> M-Pesa
                    </button>
                    <button type="button" class="method-btn" data-method="airtel_money" onclick="selectPaymentMethod('airtel_money')">
                        <i class="fas fa-mobile-alt mr-1"></i> Airtel Money
                    </button>
                    <button type="button" class="method-btn" data-method="tigo_pesa" onclick="selectPaymentMethod('tigo_pesa')">
                        <i class="fas fa-mobile-alt mr-1"></i> Tigo Pesa
                    </button>
                    <button type="button" class="method-btn" data-method="halopesa" onclick="selectPaymentMethod('halopesa')">
                        <i class="fas fa-mobile-alt mr-1"></i> Halopesa
                    </button>
                    <button type="button" class="method-btn" data-method="bank" onclick="selectPaymentMethod('bank')">
                        <i class="fas fa-university mr-1"></i> Bank
                    </button>
                    <button type="button" class="method-btn" data-method="card" onclick="selectPaymentMethod('card')">
                        <i class="fas fa-credit-card mr-1"></i> Card
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
                <a href="dashboard.php" class="btn btn-outline" style="padding:10px 24px;">
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
                total: price * qty
            });
        }
        
        // Update stock display
        var newStock = stock - qty;
        option.dataset.stock = newStock;
        option.text = option.text.replace(/\(Stock: \d+\)/, '(Stock: ' + newStock + ')');
        
        renderCart();
        updateCartSummary();
        
        showToast('Success', name + ' added to cart', 'success');
    }

    // ================================================================
    // REMOVE FROM CART
    // ================================================================
    function removeFromCart(id) {
        cart = cart.filter(function(item) { return item.id !== id; });
        renderCart();
        updateCartSummary();
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
        updateCartSummary();
        showToast('Info', 'Cart cleared', 'info');
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
        updateCartSummary();
        showToast('Success', discount + '% discount applied!', 'success');
    }

    // ================================================================
    // REMOVE DISCOUNT
    // ================================================================
    function removeDiscount() {
        currentDiscountPercent = 0;
        document.getElementById('discountPercentInput').value = 0;
        document.getElementById('discountPercentHidden').value = 0;
        updateCartSummary();
        showToast('Info', 'Discount removed', 'info');
    }

    // ================================================================
    // RENDER CART
    // ================================================================
    function renderCart() {
        var container = document.getElementById('cartItems');
        var countEl = document.getElementById('cartCount');
        var btn = document.getElementById('completeSaleBtn');
        
        countEl.textContent = '(' + cart.length + ' items)';
        
        if (cart.length === 0) {
            container.innerHTML = `
                <div class="empty-cart" id="emptyCart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No items added yet</p>
                    <p class="text-xs text-gray-400 mt-1">Select a medicine and click "Add"</p>
                </div>
            `;
            btn.disabled = true;
            return;
        }
        
        var html = '<div class="divide-y">';
        cart.forEach(function(item) {
            html += `
                <div class="cart-item">
                    <div class="item-info">
                        <div class="item-name">${item.name}</div>
                        <div class="item-meta">
                            Qty: ${item.quantity} × TSh ${item.price.toLocaleString()}
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="item-total">TSh ${item.total.toLocaleString()}</span>
                        <button class="btn-remove" onclick="removeFromCart(${item.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
        btn.disabled = false;
    }

    // ================================================================
    // UPDATE CART SUMMARY
    // ================================================================
    function updateCartSummary() {
        var subtotal = 0;
        cart.forEach(function(item) {
            subtotal += item.total;
        });
        
        var discountPercent = currentDiscountPercent;
        var discountAmount = (subtotal * discountPercent) / 100;
        var grandTotal = subtotal - discountAmount;
        
        document.getElementById('subtotalAmount').textContent = 'TSh ' + subtotal.toLocaleString();
        document.getElementById('discountAmount').textContent = 'TSh ' + discountAmount.toLocaleString();
        document.getElementById('grandTotalAmount').textContent = 'TSh ' + grandTotal.toLocaleString();
        
        // Update items_json hidden input
        document.getElementById('itemsJson').value = JSON.stringify(cart);
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
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Enter') {
            var active = document.activeElement;
            if (active && active.id === 'discountPercentInput') {
                applyDiscount();
            }
        }
    });

    console.log('%c💊 Braick - New OTC Sale (FIXED)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Medicines in stock: <?= count($medicines) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Using patient_bills table (NOT bills)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Foreign key constraint fixed', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>