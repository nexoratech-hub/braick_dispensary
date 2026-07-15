<?php
// ================================================================
// FILE: frontend/pages/pharmacy/print_receipt.php
// PHARMACY - PRINT RECEIPT (Prescription & OTC)
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
$tax = 0; // No tax for now

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
           RECEIPT STYLES
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
            padding: 20px;
        }
        
        .receipt-wrapper {
            background: white;
            max-width: 400px;
            width: 100%;
            padding: 20px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        
        .receipt-header .logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 6px;
            background: white;
            padding: 4px;
            border: 2px solid #065F46;
        }
        
        .receipt-header .clinic-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #065F46;
        }
        
        .receipt-header .clinic-sub {
            font-size: 0.65rem;
            color: #64748B;
        }
        
        .receipt-header .clinic-details {
            font-size: 0.6rem;
            color: #64748B;
            margin-top: 4px;
            line-height: 1.4;
        }
        
        .receipt-header .receipt-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0B5ED7;
            margin-top: 6px;
            letter-spacing: 2px;
        }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 10px 0;
        }
        
        .receipt-divider-double {
            border: none;
            border-top: 2px dashed #333;
            margin: 10px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            padding: 2px 0;
            color: #333;
        }
        
        .info-row .label {
            color: #64748B;
        }
        
        .info-row .value {
            font-weight: 500;
            text-align: right;
        }
        
        .items-table {
            width: 100%;
            font-size: 0.7rem;
            border-collapse: collapse;
            margin: 8px 0;
        }
        
        .items-table th {
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            color: #64748B;
            border-bottom: 1px solid #ccc;
            padding: 4px 0;
            letter-spacing: 0.5px;
        }
        
        .items-table th.text-right {
            text-align: right;
        }
        
        .items-table td {
            padding: 3px 0;
            border-bottom: 1px solid #eee;
        }
        
        .items-table td.text-right {
            text-align: right;
        }
        
        .items-table .item-name {
            max-width: 150px;
            word-wrap: break-word;
        }
        
        .totals {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
        }
        
        .totals .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            padding: 2px 0;
        }
        
        .totals .total-row .label {
            color: #64748B;
        }
        
        .totals .total-row .value {
            font-weight: 600;
        }
        
        .totals .grand-total {
            border-top: 2px solid #333;
            padding-top: 6px;
            margin-top: 4px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #065F46;
        }
        
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #333;
            padding-top: 12px;
            margin-top: 12px;
            font-size: 0.65rem;
            color: #64748B;
        }
        
        .receipt-footer .thank-you {
            font-size: 0.85rem;
            font-weight: 600;
            color: #065F46;
            margin-bottom: 4px;
        }
        
        .receipt-footer .small {
            font-size: 0.55rem;
            color: #94A3B8;
        }
        
        .payment-method-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
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
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-badge.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge.cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .print-btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 16px;
            background: #0B5ED7;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
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
            margin-top: 8px;
            background: transparent;
            color: #64748B;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 0.8rem;
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
        
        .sale-type-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.55rem;
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
        
        /* Print Styles */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            
            .receipt-wrapper {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
                padding: 10px 16px !important;
            }
            
            .print-btn, .back-btn, .no-print {
                display: none !important;
            }
            
            .receipt-header .logo {
                border: 2px solid #065F46 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .payment-method-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .status-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .sale-type-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .receipt-wrapper {
                padding: 12px 16px;
            }
            .receipt-header .clinic-name {
                font-size: 1rem;
            }
            .items-table {
                font-size: 0.65rem;
            }
            .info-row {
                font-size: 0.65rem;
            }
            .totals .total-row {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper" id="receipt">
    
    <!-- ================================================================ -->
    <!-- RECEIPT HEADER -->
    <!-- ================================================================ -->
    <div class="receipt-header">
        <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22%3E%3Crect width=%2260%22 height=%2260%22 fill=%22%23065F46%22 rx=%2250%25%22/%3E%3Ctext x=%2230%22 y=%2238%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2224%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
        
        <div class="clinic-name">BRAICK DISPENSARY</div>
        <div class="clinic-sub">Quality Healthcare Services</div>
        <div class="clinic-details">
            <?= htmlspecialchars($branch['location'] ?? 'Dodoma, Tanzania') ?><br>
            Tel: <?= htmlspecialchars($branch['phone'] ?? '+255 759 154 160') ?> | Email: <?= htmlspecialchars($branch['email'] ?? 'info@braick.com') ?>
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
        <span class="label">Customer/Patient</span>
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
        <span class="label">Payment Method</span>
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
                <td class="text-right">TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                <td class="text-right">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
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
        <?php if ($tax > 0): ?>
        <div class="total-row">
            <span class="label">Tax</span>
            <span class="value">TSh <?= number_format($tax) ?></span>
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
        <div class="thank-you">Thank You for Choosing Braick Dispensary</div>
        <div>This is a computer generated receipt</div>
        <div class="small">For inquiries, please contact us</div>
        <div class="small" style="margin-top:4px; font-size:0.5rem;">
            <?= date('Y') ?> &copy; Braick Dispensary - All rights reserved
        </div>
        <div class="small" style="margin-top:2px; font-size:0.5rem;">
            Receipt #: <?= $sale_number ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BUTTONS (Not printed) -->
    <!-- ================================================================ -->
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="otc_history.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to History
        </a>
        <?php if ($type === 'prescription'): ?>
        <a href="prescription_history.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Prescription History
        </a>
        <?php endif; ?>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>

</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Auto print when page loads (optional - uncomment to use)
    // window.onload = function() {
    //     setTimeout(function() {
    //         window.print();
    //     }, 1000);
    // };
    
    console.log('%c🧾 Braick - Receipt', 'font-size:16px; font-weight:bold; color:#065F46;');
    console.log('%c📋 Sale #: <?= $sale_number ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c👤 Customer: <?= htmlspecialchars($customer_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c💰 Total: TSh <?= number_format($grand_total) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🖨️ Click Print to print receipt', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>