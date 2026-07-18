<?php
// ================================================================
// FILE: frontend/pages/cashier/print_receipt.php
// CASHIER - PRINT RECEIPT WITH BRAICK LOGO
// AUTO OPENS PRINT DIALOG
// SAVES RECEIPT TO DATABASE (FIXED: payment_id can be NULL)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Cashier
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// GET BILL ID
// ================================================================
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$auto_print = isset($_GET['print']) && $_GET['print'] == 1;

// Initialize variables
$bill = null;
$items = [];
$payment = null;
$settings = [];
$logo_base64 = '';
$logo_available = false;
$error_message = '';
$has_error = false;

try {
    $db = getDB();
    
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
    // GET BILL DETAILS
    // ================================================================
    if ($bill_id > 0) {
        $stmt = $db->prepare("
            SELECT pb.*, p.full_name as patient_name, p.patient_id, p.phone, p.address,
                   u.full_name as cashier_name, b.name as branch_name, b.location as branch_location,
                   v.visit_number, v.visit_type, v.created_at as visit_date
            FROM patient_bills pb
            JOIN patients p ON pb.patient_id = p.id
            JOIN users u ON pb.created_by = u.id
            JOIN branches b ON pb.branch_id = b.id
            LEFT JOIN visits v ON pb.visit_id = v.id
            WHERE pb.id = ? AND pb.branch_id = ?
        ");
        $stmt->execute([$bill_id, $_SESSION['branch_id']]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill) {
            // ================================================================
            // GET BILL ITEMS
            // ================================================================
            $stmt = $db->prepare("
                SELECT * FROM bill_items 
                WHERE bill_id = ? 
                ORDER BY id
            ");
            $stmt->execute([$bill_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ================================================================
            // GET PAYMENT DETAILS (if payment_id is provided and exists)
            // ================================================================
            $payment = null;
            $valid_payment_id = null;
            
            if ($payment_id > 0) {
                $stmt = $db->prepare("
                    SELECT * FROM payments WHERE id = ? AND bill_id = ?
                ");
                $stmt->execute([$payment_id, $bill_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($payment) {
                    $valid_payment_id = $payment_id;
                }
            }
            
            // If payment_id not provided, try to get the latest payment for this bill
            if (!$payment) {
                $stmt = $db->prepare("
                    SELECT id FROM payments WHERE bill_id = ? ORDER BY received_at DESC LIMIT 1
                ");
                $stmt->execute([$bill_id]);
                $latest_payment = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($latest_payment) {
                    $valid_payment_id = $latest_payment['id'];
                    // Get payment details
                    $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
                    $stmt->execute([$valid_payment_id]);
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            
            // ================================================================
            // SAVE RECEIPT TO DATABASE
            // ================================================================
            $receipt_number = 'REC-' . date('Ymd') . '-' . str_pad($bill_id, 6, '0', STR_PAD_LEFT);
            
            // Check if receipt already exists
            $stmt = $db->prepare("SELECT id FROM receipts WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $existing_receipt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_receipt) {
                // Create receipt data as JSON
                $receipt_data = json_encode([
                    'bill_number' => $bill['bill_number'],
                    'patient_name' => $bill['patient_name'],
                    'total_amount' => $bill['total_amount'],
                    'paid_amount' => $bill['paid_amount'] ?? 0,
                    'balance' => $bill['balance'] ?? 0,
                    'items' => $items,
                    'payment_method' => $payment ? $payment['payment_method'] : null,
                    'printed_at' => date('Y-m-d H:i:s')
                ]);
                
                // Insert receipt with payment_id (can be NULL)
                // payment_id is allowed to be NULL in the database
                $stmt = $db->prepare("
                    INSERT INTO receipts (
                        receipt_number, payment_id, bill_id, patient_id, receipt_data, 
                        printed_by, printed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $receipt_number,
                    $valid_payment_id, // Can be NULL if no payment found
                    $bill_id,
                    $bill['patient_id'],
                    $receipt_data,
                    $_SESSION['user_id']
                ]);
                
                $receipt_saved = true;
            } else {
                $receipt_saved = true;
            }
        } else {
            $error_message = 'Bill not found.';
            $has_error = true;
        }
    } else {
        $error_message = 'Invalid bill ID.';
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
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpeg',
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
];

foreach ($logo_paths as $path) {
    if (file_exists($path)) {
        $logo_data = file_get_contents($path);
        $mime_type = mime_content_type($path);
        $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
        break;
    }
}

$logo_available = !empty($logo_base64);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= htmlspecialchars($site_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================
           RESET
           ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
            background: #F8FAFC;
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
        
        .page-header .print-link i {
            margin-right: 6px;
        }
        
        /* ================================================================
           RECEIPT CONTAINER
           ================================================================ */
        .receipt-wrapper {
            max-width: 420px;
            margin: 0 auto;
        }
        
        .receipt {
            background: white;
            padding: 24px 26px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            position: relative;
        }
        
        /* ================================================================
           RECEIPT HEADER WITH LOGO
           ================================================================ */
        .receipt-header {
            text-align: center;
            padding-bottom: 14px;
            border-bottom: 2px dashed #333;
            margin-bottom: 14px;
        }
        
        .receipt-logo {
            display: block;
            margin: 0 auto 10px auto;
            max-width: 120px;
            max-height: 80px;
            object-fit: contain;
        }
        
        .receipt-logo-text {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0B5ED7;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        
        .receipt-logo-text span {
            color: #059669;
        }
        
        .receipt-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1E293B;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .receipt-subtitle {
            font-size: 0.65rem;
            color: #64748B;
            margin-top: 2px;
            line-height: 1.4;
        }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #94A3B8;
            margin: 8px 0;
        }
        
        /* ================================================================
           RECEIPT BODY
           ================================================================ */
        .receipt-body {
            font-size: 0.75rem;
            color: #1E293B;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }
        
        .receipt-row .label {
            color: #64748B;
        }
        
        .receipt-row .value {
            font-weight: 600;
            color: #0F172A;
        }
        
        .receipt-row .value.bold {
            font-weight: 700;
        }
        
        .receipt-items {
            margin: 10px 0;
            border-top: 1px dashed #94A3B8;
            border-bottom: 1px dashed #94A3B8;
            padding: 8px 0;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 0.7rem;
        }
        
        .receipt-item .item-name {
            flex: 1;
        }
        
        .receipt-item .item-price {
            font-weight: 600;
            white-space: nowrap;
            margin-left: 10px;
        }
        
        .receipt-item .item-qty {
            color: #64748B;
            margin-right: 8px;
        }
        
        /* ================================================================
           RECEIPT TOTALS
           ================================================================ */
        .receipt-totals {
            margin: 8px 0;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 0.75rem;
        }
        
        .receipt-total-row .label {
            color: #64748B;
        }
        
        .receipt-total-row .value {
            font-weight: 600;
        }
        
        .receipt-grand-total {
            border-top: 2px solid #333;
            padding-top: 6px;
            margin-top: 4px;
            font-size: 0.9rem;
            font-weight: 700;
        }
        
        .receipt-grand-total .value {
            color: #0B5ED7;
        }
        
        /* ================================================================
           PAYMENT STATUS BADGE
           ================================================================ */
        .payment-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .payment-status.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        .payment-status.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .payment-status.partial {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .payment-status.cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        /* ================================================================
           RECEIPT FOOTER
           ================================================================ */
        .receipt-footer {
            text-align: center;
            font-size: 0.6rem;
            color: #94A3B8;
            padding-top: 12px;
            border-top: 2px dashed #333;
            margin-top: 12px;
        }
        
        .receipt-footer .footer-brand {
            color: #0B5ED7;
            font-weight: 700;
            font-size: 0.7rem;
        }
        
        .receipt-footer .footer-divider {
            margin: 4px 0;
            border: none;
            border-top: 1px dashed #E2E8F0;
        }
        
        /* ================================================================
           ERROR MESSAGE
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
        
        .error-box i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
            color: #DC2626;
        }
        
        .error-box h3 {
            font-size: 1.1rem;
            margin-bottom: 6px;
            color: #991B1B;
        }
        
        .error-box p {
            font-size: 0.85rem;
            color: #7F1D1D;
        }
        
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
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .receipt-wrapper {
                max-width: 100%;
                margin: 0;
            }
            
            .receipt {
                border-radius: 0;
                box-shadow: none;
                padding: 15px 18px;
            }
            
            .page-header {
                display: none !important;
            }
            
            .receipt-logo {
                max-width: 100px;
                max-height: 60px;
            }
            
            .receipt-logo-text {
                font-size: 1.4rem;
            }
            
            .no-print {
                display: none !important;
            }
            
            .error-box {
                display: none !important;
            }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 480px) {
            .receipt {
                padding: 16px 18px;
            }
            
            .receipt-logo-text {
                font-size: 1.3rem;
            }
            
            .page-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .page-header .back-link,
            .page-header .print-link {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - HIDDEN WHEN PRINTING -->
    <!-- ================================================================ -->
    <div class="page-header no-print">
        <a href="paid_bills.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="print-link">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- ERROR MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($has_error || !$bill): ?>
    <div class="error-box">
        <i class="fas fa-exclamation-circle"></i>
        <h3>Error</h3>
        <p><?= htmlspecialchars($error_message ?: 'Bill not found') ?></p>
        <a href="paid_bills.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Paid Bills
        </a>
    </div>
    <?php else: ?>

    <!-- ================================================================ -->
    <!-- RECEIPT -->
    <!-- ================================================================ -->
    <div class="receipt" id="receipt">
        
        <!-- ================================================================ -->
        <!-- RECEIPT HEADER WITH LOGO -->
        <!-- ================================================================ -->
        <div class="receipt-header">
            <?php if ($logo_available): ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Dispensary Logo" class="receipt-logo">
            <?php else: ?>
                <div class="receipt-logo-text">
                    Braick <span>Dispensary</span>
                </div>
            <?php endif; ?>
            
            <div class="receipt-title">Official Receipt</div>
            <div class="receipt-subtitle">
                <?= htmlspecialchars($bill['branch_name'] ?? $site_name) ?>
                <?php if (!empty($bill['branch_location'])): ?>
                    <br><?= htmlspecialchars($bill['branch_location']) ?>
                <?php endif; ?>
            </div>
            <hr class="receipt-divider">
            <div class="receipt-row" style="justify-content:center;gap:12px;font-size:0.6rem;color:#64748B;">
                <span>Tel: <?= htmlspecialchars($site_phone) ?></span>
                <span>Email: <?= htmlspecialchars($site_email) ?></span>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- RECEIPT BODY -->
        <!-- ================================================================ -->
        <div class="receipt-body">
            
            <!-- Bill Info -->
            <div class="receipt-row">
                <span class="label">Receipt #</span>
                <span class="value bold"><?= htmlspecialchars('REC-' . date('Ymd') . '-' . str_pad($bill_id, 6, '0', STR_PAD_LEFT)) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Bill #</span>
                <span class="value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Date</span>
                <span class="value"><?= isset($bill['created_at']) ? date('d/m/Y h:i A', strtotime($bill['created_at'])) : 'N/A' ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="payment-status <?= $bill['status'] ?? 'pending' ?>">
                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            
            <hr class="receipt-divider">
            
            <!-- Patient Info -->
            <div class="receipt-row">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Patient ID</span>
                <span class="value"><?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($bill['phone'])): ?>
            <div class="receipt-row">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($bill['phone']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($bill['visit_number'])): ?>
            <div class="receipt-row">
                <span class="label">Visit #</span>
                <span class="value"><?= htmlspecialchars($bill['visit_number']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($bill['visit_type'])): ?>
            <div class="receipt-row">
                <span class="label">Visit Type</span>
                <span class="value capitalize"><?= htmlspecialchars($bill['visit_type']) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- BILL ITEMS -->
            <!-- ================================================================ -->
            <div class="receipt-items">
                <div class="receipt-row" style="font-weight:700;border-bottom:1px solid #E2E8F0;padding-bottom:4px;margin-bottom:4px;">
                    <span>Item</span>
                    <span>Amount</span>
                </div>
                
                <?php if (!empty($items) && count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <div class="receipt-item">
                            <span class="item-name">
                                <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                <?php if (isset($item['quantity']) && $item['quantity'] > 1): ?>
                                    <span class="item-qty">x<?= $item['quantity'] ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="item-price">
                                <?= $currency ?> <?= number_format($item['total_price'] ?? $item['unit_price'] ?? 0, 0) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="receipt-item">
                        <span class="item-name" style="color:#94A3B8;">No items</span>
                        <span class="item-price" style="color:#94A3B8;"><?= $currency ?> 0</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ================================================================ -->
            <!-- TOTALS -->
            <!-- ================================================================ -->
            <div class="receipt-totals">
                <div class="receipt-total-row">
                    <span class="label">Subtotal</span>
                    <span class="value"><?= $currency ?> <?= number_format($bill['subtotal'] ?? 0, 0) ?></span>
                </div>
                
                <?php if (($bill['discount_amount'] ?? 0) > 0): ?>
                <div class="receipt-total-row">
                    <span class="label">Discount (<?= $bill['discount_percent'] ?? 0 ?>%)</span>
                    <span class="value" style="color:#DC2626;">-<?= $currency ?> <?= number_format($bill['discount_amount'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (($bill['tax_amount'] ?? 0) > 0): ?>
                <div class="receipt-total-row">
                    <span class="label">Tax</span>
                    <span class="value"><?= $currency ?> <?= number_format($bill['tax_amount'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="receipt-total-row receipt-grand-total">
                    <span class="label">Total</span>
                    <span class="value"><?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?></span>
                </div>
                
                <?php if (($bill['amount_paid'] ?? 0) > 0): ?>
                <div class="receipt-total-row" style="border-top:1px dashed #E2E8F0;padding-top:4px;margin-top:4px;">
                    <span class="label">Amount Paid</span>
                    <span class="value" style="color:#059669;"><?= $currency ?> <?= number_format($bill['amount_paid'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (($bill['balance'] ?? 0) > 0): ?>
                <div class="receipt-total-row" style="border-top:1px dashed #E2E8F0;padding-top:4px;">
                    <span class="label">Balance</span>
                    <span class="value" style="color:#DC2626;"><?= $currency ?> <?= number_format($bill['balance'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ================================================================ -->
            <!-- PAYMENT INFO -->
            <!-- ================================================================ -->
            <?php if ($payment): ?>
            <hr class="receipt-divider">
            <div class="receipt-row">
                <span class="label">Payment Method</span>
                <span class="value capitalize"><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($payment['reference_number'])): ?>
            <div class="receipt-row">
                <span class="label">Reference #</span>
                <span class="value"><?= htmlspecialchars($payment['reference_number']) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="label">Received By</span>
                <span class="value"><?= htmlspecialchars($bill['cashier_name'] ?? 'N/A') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Received At</span>
                <span class="value"><?= isset($payment['received_at']) ? date('d/m/Y h:i A', strtotime($payment['received_at'])) : 'N/A' ?></span>
            </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- RECEIPT FOOTER -->
            <!-- ================================================================ -->
            <div class="receipt-footer">
                <div class="footer-brand"><?= htmlspecialchars($site_name) ?></div>
                <hr class="footer-divider">
                <p style="margin:2px 0;">
                    <?= htmlspecialchars($bill['branch_name'] ?? '') ?>
                    <?php if (!empty($bill['branch_location'])): ?>
                        <br><?= htmlspecialchars($bill['branch_location']) ?>
                    <?php endif; ?>
                </p>
                <p style="margin:2px 0;font-size:0.55rem;">
                    Tel: <?= htmlspecialchars($site_phone) ?>
                    | Email: <?= htmlspecialchars($site_email) ?>
                </p>
                <hr class="footer-divider">
                <p style="margin:2px 0;font-size:0.5rem;color:#94A3B8;">
                    <?= date('d/m/Y h:i A') ?>
                </p>
                <p style="margin:2px 0;font-size:0.5rem;color:#94A3B8;">
                    Thank you for choosing <?= htmlspecialchars($site_name) ?>
                </p>
                <p style="margin:2px 0;font-size:0.45rem;color:#CBD5E1;">
                    This is a computer generated receipt
                </p>
            </div>
            
        </div>
        
    </div>
    <?php endif; ?>
    
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO OPEN PRINT DIALOG
    // ================================================================
    (function() {
        var hasError = <?= $has_error ? 'true' : 'false' ?>;
        var autoPrint = <?= $auto_print ? 'true' : 'false' ?>;
        
        if (!hasError && autoPrint) {
            setTimeout(function() {
                window.print();
            }, 800);
        }
    })();

    // ================================================================
    // KEYBOARD SHORTCUT - Ctrl+P works normally
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            // Allow default print behavior
        }
    });

    console.log('%c🧾 Braick - Receipt Print (FIXED - payment_id NULL allowed)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Bill #: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🖨️ Receipt saved to database', 'font-size:13px; color:#34D399;');
    console.log('%c💡 payment_id set to NULL if no payment found', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>