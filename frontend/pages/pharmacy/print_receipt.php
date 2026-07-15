<?php
// ================================================================
// FILE: frontend/pages/pharmacy/print_receipt.php
// PHARMACY - PRINT RECEIPT (FIXED SIZE - 80mm)
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
// GET PARAMETERS
// ================================================================
$type = isset($_GET['type']) ? $_GET['type'] : 'otc';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid receipt ID");
}

// ================================================================
// GET RECEIPT DATA
// ================================================================
$receipt = null;
$items = [];
$customer_name = '';
$customer_phone = '';
$sale_number = '';
$total_amount = 0;
$discount_amount = 0;
$net_amount = 0;
$payment_method = '';
$sold_by = '';
$created_at = '';
$sale_type = '';

if ($type === 'otc') {
    // Get OTC sale
    $stmt = $db->prepare("
        SELECT os.*, u.full_name as cashier_name
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.id = ? AND os.branch_id = ?
    ");
    $stmt->execute([$id, $user_branch_id]);
    $receipt = $stmt->fetch();
    
    if ($receipt) {
        // Get OTC items
        $stmt = $db->prepare("
            SELECT * FROM otc_sale_items WHERE sale_id = ?
        ");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
        
        $customer_name = $receipt['customer_name'] ?? 'Walk-in Customer';
        $customer_phone = $receipt['customer_phone'] ?? '';
        $sale_number = $receipt['sale_number'] ?? '';
        $total_amount = $receipt['total_amount'] ?? 0;
        $discount_amount = $receipt['discount_amount'] ?? 0;
        $net_amount = $receipt['net_amount'] ?? 0;
        $payment_method = $receipt['payment_method'] ?? 'cash';
        $sold_by = $receipt['cashier_name'] ?? 'Unknown';
        $created_at = $receipt['created_at'] ?? date('Y-m-d H:i:s');
        $sale_type = 'OTC Sale';
    }
} elseif ($type === 'prescription') {
    // Get Prescription sale
    $stmt = $db->prepare("
        SELECT ps.*, 
               p.full_name as patient_name, p.patient_id, p.phone,
               u.full_name as doctor_name,
               u2.full_name as cashier_name
        FROM prescription_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        LEFT JOIN users u ON ps.doctor_id = u.id
        LEFT JOIN users u2 ON ps.dispensed_by = u2.id
        WHERE ps.id = ? AND ps.branch_id = ?
    ");
    $stmt->execute([$id, $user_branch_id]);
    $receipt = $stmt->fetch();
    
    if ($receipt) {
        // Get prescription items
        $stmt = $db->prepare("
            SELECT * FROM prescription_sale_items WHERE sale_id = ?
        ");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
        
        $customer_name = $receipt['patient_name'] ?? 'Unknown Patient';
        $customer_phone = $receipt['phone'] ?? '';
        $sale_number = $receipt['sale_number'] ?? '';
        $total_amount = $receipt['total_amount'] ?? 0;
        $discount_amount = $receipt['discount_amount'] ?? 0;
        $net_amount = $receipt['net_amount'] ?? 0;
        $payment_method = $receipt['payment_method'] ?? 'cash';
        $sold_by = $receipt['cashier_name'] ?? $user_full_name;
        $created_at = $receipt['created_at'] ?? date('Y-m-d H:i:s');
        $sale_type = 'Prescription Dispensed';
    }
}

if (!$receipt) {
    die("Receipt not found");
}

// ================================================================
// GET BRANCH DETAILS
// ================================================================
$branch = null;
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$user_branch_id]);
$branch = $stmt->fetch();

// ================================================================
// CALCULATE TOTALS
// ================================================================
$subtotal = $total_amount;
$grand_total = $net_amount;
$tax = 0;

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= $sale_number ?></title>
    
    <style>
        /* ================================================================
           RECEIPT STYLES - FIXED 80mm SIZE
           ================================================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        /* ================================================================
           RECEIPT CONTAINER - FIXED 80mm WIDTH
           ================================================================ */
        .receipt-wrapper {
            background: white;
            width: 80mm;           /* FIXED 80mm - Standard receipt width */
            min-width: 80mm;
            max-width: 80mm;
            padding: 4mm 5mm;      /* Padding ndogo kwa receipt */
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 4px;
            margin: 0 auto;
        }
        
        /* ================================================================
           RECEIPT HEADER
           ================================================================ */
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        
        .receipt-header .logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 4px;
            background: white;
            padding: 3px;
            border: 2px solid #065F46;
        }
        
        .receipt-header .clinic-name {
            font-size: 14px;
            font-weight: 700;
            color: #065F46;
            letter-spacing: 1px;
        }
        
        .receipt-header .clinic-sub {
            font-size: 8px;
            color: #64748B;
            letter-spacing: 2px;
        }
        
        .receipt-header .clinic-details {
            font-size: 7px;
            color: #64748B;
            margin-top: 2px;
            line-height: 1.3;
        }
        
        .receipt-header .receipt-title {
            font-size: 10px;
            font-weight: 700;
            color: #0B5ED7;
            margin-top: 4px;
            letter-spacing: 2px;
        }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 4px 0;
        }
        
        .receipt-divider-double {
            border: none;
            border-top: 2px dashed #333;
            margin: 4px 0;
        }
        
        /* ================================================================
           INFO ROWS
           ================================================================ */
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            padding: 1px 0;
            color: #333;
        }
        
        .info-row .label {
            color: #64748B;
        }
        
        .info-row .value {
            font-weight: 500;
            text-align: right;
        }
        
        /* ================================================================
           ITEMS TABLE
           ================================================================ */
        .items-table {
            width: 100%;
            font-size: 8px;
            border-collapse: collapse;
            margin: 4px 0;
        }
        
        .items-table th {
            text-align: left;
            font-size: 7px;
            text-transform: uppercase;
            color: #64748B;
            border-bottom: 1px solid #ccc;
            padding: 2px 0;
            letter-spacing: 0.5px;
        }
        
        .items-table th.text-right {
            text-align: right;
        }
        
        .items-table td {
            padding: 2px 0;
            border-bottom: 1px solid #eee;
            font-size: 8px;
        }
        
        .items-table td.text-right {
            text-align: right;
        }
        
        .items-table .item-name {
            max-width: 80px;
            word-wrap: break-word;
        }
        
        /* ================================================================
           TOTALS
           ================================================================ */
        .totals {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #ccc;
        }
        
        .totals .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            padding: 1px 0;
        }
        
        .totals .total-row .label {
            color: #64748B;
        }
        
        .totals .total-row .value {
            font-weight: 600;
        }
        
        .totals .grand-total {
            border-top: 2px solid #333;
            padding-top: 4px;
            margin-top: 2px;
            font-size: 10px;
            font-weight: 700;
            color: #065F46;
        }
        
        /* ================================================================
           RECEIPT FOOTER
           ================================================================ */
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #333;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 7px;
            color: #64748B;
        }
        
        .receipt-footer .thank-you {
            font-size: 10px;
            font-weight: 600;
            color: #065F46;
            margin-bottom: 2px;
        }
        
        .receipt-footer .small {
            font-size: 6px;
            color: #94A3B8;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .payment-method-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: 600;
            text-transform: uppercase;
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .payment-method-badge.cash { background: #E8F0FE; color: #0B5ED7; }
        .payment-method-badge.m-pesa { background: #D1FAE5; color: #059669; }
        .payment-method-badge.airtel_money { background: #FEF3C7; color: #D97706; }
        .payment-method-badge.tigo_pesa { background: #F3E8FF; color: #7C3AED; }
        .payment-method-badge.halopesa { background: #FCE4EC; color: #DB2777; }
        .payment-method-badge.bank { background: #E0F2FE; color: #0284C7; }
        .payment-method-badge.card { background: #F1F5F9; color: #475569; }
        
        .sale-type-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: 600;
            text-transform: uppercase;
            background: #0B5ED7;
            color: white;
        }
        
        .sale-type-badge.otc {
            background: #059669;
        }
        
        .sale-type-badge.prescription {
            background: #0B5ED7;
        }
        
        /* ================================================================
           BUTTONS (Not printed)
           ================================================================ */
        .no-print {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid #eee;
        }
        
        .print-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: #0B5ED7;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .print-btn i {
            margin-right: 6px;
        }
        
        .back-btn {
            display: block;
            width: 100%;
            padding: 8px;
            margin-top: 6px;
            background: transparent;
            color: #64748B;
            border: 2px solid #E2E8F0;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .back-btn:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
        }
        
        /* ================================================================
           PRINT STYLES - KEEP 80mm SIZE
           ================================================================ */
        @media print {
            /* Reset body for print */
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
                display: block !important;
            }
            
            /* Receipt wrapper - KEEP 80mm WIDTH */
            .receipt-wrapper {
                width: 80mm !important;
                min-width: 80mm !important;
                max-width: 80mm !important;
                padding: 3mm 4mm !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 auto !important;
                background: white !important;
                border: none !important;
            }
            
            /* Hide buttons when printing */
            .no-print {
                display: none !important;
            }
            
            /* Keep colors for print */
            .receipt-header .logo {
                border: 2px solid #065F46 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .payment-method-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .sale-type-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            /* Force page to be receipt size */
            @page {
                size: 80mm auto;
                margin: 0;
            }
            
            /* Prevent scaling */
            html, body {
                zoom: 1 !important;
                -webkit-print-size-adjust: 100% !important;
                print-size-adjust: 100% !important;
            }
        }
        
        /* ================================================================
           RESPONSIVE - Screen view
           ================================================================ */
        @media screen and (max-width: 480px) {
            .receipt-wrapper {
                padding: 3mm 4mm;
            }
            .receipt-header .clinic-name {
                font-size: 12px;
            }
            .items-table {
                font-size: 7px;
            }
            .info-row {
                font-size: 7px;
            }
            .totals .total-row {
                font-size: 7px;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- RECEIPT - FIXED 80mm WIDTH -->
<!-- ================================================================ -->
<div class="receipt-wrapper" id="receipt">
    
    <!-- ================================================================ -->
    <!-- RECEIPT HEADER -->
    <!-- ================================================================ -->
    <div class="receipt-header">
        <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2245%22 height=%2245%22%3E%3Crect width=%2245%22 height=%2245%22 fill=%22%23065F46%22 rx=%2250%25%22/%3E%3Ctext x=%2222%22 y=%2230%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
        
        <div class="clinic-name">BRAICK DISPENSARY</div>
        <div class="clinic-sub">Quality Healthcare Services</div>
        <div class="clinic-details">
            <?= htmlspecialchars($branch['location'] ?? 'Dodoma, Tanzania') ?><br>
            Tel: <?= htmlspecialchars($branch['phone'] ?? '+255 759 154 160') ?>
        </div>
        <div class="receipt-title">
            <span class="sale-type-badge <?= $type ?>"><?= $sale_type ?></span>
            <br>
            <?= $sale_number ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPT INFO -->
    <!-- ================================================================ -->
    <div class="info-row">
        <span class="label">Date</span>
        <span class="value"><?= date('d/m/Y h:i A', strtotime($created_at)) ?></span>
    </div>
    <div class="info-row">
        <span class="label">Customer</span>
        <span class="value"><?= htmlspecialchars($customer_name) ?></span>
    </div>
    <?php if (!empty($customer_phone)): ?>
    <div class="info-row">
        <span class="label">Phone</span>
        <span class="value"><?= htmlspecialchars($customer_phone) ?></span>
    </div>
    <?php endif; ?>
    <div class="info-row">
        <span class="label">Cashier</span>
        <span class="value"><?= htmlspecialchars($sold_by) ?></span>
    </div>
    <div class="info-row">
        <span class="label">Payment</span>
        <span class="value">
            <span class="payment-method-badge <?= str_replace('_', '-', $payment_method) ?>">
                <?= ucfirst(str_replace('_', ' ', $payment_method)) ?>
            </span>
        </span>
    </div>

    <hr class="receipt-divider">

    <!-- ================================================================ -->
    <!-- ITEMS TABLE -->
    <!-- ================================================================ -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="item-name"><?= htmlspecialchars($item['medicine_name'] ?? $item['item_name'] ?? 'N/A') ?></td>
                <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                <td class="text-right"><?= number_format($item['unit_price'] ?? 0) ?></td>
                <td class="text-right"><?= number_format($item['total_price'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ================================================================ -->
    <!-- TOTALS -->
    <!-- ================================================================ -->
    <div class="totals">
        <div class="total-row">
            <span class="label">Subtotal</span>
            <span class="value">TSh <?= number_format($subtotal) ?></span>
        </div>
        <?php if ($discount_amount > 0): ?>
        <div class="total-row" style="color: #059669;">
            <span class="label">Discount</span>
            <span class="value">- TSh <?= number_format($discount_amount) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row grand-total">
            <span class="label">Grand Total</span>
            <span class="value">TSh <?= number_format($grand_total) ?></span>
        </div>
    </div>

    <hr class="receipt-divider-double">

    <!-- ================================================================ -->
    <!-- RECEIPT FOOTER -->
    <!-- ================================================================ -->
    <div class="receipt-footer">
        <div class="thank-you">Thank You!</div>
        <div>This is a computer generated receipt</div>
        <div class="small"><?= date('Y') ?> &copy; Braick Dispensary</div>
        <div class="small">Receipt #: <?= $sale_number ?></div>
    </div>

    <!-- ================================================================ -->
    <!-- BUTTONS (Not printed) -->
    <!-- ================================================================ -->
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        
        <?php if ($type === 'prescription'): ?>
            <a href="prescription_history.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to History
            </a>
        <?php else: ?>
            <a href="otc_history.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to OTC History
            </a>
        <?php endif; ?>
        
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>

</div>

<!-- ================================================================ -->
<!-- FONTAWESOME (For buttons) -->
<!-- ================================================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Auto print - uncomment for automatic printing
    // window.onload = function() {
    //     setTimeout(function() {
    //         window.print();
    //     }, 800);
    // };
    
    console.log('%c🧾 Braick - Receipt (FIXED 80mm SIZE)', 'font-size:16px; font-weight:bold; color:#065F46;');
    console.log('%c📋 Sale #: <?= $sale_number ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c📐 Width: 80mm (Fixed)', 'font-size:12px; color:#64748B;');
    console.log('%c💰 Total: TSh <?= number_format($grand_total) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🖨️ Click Print - size will remain 80mm', 'font-size:12px; color:#059669;');
</script>

</body>
</html>