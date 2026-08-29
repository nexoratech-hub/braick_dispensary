<?php
// ================================================================
// FILE: frontend/pages/pharmacy/print_otc_receipt.php
// PHARMACY - PRINT OTC RECEIPT WITH MEDICATION INSTRUCTIONS
// OTC SALE RECEIPT
// FIXED: Uses NULL for patient_id (not 0)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$auto_print = isset($_GET['print']) && $_GET['print'] == 1;

$sale = null;
$items = [];
$settings = [];
$logo_base64 = '';
$logo_available = false;
$error_message = '';
$has_error = false;
$currency = 'TSh';

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $site_name = $settings['site_name'] ?? 'Braick Dispensary';
    $currency = $settings['currency'] ?? 'TSh';
    $site_phone = $settings['phone'] ?? '+255 700 000 000';
    $site_email = $settings['email'] ?? 'info@braick.com';
    
    // ================================================================
    // GET ADMIN CONTACT NUMBERS
    // ================================================================
    $admin_phones = [];
    try {
        $stmt = $db->prepare("
            SELECT phone FROM users 
            WHERE role = 'admin' AND branch_id = ? AND status = 'active'
            ORDER BY id ASC
        ");
        $stmt->execute([$user_branch_id]);
        $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $admin_phones = [];
    }
    
    // ================================================================
    // GET BRANCH PHONE
    // ================================================================
    $branch_phone = '';
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$user_branch_id]);
        $branch_phone = $stmt->fetchColumn();
    } catch (Exception $e) {
        $branch_phone = '';
    }
    
    // ================================================================
    // GET OTC SALE DETAILS
    // ================================================================
    if ($sale_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                os.*,
                u.full_name as sold_by_name,
                u.phone as sold_by_phone,
                b.name as branch_name,
                b.phone as branch_phone,
                b.location as branch_location,
                p.full_name as patient_full_name,
                p.patient_id as patient_code,
                p.phone as patient_phone,
                p.address as patient_address
            FROM otc_sales os
            LEFT JOIN users u ON os.sold_by = u.id
            LEFT JOIN branches b ON os.branch_id = b.id
            LEFT JOIN patients p ON os.patient_id = p.id
            WHERE os.id = ? AND os.branch_id = ?
        ");
        $stmt->execute([$sale_id, $user_branch_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sale) {
            // ================================================================
            // GET OTC SALE ITEMS WITH INSTRUCTIONS
            // ================================================================
            $stmt = $db->prepare("
                SELECT 
                    osi.*,
                    mi.medication_name as inventory_medication_name,
                    mi.batch_number,
                    mi.unit
                FROM otc_sale_items osi
                LEFT JOIN medications_inventory mi ON osi.inventory_id = mi.id
                WHERE osi.sale_id = ?
                ORDER BY osi.id ASC
            ");
            $stmt->execute([$sale_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ================================================================
            // GET BILL ID FROM OTC SALE
            // ================================================================
            $bill_id = !empty($sale['bill_id']) ? (int)$sale['bill_id'] : null;
            $payment_id = null;
            
            // If bill_id exists, get payment_id from payments table
            if ($bill_id) {
                $stmt = $db->prepare("
                    SELECT id FROM payments WHERE bill_id = ? ORDER BY received_at DESC LIMIT 1
                ");
                $stmt->execute([$bill_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($payment) {
                    $payment_id = $payment['id'];
                }
            }
            
            // ================================================================
            // SAVE RECEIPT TO DATABASE - FIXED: Use NULL for patient_id
            // ================================================================
            $receipt_number = 'OTC-REC-' . date('Ymd') . '-' . str_pad($sale_id, 6, '0', STR_PAD_LEFT);
            
            // Get patient_id (use NULL if no patient)
            $patient_id = !empty($sale['patient_id']) ? (int)$sale['patient_id'] : null;
            
            // Check if receipt already exists
            $stmt = $db->prepare("SELECT id FROM receipts WHERE receipt_number = ?");
            $stmt->execute([$receipt_number]);
            $existing_receipt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_receipt) {
                $receipt_data = json_encode([
                    'sale_number' => $sale['sale_number'],
                    'customer_name' => $sale['customer_name'],
                    'customer_phone' => $sale['customer_phone'],
                    'total_amount' => $sale['total_amount'],
                    'subtotal' => $sale['subtotal'],
                    'discount_amount' => $sale['discount_amount'],
                    'payment_method' => $sale['payment_method'],
                    'payment_status' => $sale['payment_status'],
                    'items' => $items,
                    'printed_at' => date('Y-m-d H:i:s')
                ]);
                
                // Insert with patient_id as NULL
                $stmt = $db->prepare("
                    INSERT INTO receipts (
                        receipt_number, 
                        bill_id, 
                        patient_id, 
                        payment_id,
                        branch_id, 
                        receipt_data, 
                        printed_by, 
                        printed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $receipt_number,
                    $bill_id,
                    $patient_id,  // NULL if no patient
                    $payment_id,
                    $user_branch_id,
                    $receipt_data,
                    $user_id
                ]);
            }
        } else {
            $error_message = 'OTC sale not found.';
            $has_error = true;
        }
    } else {
        $error_message = 'Invalid sale ID.';
        $has_error = true;
    }
    
} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $has_error = true;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
];

foreach ($logo_paths as $path) {
    if (file_exists($path)) {
        $logo_data = file_get_contents($path);
        $mime_type = mime_content_type($path);
        $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
        $logo_available = true;
        break;
    }
}

// Admin contacts for display
$admin_phones_display = !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC Receipt - <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px;
        }
        
        .receipt-wrapper {
            max-width: 420px;
            margin: 0 auto;
        }
        
        .page-header {
            max-width: 420px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px;
        }
        
        .page-header .back-link {
            color: #64748B;
            text-decoration: none;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid #E2E8F0;
            transition: all 0.3s ease;
            background: white;
        }
        
        .page-header .back-link:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
        }
        
        .page-header .print-link {
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 8px;
            background: #0B5ED7;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .page-header .print-link:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .receipt {
            background: white;
            padding: 20px 22px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 2px dashed #333;
            margin-bottom: 12px;
        }
        
        .receipt-logo {
            display: block;
            margin: 0 auto 8px auto;
            max-width: 100px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .receipt-logo-text {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0B5ED7;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        
        .receipt-logo-text span {
            color: #7C3AED;
        }
        
        .receipt-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1E293B;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .receipt-subtitle {
            font-size: 0.6rem;
            color: #64748B;
            margin-top: 2px;
            line-height: 1.4;
        }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #94A3B8;
            margin: 6px 0;
        }
        
        .receipt-body {
            font-size: 0.7rem;
            color: #1E293B;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }
        
        .receipt-row .label {
            color: #64748B;
        }
        
        .receipt-row .value {
            font-weight: 600;
            color: #0F172A;
        }
        
        .receipt-row .value.bold { font-weight: 700; }
        
        /* ================================================================
           SECTION HEADERS
           ================================================================ */
        .section-header {
            font-weight: 700;
            font-size: 0.7rem;
            padding: 4px 0;
            margin: 6px 0 4px 0;
            border-bottom: 2px solid;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header.otc {
            color: #7C3AED;
            border-bottom-color: #7C3AED;
        }
        
        .section-header .section-total {
            font-size: 0.7rem;
        }
        
        /* ================================================================
           ITEMS
           ================================================================ */
        .receipt-items {
            margin: 4px 0;
            padding: 4px 0;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 0.65rem;
            border-bottom: 1px dotted #E2E8F0;
        }
        
        .receipt-item:last-child {
            border-bottom: none;
        }
        
        .receipt-item .item-name {
            flex: 1;
        }
        
        .receipt-item .item-price {
            font-weight: 600;
            white-space: nowrap;
            margin-left: 8px;
        }
        
        .receipt-item .item-qty {
            color: #64748B;
            margin-right: 6px;
            font-size: 0.6rem;
        }
        
        .receipt-item .item-instruction {
            font-size: 0.55rem;
            color: #64748B;
            font-style: italic;
            display: block;
            padding-left: 4px;
            border-left: 2px solid #7C3AED;
            margin-top: 2px;
            background: #EDE9FE;
            padding: 2px 8px;
            border-radius: 3px;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        
        .receipt-item .item-batch {
            font-size: 0.5rem;
            color: #94A3B8;
            display: block;
        }
        
        /* ================================================================
           TOTALS
           ================================================================ */
        .receipt-totals {
            margin: 6px 0;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 0.7rem;
        }
        
        .receipt-total-row .label {
            color: #64748B;
        }
        
        .receipt-total-row .value {
            font-weight: 600;
        }
        
        .receipt-grand-total {
            border-top: 2px solid #333;
            padding-top: 4px;
            margin-top: 4px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        
        .receipt-grand-total .value {
            color: #7C3AED;
        }
        
        .discount-value {
            color: #DC2626;
        }
        
        .discount-value .currency {
            color: #DC2626;
        }
        
        /* ================================================================
           PAYMENT STATUS
           ================================================================ */
        .payment-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .payment-status.paid { background: #D1FAE5; color: #059669; }
        .payment-status.pending { background: #FEF3C7; color: #D97706; }
        .payment-status.partial { background: #FEF3C7; color: #D97706; }
        .payment-status.cancelled { background: #FEE2E2; color: #DC2626; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .receipt-footer {
            text-align: center;
            font-size: 0.55rem;
            color: #94A3B8;
            padding-top: 10px;
            border-top: 2px dashed #333;
            margin-top: 10px;
        }
        
        .receipt-footer .footer-brand {
            color: #7C3AED;
            font-weight: 700;
            font-size: 0.65rem;
        }
        
        .receipt-footer .footer-divider {
            margin: 3px 0;
            border: none;
            border-top: 1px dashed #E2E8F0;
        }
        
        /* ================================================================
           ERROR
           ================================================================ */
        .error-box {
            max-width: 420px;
            margin: 0 auto;
            background: #FEF2F2;
            border: 2px solid #FCA5A5;
            border-radius: 12px;
            padding: 24px 28px;
            text-align: center;
            color: #991B1B;
        }
        
        .error-box i { font-size: 2.5rem; display: block; margin-bottom: 10px; color: #DC2626; }
        .error-box h3 { font-size: 1.1rem; margin-bottom: 6px; color: #991B1B; }
        .error-box p { font-size: 0.85rem; color: #7F1D1D; }
        
        .error-box .back-btn {
            display: inline-block;
            margin-top: 14px;
            padding: 10px 24px;
            background: #DC2626;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        
        .error-box .back-btn:hover {
            background: #B91C1C;
            transform: translateY(-2px);
        }
        
        .error-box .back-btn i {
            font-size: 0.85rem;
            display: inline;
            margin-bottom: 0;
            color: white;
        }
        
        /* ================================================================
           ADMIN CONTACT LINE
           ================================================================ */
        .admin-contact-line {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 0.5rem;
            color: #94A3B8;
            margin-top: 2px;
            padding-top: 4px;
            border-top: 1px dashed #E2E8F0;
        }
        
        .admin-contact-line span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .admin-contact-line i {
            color: #7C3AED;
        }
        
        /* ================================================================
           PRINT
           ================================================================ */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .receipt-wrapper { max-width: 100%; margin: 0; }
            .receipt { border-radius: 0; box-shadow: none; padding: 12px 16px; }
            .page-header { display: none !important; }
            .receipt-logo { max-width: 80px; max-height: 50px; }
            .receipt-logo-text { font-size: 1.2rem; }
            .no-print { display: none !important; }
            .error-box { display: none !important; }
            .receipt-item .item-instruction {
                background: #EDE9FE !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .payment-status {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .admin-contact-line {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        @media (max-width: 480px) {
            .receipt { padding: 14px 16px; }
            .receipt-logo-text { font-size: 1.2rem; }
            .page-header { flex-wrap: wrap; gap: 8px; }
            .page-header .back-link,
            .page-header .print-link { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header no-print">
        <a href="otc_history.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="print-link">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <!-- ERROR -->
    <?php if ($has_error || !$sale): ?>
    <div class="error-box">
        <i class="fas fa-exclamation-circle"></i>
        <h3>Error</h3>
        <p><?= htmlspecialchars($error_message ?: 'OTC sale not found') ?></p>
        <a href="otc_history.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to OTC History
        </a>
    </div>
    <?php else: ?>

    <!-- ================================================================ -->
    <!-- RECEIPT -->
    <!-- ================================================================ -->
    <div class="receipt" id="receipt">
        
        <!-- HEADER -->
        <div class="receipt-header">
            <?php if ($logo_available): ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Logo" class="receipt-logo">
            <?php else: ?>
                <div class="receipt-logo-text">
                    Braick <span>Dispensary</span>
                </div>
            <?php endif; ?>
            
            <div class="receipt-title">OTC Sale Receipt</div>
            <div class="receipt-subtitle">
                <?= htmlspecialchars($sale['branch_name'] ?? $site_name) ?>
                <?php if (!empty($sale['branch_location'])): ?>
                    <br><?= htmlspecialchars($sale['branch_location']) ?>
                <?php endif; ?>
            </div>
            <hr class="receipt-divider">
            <div class="receipt-row" style="justify-content:center;gap:10px;font-size:0.55rem;color:#64748B;">
                <span>Tel: <?= htmlspecialchars($site_phone) ?></span>
                <span>Email: <?= htmlspecialchars($site_email) ?></span>
            </div>
            <div class="admin-contact-line">
                <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
            </div>
        </div>
        
        <!-- BODY -->
        <div class="receipt-body">
            
            <!-- Sale Info -->
            <div class="receipt-row">
                <span class="label">Receipt #</span>
                <span class="value bold"><?= htmlspecialchars('OTC-REC-' . date('Ymd') . '-' . str_pad($sale_id, 6, '0', STR_PAD_LEFT)) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Sale #</span>
                <span class="value"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Date</span>
                <span class="value"><?= isset($sale['created_at']) ? date('d/m/Y h:i A', strtotime($sale['created_at'])) : 'N/A' ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="payment-status <?= $sale['payment_status'] ?? 'pending' ?>">
                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            
            <hr class="receipt-divider">
            
            <!-- Customer Info -->
            <div class="receipt-row">
                <span class="label">Customer</span>
                <span class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
            </div>
            <?php if (!empty($sale['customer_phone'])): ?>
            <div class="receipt-row">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($sale['customer_phone']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($sale['patient_full_name'])): ?>
            <div class="receipt-row">
                <span class="label">Patient (Registered)</span>
                <span class="value"><?= htmlspecialchars($sale['patient_full_name']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Patient ID</span>
                <span class="value"><?= htmlspecialchars($sale['patient_code'] ?? 'N/A') ?></span>
            </div>
            <?php endif; ?>
            
            <hr class="receipt-divider">
            
            <!-- ================================================================ -->
            <!-- OTC ITEMS SECTION - WITH INSTRUCTIONS -->
            <!-- ================================================================ -->
            <?php if (count($items) > 0): ?>
                <div class="section-header otc">
                    <span><i class="fas fa-shopping-cart"></i> OTC Items</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($sale['total_amount'] ?? 0, 0) ?></span>
                </div>
                <div class="receipt-items">
                    <?php foreach ($items as $item): 
                        $item_name = $item['item_name'] ?? $item['medicine_name'] ?? $item['inventory_medication_name'] ?? 'N/A';
                        $instructions = $item['instructions'] ?? '';
                        $batch_number = $item['batch_number'] ?? '';
                        $quantity = $item['quantity'] ?? 0;
                        $unit_price = $item['unit_price'] ?? 0;
                        $total_price = $item['total_price'] ?? 0;
                    ?>
                        <div class="receipt-item">
                            <span class="item-name">
                                <?= htmlspecialchars($item_name) ?>
                                <?php if ($quantity > 1): ?>
                                    <span class="item-qty">x<?= $quantity ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($batch_number)): ?>
                                    <span class="item-batch">
                                        <i class="fas fa-barcode"></i> Batch: <?= htmlspecialchars($batch_number) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($instructions)): ?>
                                    <span class="item-instruction">
                                        <i class="fas fa-info-circle" style="font-size:0.45rem;"></i>
                                        <?= nl2br(htmlspecialchars($instructions)) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="item-price">
                                <?= $currency ?> <?= number_format($total_price, 0) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:10px 0;color:#94A3B8;font-size:0.65rem;">
                    <i class="fas fa-box-open"></i> No items found
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- TOTALS -->
            <!-- ================================================================ -->
            <div class="receipt-totals">
                <div class="receipt-total-row">
                    <span class="label">Subtotal</span>
                    <span class="value"><?= $currency ?> <?= number_format($sale['subtotal'] ?? 0, 0) ?></span>
                </div>
                
                <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                <div class="receipt-total-row">
                    <span class="label"><i class="fas fa-tag"></i> Discount</span>
                    <span class="value discount-value">-<?= $currency ?> <?= number_format($sale['discount_amount'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="receipt-total-row receipt-grand-total">
                    <span class="label">Total</span>
                    <span class="value"><?= $currency ?> <?= number_format($sale['total_amount'] ?? 0, 0) ?></span>
                </div>
            </div>
            
            <!-- PAYMENT INFO -->
            <hr class="receipt-divider">
            <div class="receipt-row">
                <span class="label">Payment Method</span>
                <span class="value capitalize"><?= htmlspecialchars($sale['payment_method'] ?? 'Cash') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Sold By</span>
                <span class="value"><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($sale['sold_by_phone'])): ?>
            <div class="receipt-row">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($sale['sold_by_phone']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($sale['notes'])): ?>
            <div class="receipt-row" style="border-top:1px dashed #E2E8F0;padding-top:4px;margin-top:4px;">
                <span class="label">Notes</span>
                <span class="value" style="font-weight:400;font-size:0.65rem;"><?= nl2br(htmlspecialchars($sale['notes'])) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- FOOTER -->
            <div class="receipt-footer">
                <div class="footer-brand"><?= htmlspecialchars($site_name) ?></div>
                <hr class="footer-divider">
                <p style="margin:2px 0;">
                    <?= htmlspecialchars($sale['branch_name'] ?? '') ?>
                    <?php if (!empty($sale['branch_location'])): ?>
                        <br><?= htmlspecialchars($sale['branch_location']) ?>
                    <?php endif; ?>
                </p>
                <p style="margin:2px 0;font-size:0.5rem;">
                    Tel: <?= htmlspecialchars($site_phone) ?> | Email: <?= htmlspecialchars($site_email) ?>
                </p>
                <p style="margin:2px 0;font-size:0.45rem;color:#94A3B8;">
                    Admin Contacts: <?= htmlspecialchars($admin_phones_display) ?>
                </p>
                <hr class="footer-divider">
                <p style="margin:2px 0;font-size:0.45rem;color:#94A3B8;">
                    <?= date('d/m/Y h:i A') ?>
                </p>
                <p style="margin:2px 0;font-size:0.45rem;color:#94A3B8;">
                    Thank you for choosing <?= htmlspecialchars($site_name) ?>
                </p>
                <p style="margin:2px 0;font-size:0.4rem;color:#CBD5E1;">
                    This is a computer generated receipt
                </p>
            </div>
            
        </div>
        
    </div>
    <?php endif; ?>
    
</div>

<script>
    (function() {
        var hasError = <?= $has_error ? 'true' : 'false' ?>;
        var autoPrint = <?= $auto_print ? 'true' : 'false' ?>;
        if (!hasError && autoPrint) {
            setTimeout(function() { window.print(); }, 800);
        }
    })();

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            // Allow default print behavior
        }
    });

    console.log('%c🧾 Braick - Print OTC Receipt', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ OTC Sale Receipt with instructions', 'font-size:13px; color:#34D399;');
    console.log('%c✅ patient_id set to NULL (not 0)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Items show: name, quantity, batch, instructions', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($sale['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📞 Admin: <?= htmlspecialchars($admin_phones_display) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>