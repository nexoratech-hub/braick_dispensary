<?php
// ================================================================
// FILE: frontend/pages/cashier/receipt.php
// RECEIPT - VIEW AND PRINT RECEIPT
// SUPPORTS: Bills, OTC Sales, Prescriptions with Instructions
// FIXED: Shows OTC details with instructions
// FIXED: Admin banner at bottom
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$receipt_data = null;
$all_items = [];
$medication_items = [];
$other_items = [];
$otc_items = [];
$otc_sale = null;
$is_otc = false;
$error = null;
$currency = 'TSh';
$is_from_otc = false;

try {
    // Get system settings for currency
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'currency') {
            $currency = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    $currency = 'TSh';
}

// ================================================================
// STEP 1: CHECK IF THIS IS AN OTC SALE
// ================================================================
if ($sale_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            o.*,
            u.full_name as cashier_name,
            br.name as branch_name,
            br.location as branch_location,
            br.phone as branch_phone,
            br.email as branch_email
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        LEFT JOIN branches br ON o.branch_id = br.id
        WHERE o.id = ? AND o.branch_id = ?
    ");
    $stmt->execute([$sale_id, $user_branch_id]);
    $otc_sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($otc_sale) {
        $is_otc = true;
        $is_from_otc = true;
        
        // Get OTC items with instructions
        $stmt = $db->prepare("
            SELECT * FROM otc_sale_items 
            WHERE sale_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sale_id]);
        $otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build receipt data from OTC sale
        $receipt_data = [
            'payment_id' => null,
            'receipt_number' => 'OTC-' . $otc_sale['sale_number'],
            'bill_id' => $otc_sale['bill_id'],
            'patient_id' => $otc_sale['patient_id'],
            'paid_amount' => $otc_sale['total_amount'],
            'payment_method' => $otc_sale['payment_method'] ?? 'cash',
            'received_by' => $otc_sale['sold_by'],
            'received_at' => $otc_sale['updated_at'] ?? $otc_sale['created_at'],
            'branch_id' => $otc_sale['branch_id'],
            'bill_number' => 'OTC-' . $otc_sale['sale_number'],
            'bill_total' => $otc_sale['total_amount'],
            'bill_subtotal' => $otc_sale['subtotal'],
            'bill_paid' => $otc_sale['total_amount'],
            'bill_balance' => 0,
            'bill_status' => 'paid',
            'discount_amount' => $otc_sale['discount_amount'] ?? 0,
            'cashier_discount' => 0,
            'total_discount' => $otc_sale['discount_amount'] ?? 0,
            'bill_payment_method' => $otc_sale['payment_method'] ?? 'cash',
            'bill_created_at' => $otc_sale['created_at'],
            'patient_name' => $otc_sale['customer_name'] ?? 'Walk-in Customer',
            'patient_code' => $otc_sale['patient_id'] ?? 'N/A',
            'patient_phone' => $otc_sale['customer_phone'] ?? 'N/A',
            'patient_address' => null,
            'patient_gender' => null,
            'patient_dob' => null,
            'cashier_name' => $otc_sale['cashier_name'] ?? $user_full_name,
            'branch_name' => $otc_sale['branch_name'] ?? $user_branch_name,
            'branch_location' => $otc_sale['branch_location'] ?? 'Dodoma, Tanzania',
            'branch_phone' => $otc_sale['branch_phone'] ?? '+255 759 154 160',
            'branch_email' => $otc_sale['branch_email'] ?? 'info@braick.com',
            'visit_number' => null,
            'visit_type' => null,
            'is_otc' => true,
            'sale_number' => $otc_sale['sale_number'],
            'otc_sale_id' => $otc_sale['id']
        ];
    }
}

// ================================================================
// STEP 2: IF NOT OTC, GET FROM BILLS TABLE
// ================================================================
if (!$is_otc && ($payment_id > 0 || $bill_id > 0)) {
    try {
        // GET PAYMENT AND BILL DETAILS
        if ($payment_id > 0) {
            $stmt = $db->prepare("
                SELECT 
                    p.id as payment_id,
                    p.receipt_number,
                    p.bill_id,
                    p.patient_id,
                    p.amount as paid_amount,
                    p.payment_method,
                    p.reference_number,
                    p.received_by,
                    p.received_at,
                    p.branch_id,
                    p.notes as payment_notes,
                    b.bill_number,
                    b.total_amount as bill_total,
                    b.subtotal as bill_subtotal,
                    b.paid_amount as bill_paid,
                    b.balance as bill_balance,
                    b.status as bill_status,
                    b.discount_amount as pharmacy_discount,
                    b.cashier_discount,
                    b.total_discount,
                    b.payment_method as bill_payment_method,
                    b.created_at as bill_created_at,
                    b.updated_at as bill_updated_at,
                    pat.full_name as patient_name,
                    pat.patient_id as patient_code,
                    pat.phone as patient_phone,
                    pat.address as patient_address,
                    pat.gender as patient_gender,
                    pat.date_of_birth as patient_dob,
                    u.full_name as cashier_name,
                    br.name as branch_name,
                    br.location as branch_location,
                    br.phone as branch_phone,
                    br.email as branch_email,
                    v.visit_number,
                    v.visit_type
                FROM payments p
                LEFT JOIN bills b ON p.bill_id = b.id
                LEFT JOIN patients pat ON p.patient_id = pat.id
                LEFT JOIN users u ON p.received_by = u.id
                LEFT JOIN branches br ON p.branch_id = br.id
                LEFT JOIN visits v ON b.visit_id = v.id
                WHERE p.id = ? 
            ");
            $stmt->execute([$payment_id]);
            $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($receipt_data) {
                $bill_id = $receipt_data['bill_id'];
            }
        }
        
        // If payment not found but bill_id provided
        if (!$receipt_data && $bill_id > 0) {
            $stmt = $db->prepare("
                SELECT 
                    NULL as payment_id,
                    NULL as receipt_number,
                    b.id as bill_id,
                    b.patient_id,
                    b.paid_amount,
                    b.payment_method,
                    NULL as reference_number,
                    NULL as received_by,
                    NULL as received_at,
                    b.branch_id,
                    NULL as payment_notes,
                    b.bill_number,
                    b.total_amount as bill_total,
                    b.subtotal as bill_subtotal,
                    b.paid_amount as bill_paid,
                    b.balance as bill_balance,
                    b.status as bill_status,
                    b.discount_amount as pharmacy_discount,
                    b.cashier_discount,
                    b.total_discount,
                    b.payment_method as bill_payment_method,
                    b.created_at as bill_created_at,
                    b.updated_at as bill_updated_at,
                    pat.full_name as patient_name,
                    pat.patient_id as patient_code,
                    pat.phone as patient_phone,
                    pat.address as patient_address,
                    pat.gender as patient_gender,
                    pat.date_of_birth as patient_dob,
                    u.full_name as cashier_name,
                    br.name as branch_name,
                    br.location as branch_location,
                    br.phone as branch_phone,
                    br.email as branch_email,
                    v.visit_number,
                    v.visit_type
                FROM bills b
                LEFT JOIN patients pat ON b.patient_id = pat.id
                LEFT JOIN users u ON b.created_by = u.id
                LEFT JOIN branches br ON b.branch_id = br.id
                LEFT JOIN visits v ON b.visit_id = v.id
                WHERE b.id = ? 
            ");
            $stmt->execute([$bill_id]);
            $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$receipt_data) {
            $error = "Bill not found!";
        } else {
            $receipt_data['is_otc'] = false;
            $receipt_data['otc_sale_id'] = null;
            $receipt_data['sale_number'] = null;
            
            // GET BILL ITEMS with instructions from prescription_items
            $stmt = $db->prepare("
                SELECT 
                    bi.*,
                    pi.instructions as medication_instructions,
                    pi.dosage,
                    pi.frequency,
                    pi.duration,
                    pi.route,
                    p.prescription_number
                FROM bill_items bi
                LEFT JOIN prescriptions p ON bi.reference_id = p.id AND bi.reference_type = 'prescription'
                LEFT JOIN prescription_items pi ON p.id = pi.prescription_id 
                    AND pi.medication_name = bi.item_name
                WHERE bi.bill_id = ? AND bi.status != 'cancelled'
                ORDER BY bi.item_type ASC, bi.created_at ASC
            ");
            $stmt->execute([$bill_id]);
            $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Receipt error: " . $e->getMessage());
    }
}

// ================================================================
// STEP 3: SEPARATE ITEMS BY CATEGORY
// ================================================================
$medication_items = [];
$other_items = [];

if (!$is_otc) {
    foreach ($all_items as $item) {
        if ($item['item_type'] === 'medication') {
            $medication_items[] = $item;
        } else {
            $other_items[] = $item;
        }
    }
}

// Calculate totals for each category
$medication_total = 0;
foreach ($medication_items as $item) {
    $medication_total += (float)($item['total_price'] ?? 0);
}

$other_total = 0;
foreach ($other_items as $item) {
    $other_total += (float)($item['total_price'] ?? 0);
}

$otc_total = 0;
foreach ($otc_items as $item) {
    $otc_total += (float)($item['total_price'] ?? 0);
}

// If error, redirect
if ($error) {
    $_SESSION['receipt_error'] = $error;
    header('Location: pending_bills.php');
    exit;
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       RECEIPT STYLES
       ================================================================ */
    .receipt-wrapper {
        max-width: 1000px;
        margin: 0 auto;
        background: var(--bg-card);
        border-radius: 20px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }
    
    .receipt-wrapper:hover {
        box-shadow: 0 8px 40px rgba(5, 150, 105, 0.1);
    }
    
    /* ===== RECEIPT HEADER ===== */
    .receipt-header {
        background: linear-gradient(135deg, #065F46, #047857);
        color: white;
        padding: 24px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        position: relative;
        overflow: hidden;
    }
    
    .receipt-header.otc-header {
        background: linear-gradient(135deg, #6D28D9, #8B5CF6);
    }
    
    .receipt-header::after {
        content: '';
        position: absolute;
        top: -60px;
        right: -60px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
    }
    
    .receipt-header .logo-area {
        display: flex;
        align-items: center;
        gap: 16px;
        position: relative;
        z-index: 1;
    }
    
    .receipt-header .logo-area .logo-img {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: white;
        padding: 4px;
        object-fit: cover;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .receipt-header .logo-area .brand-name {
        font-size: 1.4rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    
    .receipt-header .logo-area .brand-sub {
        font-size: 0.65rem;
        opacity: 0.8;
    }
    
    .receipt-header .receipt-number {
        text-align: right;
        position: relative;
        z-index: 1;
    }
    
    .receipt-header .receipt-number .label {
        font-size: 0.55rem;
        opacity: 0.6;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    
    .receipt-header .receipt-number .number {
        font-size: 1.2rem;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        background: rgba(255,255,255,0.1);
        padding: 2px 14px;
        border-radius: 8px;
        display: inline-block;
        margin-top: 2px;
    }
    
    .receipt-header .receipt-number .date-badge {
        font-size: 0.6rem;
        opacity: 0.7;
        margin-top: 4px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }
    
    .receipt-header .receipt-type-badge {
        background: rgba(255,255,255,0.15);
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        border: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
    }
    
    /* ===== RECEIPT BODY ===== */
    .receipt-body {
        padding: 24px 32px;
    }
    
    /* Business Details */
    .business-details {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px 16px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 2px dashed var(--border-color);
        font-size: 0.8rem;
    }
    
    .business-details .detail-item .label {
        font-size: 0.55rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
    }
    
    .business-details .detail-item .value {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.8rem;
    }
    
    /* Patient Details */
    .patient-details {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 8px 16px;
        padding: 12px 18px;
        background: var(--bg-body);
        border-radius: 10px;
        margin-bottom: 18px;
        border: 1px solid var(--border-color);
    }
    
    .patient-details .detail-item .label {
        font-size: 0.55rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
    }
    
    .patient-details .detail-item .value {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    .patient-details .detail-item .value.otc-customer {
        color: #8B5CF6;
        font-size: 1rem;
    }
    
    /* ===== SECTION HEADERS ===== */
    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9rem;
        margin: 16px 0 10px 0;
        border-left: 4px solid;
    }
    
    .section-header.other-bills {
        background: var(--section-other-bg, #E8F0FE);
        border-left-color: var(--section-other-border, #0B5ED7);
        color: var(--section-other-border, #0B5ED7);
    }
    
    .section-header.medications {
        background: var(--section-medication-bg, #FEF3C7);
        border-left-color: var(--section-medication-border, #D97706);
        color: var(--section-medication-border, #D97706);
    }
    
    .section-header.otc-section {
        background: #EDE9FE;
        border-left-color: #8B5CF6;
        color: #6D28D9;
    }
    
    .section-header .section-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 1px 12px;
        border-radius: 20px;
        color: white;
    }
    
    .section-header.other-bills .section-badge {
        background: var(--section-other-border, #0B5ED7);
    }
    
    .section-header.medications .section-badge {
        background: var(--section-medication-border, #D97706);
    }
    
    .section-header.otc-section .section-badge {
        background: #8B5CF6;
    }
    
    .section-header .section-total {
        margin-left: auto;
        font-size: 0.85rem;
        font-weight: 700;
    }
    
    /* ===== TABLE ===== */
    .receipt-table-wrap {
        overflow-x: auto;
        margin-bottom: 12px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }
    
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    
    .receipt-table thead th {
        text-align: left;
        padding: 8px 14px;
        background: var(--bg-body);
        color: var(--text-secondary);
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        border-bottom: 2px solid var(--border-color);
    }
    
    .receipt-table thead th:last-child {
        text-align: right;
    }
    
    .receipt-table tbody td {
        padding: 6px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.8rem;
        vertical-align: middle;
    }
    
    .receipt-table tbody td:last-child {
        text-align: right;
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }
    
    .receipt-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .receipt-table tbody tr:hover td {
        background: var(--table-hover, #D1FAE5);
    }
    
    .receipt-table .item-name {
        font-weight: 500;
    }
    
    .receipt-table .item-type {
        font-size: 0.55rem;
        color: var(--text-secondary);
        display: block;
        margin-top: 1px;
    }
    
    .receipt-table .item-instruction {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-style: italic;
        display: block;
        background: var(--bg-body);
        padding: 2px 10px;
        border-radius: 4px;
        border-left: 3px solid var(--warning, #D97706);
        margin-top: 3px;
        max-width: 300px;
    }
    
    .receipt-table .text-right {
        text-align: right;
    }
    
    .receipt-table .font-mono {
        font-family: 'Courier New', monospace;
    }
    
    /* ===== TOTALS ===== */
    .totals-section {
        display: flex;
        justify-content: flex-end;
        padding-top: 14px;
        border-top: 2px solid var(--border-color);
        margin-top: 6px;
    }
    
    .totals-box {
        width: 360px;
    }
    
    .totals-box .total-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.85rem;
        align-items: center;
    }
    
    .totals-box .total-row .label {
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 0.8rem;
    }
    
    .totals-box .total-row .value {
        font-weight: 600;
        color: var(--text-primary);
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
    }
    
    .totals-box .total-row .value .currency {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 400;
        margin-right: 2px;
    }
    
    .totals-box .total-row.grand-total {
        border-top: 2px solid var(--border-color);
        padding-top: 8px;
        margin-top: 4px;
        font-size: 1.05rem;
    }
    
    .totals-box .total-row.grand-total .label {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-primary);
    }
    
    .totals-box .total-row.grand-total .value {
        color: var(--success);
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .totals-box .total-row.balance .value {
        color: var(--danger);
        font-weight: 700;
    }
    
    .totals-box .total-row.discount-row .value {
        color: var(--warning);
    }
    
    /* ===== CATEGORY TOTALS ===== */
    .category-totals {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
        margin: 10px 0 6px 0;
    }
    
    .category-total-box {
        padding: 8px 14px;
        border-radius: 8px;
        border: 2px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
    }
    
    .category-total-box.other {
        border-color: var(--section-other-border, #0B5ED7);
        background: var(--section-other-bg, #E8F0FE);
    }
    
    .category-total-box.medication {
        border-color: var(--section-medication-border, #D97706);
        background: var(--section-medication-bg, #FEF3C7);
    }
    
    .category-total-box.otc {
        border-color: #8B5CF6;
        background: #EDE9FE;
    }
    
    .category-total-box .cat-label {
        font-weight: 600;
    }
    
    .category-total-box .cat-value {
        font-weight: 700;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }
    
    .category-total-box.other .cat-value {
        color: var(--section-other-border, #0B5ED7);
    }
    
    .category-total-box.medication .cat-value {
        color: var(--section-medication-border, #D97706);
    }
    
    .category-total-box.otc .cat-value {
        color: #6D28D9;
    }
    
    /* ===== STATUS BADGE ===== */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .status-badge.completed,
    .status-badge.paid {
        background: #D1FAE5;
        color: #059669;
    }
    .status-badge.pending {
        background: #FEF3C7;
        color: #D97706;
    }
    .status-badge.partial {
        background: #DBEAFE;
        color: #2563EB;
    }
    
    [data-theme="dark"] .status-badge.completed,
    [data-theme="dark"] .status-badge.paid {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .status-badge.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    [data-theme="dark"] .status-badge.partial {
        background: #1E3A5F;
        color: #60A5FA;
    }
    
    .payment-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    /* ===== RECEIPT FOOTER ===== */
    .receipt-footer {
        padding: 16px 32px;
        background: var(--bg-body);
        border-top: 2px solid var(--border-color);
        text-align: center;
    }
    
    .receipt-footer .thank-you {
        font-size: 1rem;
        font-weight: 700;
        color: var(--success);
        margin-bottom: 4px;
    }
    
    .receipt-footer .footer-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        opacity: 0.7;
    }
    
    .receipt-footer .footer-copy {
        font-size: 0.55rem;
        color: var(--text-secondary);
        opacity: 0.4;
        margin-top: 4px;
    }
    
    .cashier-info {
        margin-top: 14px;
        padding-top: 10px;
        border-top: 1px solid var(--border-color);
        font-size: 0.7rem;
        color: var(--text-secondary);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .cashier-info strong {
        color: var(--text-primary);
    }
    
    /* ================================================================
       ADMIN BANNER
       ================================================================ */
    .admin-banner {
        background: linear-gradient(135deg, #1E3A5F, #0B5ED7);
        color: white;
        padding: 12px 24px;
        text-align: center;
        font-size: 0.7rem;
        border-top: 3px solid #FFD700;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    [data-theme="dark"] .admin-banner {
        background: linear-gradient(135deg, #0F172A, #1E3A5F);
        border-top-color: #FBBF24;
    }
    
    .admin-banner .admin-badge {
        background: rgba(255,215,0,0.2);
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: #FFD700;
        border: 1px solid rgba(255,215,0,0.2);
    }
    
    .admin-banner .admin-text {
        opacity: 0.8;
        font-size: 0.65rem;
    }
    
    .admin-banner .admin-version {
        background: rgba(255,255,255,0.1);
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.55rem;
        font-family: monospace;
    }
    
    /* ================================================================
       PRINT STYLES
       ================================================================ */
    @media print {
        .top-nav, .sidebar, .no-print, .btn, 
        #sidebarToggle, #darkModeToggle, .page-header .btn,
        .footer, .dark-toggle-btn, .icon-btn, .search-wrapper {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .receipt-wrapper {
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            max-width: 100% !important;
        }
        
        .receipt-header {
            background: #065F46 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            padding: 16px 24px !important;
        }
        
        .receipt-header.otc-header {
            background: #6D28D9 !important;
        }
        
        .receipt-body {
            padding: 16px 24px !important;
        }
        
        .receipt-footer {
            background: #F8FAFC !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            padding: 12px 24px !important;
        }
        
        [data-theme="dark"] .receipt-footer {
            background: #1E293B !important;
        }
        
        .patient-details {
            background: #F1F5F9 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        [data-theme="dark"] .patient-details {
            background: #1E293B !important;
        }
        
        .receipt-table thead th {
            background: #F1F5F9 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        [data-theme="dark"] .receipt-table thead th {
            background: #1E293B !important;
        }
        
        .status-badge.completed,
        .status-badge.paid {
            background: #D1FAE5 !important;
            color: #059669 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .section-header.other-bills {
            background: #E8F0FE !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .section-header.medications {
            background: #FEF3C7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .section-header.otc-section {
            background: #EDE9FE !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .category-total-box.other {
            background: #E8F0FE !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .category-total-box.medication {
            background: #FEF3C7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .category-total-box.otc {
            background: #EDE9FE !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .admin-banner {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            border-top-color: #FFD700 !important;
        }
        
        .receipt-wrapper {
            page-break-inside: avoid;
        }
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .receipt-header {
            flex-direction: column;
            text-align: center;
            padding: 16px 20px;
        }
        
        .receipt-header .logo-area {
            flex-direction: column;
            text-align: center;
        }
        
        .receipt-header .receipt-number {
            text-align: center;
        }
        
        .receipt-header .receipt-number .date-badge {
            justify-content: center;
        }
        
        .business-details {
            grid-template-columns: 1fr 1fr;
        }
        
        .patient-details {
            grid-template-columns: 1fr;
        }
        
        .totals-box {
            width: 100%;
        }
        
        .receipt-body {
            padding: 12px 16px;
        }
        
        .receipt-footer {
            padding: 12px 16px;
        }
        
        .category-totals {
            grid-template-columns: 1fr;
        }
        
        .cashier-info {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .admin-banner {
            flex-direction: column;
            gap: 8px;
            padding: 10px 16px;
        }
    }
    
    @media (max-width: 480px) {
        .business-details {
            grid-template-columns: 1fr;
        }
        
        .receipt-header .logo-area .brand-name {
            font-size: 1.2rem;
        }
        
        .receipt-header .logo-area .logo-img {
            width: 50px;
            height: 50px;
        }
        
        .receipt-table thead th,
        .receipt-table tbody td {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        
        .receipt-table .item-instruction {
            max-width: 150px;
            font-size: 0.5rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header" style="background:linear-gradient(135deg, var(--success), var(--success-dark));border-radius:16px;padding:20px 24px;margin-bottom:24px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;box-shadow:0 4px 20px rgba(5,150,105,0.25);position:relative;overflow:hidden;">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title" style="font-size:1.5rem;font-weight:700;color:white;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0;">
                <i class="fas fa-receipt"></i> 
                <?= $is_otc ? 'OTC Sale Receipt' : 'Payment Receipt' ?>
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:600;text-transform:uppercase;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_otc): ?>
                    <span class="role-badge-display" style="background:rgba(139,92,246,0.3);color:#C4B5FD;font-size:0.6rem;">
                        <i class="fas fa-shopping-cart"></i> OTC
                    </span>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <span class="role-badge-display" style="background:rgba(255,215,0,0.3);color:#FFD700;font-size:0.6rem;">
                        <i class="fas fa-user-shield"></i> ADMIN
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle" style="color:rgba(255,255,255,0.85);font-size:0.85rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0;">
                <?= $is_otc ? 'Over-The-Counter sale receipt' : 'Payment receipt' ?> - <?= htmlspecialchars($receipt_data['branch_name'] ?? $user_branch_name) ?>
                <span style="background:rgba(255,255,255,0.15);color:white;padding:2px 12px;border-radius:20px;font-size:0.6rem;border:1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print" style="position:relative;z-index:1;">
            <button onclick="window.print()" class="btn" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);padding:8px 18px;border-radius:10px;font-weight:500;font-size:0.8rem;transition:all 0.3s;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="pending_bills.php" class="btn" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);padding:8px 18px;border-radius:10px;font-weight:500;font-size:0.8rem;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPT -->
    <!-- ================================================================ -->
    <div class="receipt-wrapper">
        
        <!-- Receipt Header -->
        <div class="receipt-header <?= $is_otc ? 'otc-header' : '' ?>">
            <div class="logo-area">
                <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" 
                     alt="Braick Logo"
                     class="logo-img"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22%3E%3Crect width=%2260%22 height=%2260%22 fill=%22%23065F46%22 rx=%2212%22/%3E%3Ctext x=%2230%22 y=%2238%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
                <div>
                    <div class="brand-name">BRAICK DISPENSARY</div>
                    <div class="brand-sub">Quality Healthcare Services</div>
                </div>
            </div>
            <div class="receipt-number">
                <div class="label">Receipt Number</div>
                <div class="number">#<?= htmlspecialchars($receipt_data['receipt_number'] ?? $receipt_data['bill_number'] ?? 'N/A') ?></div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('d M Y', strtotime($receipt_data['received_at'] ?? $receipt_data['bill_created_at'] ?? 'now')) ?>
                    <i class="fas fa-clock ml-1"></i>
                    <?= date('h:i A', strtotime($receipt_data['received_at'] ?? $receipt_data['bill_created_at'] ?? 'now')) ?>
                </div>
                <?php if ($is_otc): ?>
                    <div class="receipt-type-badge" style="margin-top:6px;">
                        <i class="fas fa-shopping-cart"></i> OTC Sale
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Receipt Body -->
        <div class="receipt-body">
            
            <!-- Business Details -->
            <div class="business-details">
                <div class="detail-item">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_name'] ?? 'Braick Dispensary') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Location</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_location'] ?? 'Dodoma, Tanzania') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_phone'] ?? '+255 759 154 160') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value"><?= htmlspecialchars($receipt_data['branch_email'] ?? 'info@braick.com') ?></span>
                </div>
            </div>
            
            <!-- Patient / Customer Details -->
            <div class="patient-details">
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <span class="label"><i class="fas fa-user"></i> <?= $is_otc ? 'Customer' : 'Patient' ?></span>
                    <span class="value <?= $is_otc ? 'otc-customer' : '' ?>">
                        <?= htmlspecialchars($receipt_data['patient_name'] ?? 'Walk-in Customer') ?>
                        <?php if ($is_otc && !empty($receipt_data['sale_number'])): ?>
                            <span style="font-size:0.6rem;color:var(--text-secondary);margin-left:6px;">
                                Sale: <?= htmlspecialchars($receipt_data['sale_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($receipt_data['patient_code']) && !$is_otc): ?>
                            <span style="font-size:0.6rem;color:var(--text-secondary);margin-left:6px;">
                                ID: <?= htmlspecialchars($receipt_data['patient_code']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($is_otc): ?>
                    <div class="detail-item">
                        <span class="label"><i class="fas fa-phone"></i> Phone</span>
                        <span class="value"><?= htmlspecialchars($receipt_data['patient_phone'] ?? 'N/A') ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label"><i class="fas fa-shopping-cart"></i> Sale #</span>
                        <span class="value"><?= htmlspecialchars($receipt_data['sale_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label"><i class="fas fa-calendar-alt"></i> Date</span>
                        <span class="value"><?= date('d/m/Y', strtotime($receipt_data['received_at'] ?? $receipt_data['bill_created_at'] ?? 'now')) ?></span>
                    </div>
                <?php else: ?>
                    <div class="detail-item">
                        <span class="label"><i class="fas fa-file-invoice"></i> Bill #</span>
                        <span class="value"><?= htmlspecialchars($receipt_data['bill_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label"><i class="fas fa-phone"></i> Phone</span>
                        <span class="value"><?= htmlspecialchars($receipt_data['patient_phone'] ?? 'N/A') ?></span>
                    </div>
                    <?php if (!empty($receipt_data['visit_number'])): ?>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-stethoscope"></i> Visit</span>
                            <span class="value"><?= htmlspecialchars($receipt_data['visit_number']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="label"><i class="fas fa-venus-mars"></i> Gender</span>
                        <span class="value"><?= ucfirst($receipt_data['patient_gender'] ?? 'N/A') ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ================================================================ -->
            <!-- CATEGORY TOTALS -->
            <!-- ================================================================ -->
            <?php if ($is_otc): ?>
                <div class="category-totals" style="grid-template-columns: 1fr;">
                    <div class="category-total-box otc">
                        <span class="cat-label"><i class="fas fa-shopping-cart"></i> OTC Sale</span>
                        <span class="cat-value"><?= $currency ?> <?= number_format($otc_total, 0) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="category-totals">
                    <div class="category-total-box other">
                        <span class="cat-label"><i class="fas fa-file-invoice"></i> Other Bills</span>
                        <span class="cat-value"><?= $currency ?> <?= number_format($other_total, 0) ?></span>
                    </div>
                    <div class="category-total-box medication">
                        <span class="cat-label"><i class="fas fa-pills"></i> Prescriptions</span>
                        <span class="cat-value"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- SECTION: OTC SALE -->
            <!-- ================================================================ -->
            <?php if ($is_otc && count($otc_items) > 0): ?>
                <div class="section-header otc-section">
                    <i class="fas fa-shopping-cart"></i>
                    OTC Sale Items
                    <span class="section-badge"><?= count($otc_items) ?> items</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($otc_total, 0) ?></span>
                </div>
                <div class="receipt-table-wrap">
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th style="width:40%;">Item</th>
                                <th style="width:25%;">Instructions</th>
                                <th class="text-right" style="width:12%;">Qty</th>
                                <th class="text-right" style="width:13%;">Unit Price</th>
                                <th class="text-right" style="width:10%;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($otc_items as $item): ?>
                                <tr>
                                    <td>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? 'N/A') ?></span>
                                        <span class="item-type">OTC Medication</span>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['instructions'])): ?>
                                            <span class="item-instruction">
                                                <i class="fas fa-prescription"></i> <?= htmlspecialchars($item['instructions']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:0.6rem;color:var(--text-secondary);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                                    <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:700;background:var(--bg-body);">
                                <td colspan="4" class="text-right" style="padding:6px 14px;">OTC TOTAL</td>
                                <td class="text-right" style="color:#6D28D9;font-size:0.9rem;"><?= $currency ?> <?= number_format($otc_total, 0) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- SECTION: OTHER BILLS (Non-OTC only) -->
            <!-- ================================================================ -->
            <?php if (!$is_otc && count($other_items) > 0): ?>
                <div class="section-header other-bills">
                    <i class="fas fa-file-invoice"></i>
                    Other Bills
                    <span class="section-badge"><?= count($other_items) ?> items</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($other_total, 0) ?></span>
                </div>
                <div class="receipt-table-wrap">
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th style="width:50%;">Item</th>
                                <th class="text-right" style="width:15%;">Qty</th>
                                <th class="text-right" style="width:20%;">Unit Price</th>
                                <th class="text-right" style="width:15%;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($other_items as $item): ?>
                                <tr>
                                    <td>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></span>
                                        <span class="item-type"><?= ucfirst($item['item_type'] ?? 'other') ?></span>
                                    </td>
                                    <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                                    <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:700;background:var(--bg-body);">
                                <td colspan="3" class="text-right" style="padding:6px 14px;">OTHER BILLS TOTAL</td>
                                <td class="text-right" style="color:var(--section-other-border, #0B5ED7);font-size:0.9rem;"><?= $currency ?> <?= number_format($other_total, 0) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- SECTION: PRESCRIPTIONS (MEDICATIONS) - Non-OTC only -->
            <!-- ================================================================ -->
            <?php if (!$is_otc && count($medication_items) > 0): ?>
                <div class="section-header medications">
                    <i class="fas fa-prescription"></i>
                    Prescriptions (Medications)
                    <span class="section-badge"><?= count($medication_items) ?> items</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                </div>
                <div class="receipt-table-wrap">
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th style="width:30%;">Medication</th>
                                <th style="width:25%;">Instructions</th>
                                <th class="text-right" style="width:12%;">Qty</th>
                                <th class="text-right" style="width:16%;">Unit Price</th>
                                <th class="text-right" style="width:17%;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medication_items as $item): ?>
                                <?php 
                                $instructions = $item['medication_instructions'] ?? $item['instructions'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></span>
                                        <?php if (!empty($item['prescription_number'])): ?>
                                            <span class="item-type">RX: <?= htmlspecialchars($item['prescription_number']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['dosage']) || !empty($item['frequency']) || !empty($item['route'])): ?>
                                            <span class="item-type">
                                                <?= !empty($item['dosage']) ? htmlspecialchars($item['dosage']) . ' mg' : '' ?>
                                                <?= !empty($item['frequency']) ? ' | ' . htmlspecialchars($item['frequency']) : '' ?>
                                                <?= !empty($item['route']) ? ' | ' . htmlspecialchars($item['route']) : '' ?>
                                                <?= !empty($item['duration']) ? ' | ' . htmlspecialchars($item['duration']) : '' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($instructions)): ?>
                                            <span class="item-instruction">
                                                <i class="fas fa-prescription"></i> <?= htmlspecialchars($instructions) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:0.6rem;color:var(--text-secondary);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                                    <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:700;background:var(--bg-body);">
                                <td colspan="4" class="text-right" style="padding:6px 14px;">MEDICATIONS TOTAL</td>
                                <td class="text-right" style="color:var(--section-medication-border, #D97706);font-size:0.9rem;"><?= $currency ?> <?= number_format($medication_total, 0) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- TOTALS -->
            <!-- ================================================================ -->
            <div class="totals-section">
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value"><span class="currency"><?= $currency ?></span> <?= number_format($receipt_data['bill_subtotal'] ?? $receipt_data['bill_total'] ?? 0, 0) ?></span>
                    </div>
                    
                    <?php $discount_amount = (float)($receipt_data['discount_amount'] ?? $receipt_data['total_discount'] ?? 0); ?>
                    <?php if ($discount_amount > 0): ?>
                        <div class="total-row discount-row">
                            <span class="label"><i class="fas fa-tag"></i> <?= $is_otc ? 'Discount' : 'Total Discount' ?></span>
                            <span class="value" style="color:var(--warning);">
                                -<span class="currency"><?= $currency ?></span> <?= number_format($discount_amount, 0) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="total-row">
                        <span class="label">Payment Method</span>
                        <span class="value">
                            <span class="payment-method-badge">
                                <i class="fas <?= ($receipt_data['payment_method'] ?? 'cash') === 'cash' ? 'fa-money-bill-wave' : (($receipt_data['payment_method'] ?? 'cash') === 'm-pesa' ? 'fa-mobile-alt' : 'fa-credit-card') ?>"></i>
                                <?= ucfirst(str_replace('_', ' ', $receipt_data['payment_method'] ?? 'Cash')) ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="total-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="status-badge <?= $receipt_data['bill_status'] ?? 'paid' ?>">
                                <i class="fas fa-circle"></i>
                                <?= ucfirst($receipt_data['bill_status'] ?? 'Paid') ?>
                            </span>
                        </span>
                    </div>
                    
                    <?php if (($receipt_data['bill_balance'] ?? 0) > 0 && !$is_otc): ?>
                        <div class="total-row balance" style="border-top:1px solid var(--border-color);padding-top:6px;margin-top:4px;">
                            <span class="label" style="font-weight:600;">Remaining Balance</span>
                            <span class="value"><span class="currency"><?= $currency ?></span> <?= number_format($receipt_data['bill_balance'] ?? 0, 0) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="total-row grand-total">
                        <span class="label">Total Paid</span>
                        <span class="value"><span class="currency"><?= $currency ?></span> <?= number_format($receipt_data['paid_amount'] ?? $receipt_data['bill_paid'] ?? 0, 0) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Cashier Info -->
            <div class="cashier-info">
                <span>
                    <i class="fas fa-user-check"></i>
                    <strong>Cashier:</strong> <?= htmlspecialchars($receipt_data['cashier_name'] ?? $user_full_name) ?>
                </span>
                <span>
                    <i class="fas fa-calendar-check"></i>
                    <strong>Date:</strong> <?= date('d/m/Y h:i A', strtotime($receipt_data['received_at'] ?? $receipt_data['bill_created_at'] ?? 'now')) ?>
                </span>
                <span>
                    <i class="fas fa-fingerprint"></i>
                    <strong>Transaction:</strong> #<?= $payment_id > 0 ? $payment_id : ($sale_id > 0 ? 'OTC-' . $sale_id : $bill_id) ?>
                </span>
                <?php if ($is_otc): ?>
                    <span>
                        <i class="fas fa-shopping-cart"></i>
                        <strong>Sale:</strong> <?= htmlspecialchars($receipt_data['sale_number'] ?? 'N/A') ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div class="thank-you">
                <i class="fas fa-heart" style="color:var(--danger);opacity:0.6;"></i>
                <?= $is_otc ? 'Thank You for Your Purchase at Braick Dispensary' : 'Thank You for Choosing Braick Dispensary' ?>
            </div>
            <div class="footer-text">
                This is a computer generated receipt. For inquiries, contact 
                <strong><?= htmlspecialchars($receipt_data['branch_phone'] ?? '+255 759 154 160') ?></strong>
            </div>
            <div class="footer-copy">
                <?= date('Y') ?> &copy; Braick Dispensary - All rights reserved
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- ADMIN BANNER - Shows for all users -->
        <!-- ================================================================ -->
        <div class="admin-banner">
            <span class="admin-badge">
                <i class="fas fa-shield-alt"></i> ADMIN
            </span>
            <span class="admin-text">
                <i class="fas fa-building"></i> Braick Dispensary Management System v2.0
            </span>
            <span class="admin-text">
                <i class="fas fa-user-check"></i> Authorized: <?= htmlspecialchars($user_full_name) ?> 
                (<?= strtoupper($user_role) ?>)
            </span>
            <span class="admin-version">
                <i class="fas fa-code-branch"></i> v2.0.<?= date('Ymd') ?>
            </span>
            <?php if ($is_admin): ?>
                <span class="admin-badge" style="background:rgba(255,215,0,0.3);color:#FFD700;">
                    <i class="fas fa-crown"></i> SUPER ADMIN
                </span>
            <?php endif; ?>
            <?php if ($is_reception): ?>
                <span class="admin-badge" style="background:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-eye"></i> RECEPTION
                </span>
            <?php endif; ?>
            <span class="admin-text" style="font-size:0.55rem;opacity:0.5;">
                <i class="fas fa-print"></i> Printed: <?= date('d/m/Y h:i A') ?>
            </span>
        </div>
        
    </div>

    <!-- Footer -->
    <footer class="footer" style="padding:12px 0;border-top:1px solid var(--border-color);margin-top:20px;text-align:center;font-size:0.6rem;color:var(--text-secondary);">
        <p>
            <span style="color:var(--success);font-weight:600;">Braick Dispensary</span>
            <span style="opacity:0.3;margin:0 6px;">|</span>
            <?= $is_otc ? 'OTC Receipt' : 'Receipt' ?>
            <span style="opacity:0.3;margin:0 6px;">|</span>
            👤 <?= htmlspecialchars($user_full_name) ?>
            <?php if ($is_admin): ?>
                <span style="color:#FFD700;font-size:0.5rem;margin-left:4px;">⭐ Admin</span>
            <?php endif; ?>
            <?php if ($is_reception): ?>
                <span style="color:#34D399;font-size:0.5rem;margin-left:4px;">👀 Reception</span>
            <?php endif; ?>
            <span style="opacity:0.3;margin:0 6px;">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;position:fixed;bottom:24px;right:24px;padding:14px 20px;border-radius:12px;z-index:999;max-width:380px;transform:translateY(100px);opacity:0;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);display:flex;align-items:center;gap:12px;color:white;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.9rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.8rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        setTimeout(function() { toast.classList.add('show'); }, 50);
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c🧾 Braick - Receipt (Full Support)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Categories: Other Bills, Prescriptions, OTC Sales', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Instructions shown for each medication item', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Admin banner at bottom with user role', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ OTC: Customer name, phone, items, discount', 'font-size:13px; color:#8B5CF6;');
    <?php if ($is_otc): ?>
        console.log('%c🛒 OTC Sale: <?= htmlspecialchars($receipt_data['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#8B5CF6;');
        console.log('%c👤 Customer: <?= htmlspecialchars($receipt_data['patient_name'] ?? 'Walk-in') ?>', 'font-size:13px; color:#8B5CF6;');
        console.log('%c📞 Phone: <?= htmlspecialchars($receipt_data['patient_phone'] ?? 'N/A') ?>', 'font-size:13px; color:#8B5CF6;');
    <?php endif; ?>
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= strtoupper($user_role) ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($receipt_data['paid_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>