<?php
// ================================================================
// FILE: frontend/pages/cashier/print_receipt.php
// CASHIER - PRINT RECEIPT
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS CASHIER OR ADMIN
// ================================================================
$allowed_roles = ['cashier', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET RECEIPT PARAMETERS
// ================================================================
$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$otc_sale_id = isset($_GET['otc_id']) ? (int)$_GET['otc_id'] : 0;

$receipt_type = isset($_GET['type']) ? $_GET['type'] : 'bill'; // bill, payment, otc

// ================================================================
// FETCH RECEIPT DATA BASED ON TYPE
// ================================================================
$receipt_data = null;
$bill_data = null;
$payment_data = null;
$patient_data = null;
$branch_data = null;
$items_data = [];
$otc_data = null;

// If receipt ID is provided, fetch from receipts table
if ($receipt_id > 0) {
    $stmt = $db->prepare("
        SELECT r.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone as patient_phone,
               u.full_name as cashier_name,
               b.name as branch_name,
               b.location as branch_location,
               b.phone as branch_phone,
               b.email as branch_email
        FROM receipts r
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN users u ON r.printed_by = u.id
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ?
    ");
    $stmt->execute([$receipt_id]);
    $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($receipt_data) {
        // Decode receipt data
        $receipt_json = json_decode($receipt_data['receipt_data'], true);
        if ($receipt_json) {
            $bill_data = $receipt_json['bill'] ?? null;
            $payment_data = $receipt_json['payment'] ?? null;
            $patient_data = $receipt_json['patient'] ?? null;
            $items_data = $receipt_json['items'] ?? [];
            $otc_data = $receipt_json['otc'] ?? null;
        }
    }
}

// If payment ID is provided, fetch payment details
if ($payment_id > 0 && !$receipt_data) {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            b.bill_number,
            b.total_amount,
            b.subtotal,
            b.discount_amount,
            b.balance as bill_balance,
            b.status as bill_status,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            u.full_name as received_by_name,
            br.name as branch_name
        FROM payments p
        LEFT JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        LEFT JOIN branches br ON p.branch_id = br.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payment_data) {
        // Get bill items
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$payment_data['bill_id']]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $patient_data = $payment_data;
        $branch_data = $payment_data;
    }
}

// If bill ID is provided, fetch bill details
if ($bill_id > 0 && !$receipt_data && !$payment_data) {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            u.full_name as created_by_name,
            br.name as branch_name,
            br.location as branch_location,
            br.phone as branch_phone,
            br.email as branch_email
        FROM bills b
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN branches br ON b.branch_id = br.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill_data) {
        // Get bill items
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$bill_id]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest payment
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE bill_id = ?
            ORDER BY received_at DESC LIMIT 1
        ");
        $stmt->execute([$bill_id]);
        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $patient_data = $bill_data;
        $branch_data = $bill_data;
    }
}

// If OTC sale ID is provided
if ($otc_sale_id > 0 && !$receipt_data) {
    $stmt = $db->prepare("
        SELECT 
            o.*,
            u.full_name as sold_by_name,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        LEFT JOIN branches b ON o.branch_id = b.id
        LEFT JOIN patients p ON o.patient_id = p.id
        WHERE o.id = ?
    ");
    $stmt->execute([$otc_sale_id]);
    $otc_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($otc_data) {
        // Get OTC items
        $stmt = $db->prepare("
            SELECT oi.*, mi.medication_name, mi.batch_number, mi.unit
            FROM otc_sale_items oi
            LEFT JOIN medications_inventory mi ON oi.inventory_id = mi.id
            WHERE oi.sale_id = ?
            ORDER BY oi.created_at DESC
        ");
        $stmt->execute([$otc_sale_id]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// IF NO DATA FOUND, SHOW ERROR
// ================================================================
if (!$receipt_data && !$payment_data && !$bill_data && !$otc_data) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Receipt Not Found</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .error-box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
            .error-box i { font-size: 48px; color: #DC2626; margin-bottom: 16px; }
            .error-box h2 { color: #1E293B; margin: 0 0 8px; }
            .error-box p { color: #64748B; margin: 0 0 20px; }
            .btn-back { display: inline-block; padding: 10px 24px; background: #059669; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .btn-back:hover { background: #047857; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <i class="fas fa-file-invoice"></i>
            <h2>Receipt Not Found</h2>
            <p>The receipt you are looking for does not exist or has been removed.</p>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ================================================================
// DETERMINE WHAT TO DISPLAY
// ================================================================
$display_type = 'bill';
$display_data = null;
$display_items = $items_data;
$display_patient = null;
$display_branch = null;
$display_payment = null;

if ($otc_data) {
    $display_type = 'otc';
    $display_data = $otc_data;
    $display_patient = $otc_data;
    $display_branch = $otc_data;
} elseif ($payment_data && $bill_data) {
    $display_type = 'payment';
    $display_data = $bill_data;
    $display_payment = $payment_data;
    $display_patient = $patient_data;
    $display_branch = $branch_data;
} elseif ($bill_data) {
    $display_type = 'bill';
    $display_data = $bill_data;
    $display_patient = $patient_data;
    $display_branch = $branch_data;
    $display_payment = $payment_data;
} elseif ($receipt_data) {
    $display_type = 'receipt';
    $display_data = $receipt_data;
    $display_patient = $patient_data;
    $display_branch = $branch_data;
    $display_payment = $payment_data;
    $display_items = $items_data;
}

// ================================================================
// FORMAT FUNCTIONS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y H:i', strtotime($datetime));
}

function formatDateOnly($date) {
    if (empty($date)) return 'N/A';
    return date('d/m/Y', strtotime($date));
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'paid' => '✅ Paid',
        'partial' => '🔄 Partial',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getPaymentMethodLabel($method) {
    $map = [
        'cash' => '💰 Cash',
        'm-pesa' => '📱 M-Pesa',
        'airtel_money' => '📱 Airtel Money',
        'tigo_pesa' => '📱 Tigo Pesa',
        'halopesa' => '📱 Halo Pesa',
        'bank' => '🏦 Bank Transfer',
        'card' => '💳 Card',
        'insurance' => '🏥 Insurance',
        'other' => '📦 Other'
    ];
    return $map[$method] ?? ucfirst($method);
}

// ================================================================
// DETERMINE RECEIPT NUMBER
// ================================================================
$receipt_number = 'N/A';
if ($receipt_data && !empty($receipt_data['receipt_number'])) {
    $receipt_number = $receipt_data['receipt_number'];
} elseif ($payment_data && !empty($payment_data['receipt_number'])) {
    $receipt_number = $payment_data['receipt_number'];
} elseif ($otc_data) {
    $receipt_number = 'OTC-' . $otc_data['sale_number'];
} elseif ($bill_data) {
    $receipt_number = $bill_data['bill_number'];
}

// ================================================================
// DETERMINE AMOUNTS
// ================================================================
$subtotal = 0;
$total_amount = 0;
$discount_amount = 0;
$paid_amount = 0;
$balance = 0;

if ($display_type === 'otc' && $otc_data) {
    $subtotal = $otc_data['subtotal'] ?? 0;
    $discount_amount = $otc_data['discount_amount'] ?? 0;
    $total_amount = $otc_data['total_amount'] ?? 0;
    $paid_amount = $total_amount; // OTC is usually paid in full
    $balance = 0;
} elseif ($bill_data) {
    $subtotal = $bill_data['subtotal'] ?? 0;
    $discount_amount = $bill_data['discount_amount'] ?? 0;
    $total_amount = $bill_data['total_amount'] ?? 0;
    $paid_amount = $bill_data['paid_amount'] ?? 0;
    $balance = $bill_data['balance'] ?? 0;
} elseif ($payment_data) {
    $paid_amount = $payment_data['amount'] ?? 0;
}

// ================================================================
// GET CASHIER NAME
// ================================================================
$cashier_name = $user_full_name;
if ($payment_data && !empty($payment_data['received_by_name'])) {
    $cashier_name = $payment_data['received_by_name'];
} elseif ($otc_data && !empty($otc_data['sold_by_name'])) {
    $cashier_name = $otc_data['sold_by_name'];
}

// ================================================================
// GET BRANCH INFO
// ================================================================
$branch_name = $user_branch_name;
$branch_location = '';
$branch_phone = '';
$branch_email = '';

if ($display_branch) {
    if (!empty($display_branch['branch_name'])) {
        $branch_name = $display_branch['branch_name'];
    }
    if (!empty($display_branch['branch_location'])) {
        $branch_location = $display_branch['branch_location'];
    }
    if (!empty($display_branch['branch_phone'])) {
        $branch_phone = $display_branch['branch_phone'];
    }
    if (!empty($display_branch['branch_email'])) {
        $branch_email = $display_branch['branch_email'];
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_html = '<img src="' . $logo_path . '" alt="Braick Dispensary" style="height:60px;width:auto;max-height:60px;object-fit:contain;" onerror="this.style.display=\'none\'">';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= htmlspecialchars($receipt_number) ?></title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           RECEIPT PRINT STYLES
           ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', 'Consolas', monospace;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            padding: 20px;
            min-height: 100vh;
        }
        
        .receipt-container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 20px 20px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
        }
        
        /* ================================================================
           RECEIPT HEADER
           ================================================================ */
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        
        .receipt-header .logo {
            margin-bottom: 6px;
        }
        
        .receipt-header .logo img {
            height: 50px;
            width: auto;
            max-height: 50px;
        }
        
        .receipt-header .brand-name {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: #059669;
            text-transform: uppercase;
        }
        
        .receipt-header .brand-sub {
            font-size: 0.6rem;
            color: #64748B;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        
        .receipt-header .branch-info {
            font-size: 0.65rem;
            color: #64748B;
            margin-top: 2px;
        }
        
        .receipt-header .divider-line {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 6px 0;
        }
        
        .receipt-title {
            text-align: center;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1E293B;
            margin: 4px 0;
        }
        
        /* ================================================================
           RECEIPT BODY
           ================================================================ */
        .receipt-body {
            padding: 4px 0;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 0.65rem;
        }
        
        .receipt-row .label {
            color: #64748B;
        }
        
        .receipt-row .value {
            color: #1E293B;
            font-weight: 600;
        }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #e2e8f0;
            margin: 6px 0;
        }
        
        .receipt-divider-thick {
            border: none;
            border-top: 2px dashed #ccc;
            margin: 8px 0;
        }
        
        /* ================================================================
           ITEMS TABLE
           ================================================================ */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.6rem;
            margin: 6px 0;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 3px 2px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.5rem;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        
        .items-table tbody td {
            padding: 3px 2px;
            border-bottom: 1px solid #f1f5f9;
            color: #1E293B;
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .items-table .item-name {
            font-weight: 500;
        }
        
        .items-table .item-qty {
            text-align: center;
        }
        
        .items-table .item-price {
            text-align: right;
            font-weight: 600;
        }
        
        .items-table .item-total {
            text-align: right;
            font-weight: 700;
        }
        
        /* ================================================================
           TOTALS
           ================================================================ */
        .totals-section {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px dashed #ccc;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 0.7rem;
        }
        
        .totals-row .label {
            color: #64748B;
        }
        
        .totals-row .value {
            font-weight: 700;
        }
        
        .totals-row.total {
            border-top: 2px solid #1E293B;
            padding-top: 4px;
            margin-top: 2px;
            font-size: 0.8rem;
        }
        
        .totals-row.total .value {
            color: #059669;
            font-size: 0.9rem;
        }
        
        .totals-row.balance .value {
            color: #DC2626;
        }
        
        .totals-row.balance.zero .value {
            color: #059669;
        }
        
        /* ================================================================
           PAYMENT INFO
           ================================================================ */
        .payment-info {
            background: #f8fafc;
            border-radius: 6px;
            padding: 8px 12px;
            margin: 8px 0;
            border: 1px solid #e2e8f0;
        }
        
        .payment-info .payment-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.6rem;
            padding: 2px 0;
        }
        
        .payment-info .payment-row .label {
            color: #64748B;
        }
        
        .payment-info .payment-row .value {
            font-weight: 600;
            color: #1E293B;
        }
        
        /* ================================================================
           RECEIPT FOOTER
           ================================================================ */
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #ccc;
            padding-top: 10px;
            margin-top: 12px;
        }
        
        .receipt-footer .footer-text {
            font-size: 0.55rem;
            color: #64748B;
            line-height: 1.4;
        }
        
        .receipt-footer .footer-text strong {
            color: #1E293B;
        }
        
        .receipt-footer .thank-you {
            font-size: 0.7rem;
            font-weight: 700;
            color: #059669;
            margin: 4px 0;
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.paid { background: #D1FAE5; color: #059669; }
        .status-badge.partial { background: #DBEAFE; color: #3B82F6; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        /* ================================================================
           PRINT BUTTON
           ================================================================ */
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 12px;
            width: 100%;
            justify-content: center;
        }
        
        .print-btn:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: transparent;
            color: #64748B;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-top: 8px;
            width: 100%;
            justify-content: center;
        }
        
        .back-btn:hover {
            border-color: #059669;
            color: #059669;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                display: block;
            }
            
            .receipt-container {
                max-width: 100%;
                border-radius: 0;
                box-shadow: none;
                padding: 12px 16px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            .receipt-container {
                page-break-after: avoid;
            }
        }
        
        @media screen and (max-width: 480px) {
            body {
                padding: 10px;
            }
            .receipt-container {
                padding: 14px 14px 12px;
            }
        }
        
        .print-only {
            display: none;
        }
        
        /* OTC specific */
        .otc-customer {
            background: #ECFDF5;
            border-radius: 6px;
            padding: 6px 12px;
            margin: 6px 0;
            border: 1px solid #D1FAE5;
        }
    </style>
</head>
<body>

<div class="receipt-container" id="receiptContainer">
    
    <!-- ================================================================ -->
    <!-- RECEIPT HEADER -->
    <!-- ================================================================ -->
    <div class="receipt-header">
        <div class="logo">
            <?= $logo_html ?>
        </div>
        <div class="brand-name">Braick Dispensary</div>
        <div class="brand-sub">Quality Healthcare Services</div>
        <div class="branch-info">
            <?= htmlspecialchars($branch_name) ?>
            <?php if (!empty($branch_location)): ?>
                <br><?= htmlspecialchars($branch_location) ?>
            <?php endif; ?>
            <?php if (!empty($branch_phone)): ?>
                <br>Tel: <?= htmlspecialchars($branch_phone) ?>
            <?php endif; ?>
            <?php if (!empty($branch_email)): ?>
                <br>Email: <?= htmlspecialchars($branch_email) ?>
            <?php endif; ?>
        </div>
        <hr class="divider-line">
        <div class="receipt-title">
            <?php if ($display_type === 'otc'): ?>
                Over-The-Counter Sale
            <?php elseif ($display_type === 'payment'): ?>
                Payment Receipt
            <?php else: ?>
                Bill Receipt
            <?php endif; ?>
        </div>
        <div style="font-size:0.55rem;color:#64748B;text-align:center;">
            <?= formatDate(date('Y-m-d H:i:s')) ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPT BODY -->
    <!-- ================================================================ -->
    <div class="receipt-body">
        
        <!-- Receipt Number & Status -->
        <div class="receipt-row">
            <span class="label">Receipt #</span>
            <span class="value" style="color:#059669;"><?= htmlspecialchars($receipt_number) ?></span>
        </div>
        
        <?php if ($display_type === 'otc'): ?>
            <div class="receipt-row">
                <span class="label">Sale #</span>
                <span class="value"><?= htmlspecialchars($otc_data['sale_number'] ?? 'N/A') ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($display_type === 'bill' || $display_type === 'payment'): ?>
            <div class="receipt-row">
                <span class="label">Bill #</span>
                <span class="value"><?= htmlspecialchars($bill_data['bill_number'] ?? 'N/A') ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($display_type === 'payment' && $payment_data): ?>
            <div class="receipt-row">
                <span class="label">Payment #</span>
                <span class="value"><?= htmlspecialchars($payment_data['receipt_number'] ?? 'N/A') ?></span>
            </div>
        <?php endif; ?>
        
        <hr class="receipt-divider">
        
        <!-- Patient / Customer Info -->
        <?php if ($display_type === 'otc' && $otc_data): ?>
            <div class="otc-customer">
                <div class="receipt-row">
                    <span class="label">Customer</span>
                    <span class="value"><?= htmlspecialchars($otc_data['customer_name'] ?? 'Walk-in Customer') ?></span>
                </div>
                <?php if (!empty($otc_data['customer_phone'])): ?>
                    <div class="receipt-row">
                        <span class="label">Phone</span>
                        <span class="value"><?= htmlspecialchars($otc_data['customer_phone']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($display_patient): ?>
            <div class="receipt-row">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($display_patient['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Patient ID</span>
                <span class="value"><?= htmlspecialchars($display_patient['patient_code'] ?? $display_patient['patient_id'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($display_patient['patient_phone'])): ?>
                <div class="receipt-row">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($display_patient['patient_phone']) ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <hr class="receipt-divider">
        
        <!-- Items -->
        <?php if (!empty($display_items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:50%;">Item</th>
                        <th style="width:15%;text-align:center;">Qty</th>
                        <th style="width:17%;text-align:right;">Price</th>
                        <th style="width:18%;text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($display_items as $item): ?>
                        <tr>
                            <td class="item-name">
                                <?php 
                                    $item_name = $item['item_name'] ?? $item['medicine_name'] ?? $item['test_name'] ?? 'Item';
                                    echo htmlspecialchars($item_name);
                                    if (!empty($item['medication_name'])) {
                                        echo ' (' . htmlspecialchars($item['medication_name']) . ')';
                                    }
                                ?>
                                <?php if (!empty($item['batch_number'])): ?>
                                    <div style="font-size:0.5rem;color:#94A3B8;">Batch: <?= htmlspecialchars($item['batch_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="item-qty"><?= $item['quantity'] ?? 1 ?></td>
                            <td class="item-price"><?= formatMoney($item['unit_price'] ?? 0) ?></td>
                            <td class="item-total"><?= formatMoney($item['total_price'] ?? ($item['unit_price'] * $item['quantity'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align:center;padding:8px 0;color:#94A3B8;font-size:0.65rem;">
                No items found
            </div>
        <?php endif; ?>
        
        <hr class="receipt-divider-thick">
        
        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-row">
                <span class="label">Subtotal</span>
                <span class="value">TSh <?= formatMoney($subtotal) ?></span>
            </div>
            
            <?php if ($discount_amount > 0): ?>
                <div class="totals-row" style="color:#D97706;">
                    <span class="label">Discount</span>
                    <span class="value">- TSh <?= formatMoney($discount_amount) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="totals-row total">
                <span class="label">Total</span>
                <span class="value">TSh <?= formatMoney($total_amount) ?></span>
            </div>
            
            <?php if ($display_type === 'bill' || $display_type === 'payment'): ?>
                <div class="totals-row" style="color:#059669;">
                    <span class="label">Paid</span>
                    <span class="value">TSh <?= formatMoney($paid_amount) ?></span>
                </div>
                
                <?php if ($balance > 0): ?>
                    <div class="totals-row balance">
                        <span class="label">Balance</span>
                        <span class="value">TSh <?= formatMoney($balance) ?></span>
                    </div>
                <?php else: ?>
                    <div class="totals-row balance zero">
                        <span class="label">Balance</span>
                        <span class="value" style="color:#059669;">TSh 0 - Paid in Full</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Payment Info -->
        <?php if ($payment_data || $otc_data): ?>
            <div class="payment-info">
                <div class="payment-row">
                    <span class="label">Payment Method</span>
                    <span class="value">
                        <?php 
                            $method = $payment_data['payment_method'] ?? $otc_data['payment_method'] ?? 'cash';
                            echo getPaymentMethodLabel($method);
                        ?>
                    </span>
                </div>
                <?php if ($payment_data && !empty($payment_data['reference_number'])): ?>
                    <div class="payment-row">
                        <span class="label">Reference</span>
                        <span class="value"><?= htmlspecialchars($payment_data['reference_number']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="payment-row">
                    <span class="label">Received By</span>
                    <span class="value"><?= htmlspecialchars($cashier_name) ?></span>
                </div>
                <?php if ($payment_data && !empty($payment_data['received_at'])): ?>
                    <div class="payment-row">
                        <span class="label">Date/Time</span>
                        <span class="value"><?= formatDate($payment_data['received_at']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Status -->
        <div style="text-align:center;margin:6px 0;">
            <?php if ($display_type === 'otc'): ?>
                <span class="status-badge <?= ($otc_data['payment_status'] ?? 'pending') === 'paid' ? 'paid' : 'pending' ?>">
                    <?= $otc_data['payment_status'] === 'paid' ? '✅ Paid' : '⏳ Pending' ?>
                </span>
            <?php elseif ($bill_data): ?>
                <span class="status-badge <?= $bill_data['status'] ?? 'pending' ?>">
                    <?= getStatusLabel($bill_data['status'] ?? 'pending') ?>
                </span>
            <?php elseif ($payment_data): ?>
                <span class="status-badge paid">✅ Payment Received</span>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPT FOOTER -->
    <!-- ================================================================ -->
    <div class="receipt-footer">
        <div class="thank-you">Thank You for Your Payment!</div>
        <div class="footer-text">
            <strong>Braick Dispensary</strong><br>
            Quality Healthcare Services<br>
            <?= htmlspecialchars($branch_name) ?>
            <?php if (!empty($branch_phone)): ?>
                <br>Tel: <?= htmlspecialchars($branch_phone) ?>
            <?php endif; ?>
        </div>
        <div class="footer-text" style="margin-top:4px;font-size:0.5rem;color:#94A3B8;">
            This is a system generated receipt.<br>
            Printed on <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BUTTONS (No Print) -->
    <!-- ================================================================ -->
    <div class="no-print" style="margin-top:12px;">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Auto print on load if print parameter is set
    <?php if (isset($_GET['print']) && $_GET['print'] == 1): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    <?php endif; ?>
    
    // Keyboard shortcut: Ctrl+P to print
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
    
    console.log('%c🧾 Braick - Receipt Print', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c📋 Receipt #: <?= htmlspecialchars($receipt_number) ?>', 'font-size:12px; color:#059669;');
    console.log('%c👤 Cashier: <?= htmlspecialchars($cashier_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c💰 Total: TSh <?= formatMoney($total_amount) ?>', 'font-size:12px; color:#059669;');
</script>

</body>
</html>