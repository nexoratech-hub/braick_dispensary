<?php
// ================================================================
// FILE: frontend/pages/cashier/print_receipt.php
// CASHIER - PRINT RECEIPT 
// SUPPORTS: Regular Bills (with visit_id) AND OTC Sales
// WITH BEAUTIFUL DESIGN AND PRINT BUTTON
// FIXED: Medication instructions show dosage, route, frequency, instructions
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_reception = ($user_role === 'reception');
$is_admin = ($user_role === 'admin');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'regular';
$auto_print = isset($_GET['print']) && $_GET['print'] == 1;

$bill = null;
$all_items = [];
$medication_items = [];
$other_items = [];
$otc_items = [];
$otc_sale = null;
$payment = null;
$settings = [];
$logo_base64 = '';
$logo_available = false;
$error_message = '';
$has_error = false;
$currency = 'TSh';
$is_otc = false;
$site_name = 'Braick Dispensary';
$site_phone = '+255 700 000 000';
$site_email = 'info@braick.com';

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

$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}
$admin_phones_display = !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001');

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
    // CASE 1: OTC SALE
    // ================================================================
    if ($type === 'otc' || $sale_id > 0) {
        if ($sale_id == 0 && $bill_id > 0) {
            $stmt = $db->prepare("SELECT id FROM otc_sales WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $otc_check = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($otc_check) {
                $sale_id = $otc_check['id'];
            }
        }
        
        if ($sale_id > 0) {
            $stmt = $db->prepare("
                SELECT 
                    os.*,
                    u.full_name as cashier_name,
                    br.name as branch_name,
                    br.location as branch_location,
                    br.phone as branch_phone,
                    br.email as branch_email
                FROM otc_sales os
                LEFT JOIN users u ON os.sold_by = u.id
                LEFT JOIN branches br ON os.branch_id = br.id
                WHERE os.id = ? AND os.branch_id = ?
            ");
            $stmt->execute([$sale_id, $user_branch_id]);
            $otc_sale = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otc_sale) {
                $is_otc = true;
                
                $stmt = $db->prepare("
                    SELECT * FROM otc_sale_items 
                    WHERE sale_id = ?
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$sale_id]);
                $otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($otc_sale['bill_id'] > 0) {
                    $stmt = $db->prepare("
                        SELECT * FROM payments 
                        WHERE bill_id = ? 
                        ORDER BY received_at DESC LIMIT 1
                    ");
                    $stmt->execute([$otc_sale['bill_id']]);
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                $bill = [
                    'id' => $otc_sale['id'],
                    'bill_number' => 'OTC-' . $otc_sale['sale_number'],
                    'sale_number' => $otc_sale['sale_number'],
                    'patient_id' => $otc_sale['patient_id'],
                    'patient_name' => $otc_sale['customer_name'] ?? 'Walk-in Customer',
                    'patient_code' => $otc_sale['patient_id'] ?? 'N/A',
                    'phone' => $otc_sale['customer_phone'] ?? 'N/A',
                    'gender' => null,
                    'address' => null,
                    'date_of_birth' => null,
                    'visit_number' => null,
                    'visit_type' => 'OTC Sale',
                    'subtotal' => $otc_sale['subtotal'],
                    'total_amount' => $otc_sale['total_amount'],
                    'paid_amount' => $payment['amount'] ?? $otc_sale['total_amount'],
                    'balance' => 0,
                    'status' => 'paid',
                    'pharmacy_discount' => 0,
                    'cashier_discount' => 0,
                    'total_discount' => $otc_sale['discount_amount'] ?? 0,
                    'payment_method' => $payment['payment_method'] ?? $otc_sale['payment_method'] ?? 'cash',
                    'created_at' => $otc_sale['created_at'],
                    'updated_at' => $otc_sale['updated_at'],
                    'cashier_name' => $otc_sale['cashier_name'] ?? $user_full_name,
                    'branch_name' => $otc_sale['branch_name'] ?? $user_branch_name,
                    'branch_location' => $otc_sale['branch_location'] ?? 'Dodoma, Tanzania',
                    'branch_phone' => $otc_sale['branch_phone'] ?? '+255 759 154 160',
                    'branch_email' => $otc_sale['branch_email'] ?? 'info@braick.com',
                    'is_otc' => true,
                    'reference_id' => $otc_sale['id']
                ];
            } else {
                $error_message = 'OTC sale not found.';
                $has_error = true;
            }
        } else {
            $error_message = 'Invalid OTC sale ID.';
            $has_error = true;
        }
    }
    
    // ================================================================
    // CASE 2: REGULAR BILL - FIXED QUERY FOR MEDICATION INSTRUCTIONS
    // ================================================================
    if (!$is_otc && $bill_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                b.*,
                b.discount_amount as pharmacy_discount,
                b.cashier_discount,
                b.total_discount,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone,
                p.address,
                p.gender,
                p.date_of_birth,
                u.full_name as cashier_name,
                br.name as branch_name,
                br.location as branch_location,
                br.phone as branch_phone,
                br.email as branch_email,
                v.visit_number,
                v.visit_type,
                v.created_at as visit_date
            FROM bills b
            LEFT JOIN patients p ON b.patient_id = p.id
            LEFT JOIN users u ON b.created_by = u.id
            LEFT JOIN branches br ON b.branch_id = br.id
            LEFT JOIN visits v ON b.visit_id = v.id
            WHERE b.id = ? AND b.branch_id = ?
        ");
        $stmt->execute([$bill_id, $user_branch_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill) {
            // ================================================================
            // STEP 1: Get bill items
            // ================================================================
            $stmt = $db->prepare("
                SELECT 
                    bi.*
                FROM bill_items bi
                WHERE bi.bill_id = ? AND bi.status != 'cancelled'
                ORDER BY 
                    CASE 
                        WHEN bi.item_type = 'medication' THEN 1 
                        ELSE 2 
                    END,
                    bi.created_at ASC
            ");
            $stmt->execute([$bill_id]);
            $bill_items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ================================================================
            // STEP 2: Get prescriptions and their items for this visit
            // ================================================================
            // First get the visit_id from the bill
            $visit_id = $bill['visit_id'] ?? 0;
            
            $prescription_items_map = [];
            $prescription_numbers = [];
            
            if ($visit_id > 0) {
                // Get all prescriptions for this visit
                $stmt = $db->prepare("
                    SELECT 
                        p.id as prescription_id,
                        p.prescription_number,
                        p.status as prescription_status,
                        pi.id as item_id,
                        pi.medication_name,
                        pi.dosage,
                        pi.frequency,
                        pi.quantity as rx_quantity,
                        pi.duration,
                        pi.route,
                        pi.instructions,
                        pi.pharmacy_instructions,
                        pi.unit_price,
                        pi.total_price as rx_total_price,
                        pi.inventory_id
                    FROM prescriptions p
                    LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                    WHERE p.visit_id = ?
                    ORDER BY p.created_at DESC, pi.id ASC
                ");
                $stmt->execute([$visit_id]);
                $prescription_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Build a map of medication_name -> prescription item details
                foreach ($prescription_data as $rx) {
                    if (!empty($rx['medication_name'])) {
                        $key = trim($rx['medication_name']);
                        // Store the first occurrence with the most details
                        if (!isset($prescription_items_map[$key]) || 
                            (empty($prescription_items_map[$key]['instructions']) && !empty($rx['instructions']))) {
                            $prescription_items_map[$key] = $rx;
                        }
                        // Store prescription number
                        if (!empty($rx['prescription_number']) && !in_array($rx['prescription_number'], $prescription_numbers)) {
                            $prescription_numbers[] = $rx['prescription_number'];
                        }
                    }
                }
            }
            
            // ================================================================
            // STEP 3: Merge bill items with prescription details
            // ================================================================
            $all_items = [];
            foreach ($bill_items_data as $item) {
                // Try to find matching prescription item by medication name
                $item_name = trim($item['item_name'] ?? '');
                // Remove batch info from item name for matching
                $clean_name = preg_replace('/\s*\(Batch:.*\)/', '', $item_name);
                $clean_name = trim($clean_name);
                
                $rx_details = null;
                if (!empty($clean_name) && isset($prescription_items_map[$clean_name])) {
                    $rx_details = $prescription_items_map[$clean_name];
                } elseif (!empty($item_name) && isset($prescription_items_map[$item_name])) {
                    $rx_details = $prescription_items_map[$item_name];
                } else {
                    // Try partial match
                    foreach ($prescription_items_map as $rx_name => $rx_data) {
                        if (stripos($item_name, $rx_name) !== false || stripos($rx_name, $item_name) !== false) {
                            $rx_details = $rx_data;
                            break;
                        }
                    }
                }
                
                // Merge the data
                $merged_item = $item;
                if ($rx_details) {
                    $merged_item['dosage'] = $rx_details['dosage'] ?? '';
                    $merged_item['frequency'] = $rx_details['frequency'] ?? '';
                    $merged_item['route'] = $rx_details['route'] ?? '';
                    $merged_item['duration'] = $rx_details['duration'] ?? '';
                    $merged_item['rx_quantity'] = $rx_details['rx_quantity'] ?? $item['quantity'] ?? 1;
                    $merged_item['instructions'] = $rx_details['instructions'] ?? '';
                    $merged_item['pharmacy_instructions'] = $rx_details['pharmacy_instructions'] ?? '';
                    $merged_item['prescription_number'] = $rx_details['prescription_number'] ?? '';
                    $merged_item['prescription_status'] = $rx_details['prescription_status'] ?? '';
                    $merged_item['rx_total_price'] = $rx_details['rx_total_price'] ?? $item['total_price'] ?? 0;
                } else {
                    $merged_item['dosage'] = '';
                    $merged_item['frequency'] = '';
                    $merged_item['route'] = '';
                    $merged_item['duration'] = '';
                    $merged_item['rx_quantity'] = $item['quantity'] ?? 1;
                    $merged_item['instructions'] = '';
                    $merged_item['pharmacy_instructions'] = '';
                    $merged_item['prescription_number'] = '';
                    $merged_item['prescription_status'] = '';
                    $merged_item['rx_total_price'] = $item['total_price'] ?? 0;
                }
                
                $all_items[] = $merged_item;
            }
            
            // Separate medications from other items
            $medication_items = [];
            $other_items = [];
            foreach ($all_items as $item) {
                if ($item['item_type'] === 'medication') {
                    $medication_items[] = $item;
                } else {
                    $other_items[] = $item;
                }
            }
            
            // Get payment info
            if ($payment_id > 0) {
                $stmt = $db->prepare("SELECT * FROM payments WHERE id = ? AND bill_id = ?");
                $stmt->execute([$payment_id, $bill_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$payment) {
                $stmt = $db->prepare("SELECT * FROM payments WHERE bill_id = ? ORDER BY received_at DESC LIMIT 1");
                $stmt->execute([$bill_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $bill['is_otc'] = false;
            $bill['reference_id'] = $bill_id;
        } else {
            $error_message = 'Bill not found.';
            $has_error = true;
        }
    }
    
    // ================================================================
    // CASE 3: Try to find by payment_id
    // ================================================================
    if (!$is_otc && !$bill && $payment_id > 0) {
        $stmt = $db->prepare("SELECT bill_id FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment_check && $payment_check['bill_id'] > 0) {
            $stmt = $db->prepare("SELECT id FROM otc_sales WHERE bill_id = ?");
            $stmt->execute([$payment_check['bill_id']]);
            $otc_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otc_check) {
                header('Location: print_receipt.php?type=otc&sale_id=' . $otc_check['id'] . '&print=' . ($auto_print ? 1 : 0));
                exit;
            } else {
                header('Location: print_receipt.php?bill_id=' . $payment_check['bill_id'] . '&print=' . ($auto_print ? 1 : 0));
                exit;
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $has_error = true;
}

// ================================================================
// LOGO
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

// Calculate totals
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

// Check if we should show the receipt
$show_receipt = !$has_error && $bill;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_otc ? 'OTC Receipt' : 'Receipt' ?> - <?= htmlspecialchars($site_name) ?></title>
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
            max-width: 450px;
            margin: 0 auto;
        }
        
        .page-header {
            max-width: 450px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .page-header .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .page-header .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        
        .page-header .btn-back {
            background: white;
            color: #64748B;
            border: 2px solid #E2E8F0;
        }
        
        .page-header .btn-back:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
            transform: translateY(-2px);
        }
        
        .page-header .btn-print {
            background: #0B5ED7;
            color: white;
            border: 2px solid #0B5ED7;
        }
        
        .page-header .btn-print:hover {
            background: #0A4CA8;
            border-color: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .page-header .btn-print.otc-btn {
            background: #7C3AED;
            border-color: #7C3AED;
        }
        
        .page-header .btn-print.otc-btn:hover {
            background: #6D28D9;
            border-color: #6D28D9;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        .receipt {
            background: white;
            padding: 24px 28px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .receipt::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #0B5ED7, #059669, #7C3AED);
        }
        
        .receipt.otc-receipt::before {
            background: linear-gradient(90deg, #7C3AED, #8B5CF6, #A78BFA);
        }
        
        .receipt-header {
            text-align: center;
            padding-bottom: 14px;
            border-bottom: 2px dashed #E2E8F0;
            margin-bottom: 14px;
        }
        
        .receipt-header.otc-header {
            border-bottom-color: #7C3AED;
        }
        
        .receipt-logo {
            display: block;
            margin: 0 auto 8px auto;
            max-width: 100px;
            max-height: 60px;
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
        
        .receipt-logo-text.otc-text {
            color: #6D28D9;
        }
        
        .receipt-logo-text.otc-text span {
            color: #7C3AED;
        }
        
        .receipt-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1E293B;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .receipt-title.otc-title {
            color: #7C3AED;
        }
        
        .receipt-subtitle {
            font-size: 0.65rem;
            color: #64748B;
            margin-top: 2px;
            line-height: 1.4;
        }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #CBD5E1;
            margin: 6px 0;
        }
        
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
        
        .receipt-row .value.bold { font-weight: 700; }
        .receipt-row .value.otc-value { color: #7C3AED; }
        .receipt-row .value.paid-value { color: #059669; }
        .receipt-row .value.balance-value { color: #DC2626; }
        
        .section-header {
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 0;
            margin: 8px 0 4px 0;
            border-bottom: 2px solid;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header.other {
            color: #0B5ED7;
            border-bottom-color: #0B5ED7;
        }
        
        .section-header.medication {
            color: #D97706;
            border-bottom-color: #D97706;
        }
        
        .section-header.otc-section {
            color: #7C3AED;
            border-bottom-color: #7C3AED;
        }
        
        .section-header .section-total {
            font-size: 0.75rem;
        }
        
        .receipt-items {
            margin: 4px 0;
            padding: 4px 0;
        }
        
        .receipt-item {
            padding: 6px 0;
            border-bottom: 1px dotted #E2E8F0;
        }
        
        .receipt-item:last-child {
            border-bottom: none;
        }
        
        .receipt-item .item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .receipt-item .item-name {
            flex: 1;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .receipt-item .item-price {
            font-weight: 600;
            white-space: nowrap;
            margin-left: 8px;
            font-size: 0.75rem;
        }
        
        .receipt-item .item-qty {
            color: #64748B;
            margin-right: 4px;
            font-size: 0.6rem;
        }
        
        .receipt-item .item-type {
            font-size: 0.5rem;
            color: #94A3B8;
            display: block;
            margin-top: 1px;
        }
        
        /* ================================================================
           MEDICATION DETAILS - DOSAGE, ROUTE, FREQUENCY, INSTRUCTIONS
           ================================================================ */
        .receipt-item .med-details {
            display: block;
            font-size: 0.55rem;
            color: #64748B;
            margin-top: 2px;
            padding-left: 4px;
        }
        
        .receipt-item .med-details .med-tag {
            display: inline-block;
            padding: 0 8px;
            border-radius: 4px;
            margin-right: 4px;
            font-size: 0.5rem;
            font-weight: 600;
            color: #475569;
            background: #F1F5F9;
        }
        
        .receipt-item .med-details .med-tag.dosage-tag {
            background: #DBEAFE;
            color: #0B5ED7;
        }
        
        .receipt-item .med-details .med-tag.route-tag {
            background: #D1FAE5;
            color: #059669;
        }
        
        .receipt-item .med-details .med-tag.freq-tag {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .receipt-item .med-details .med-tag.duration-tag {
            background: #EDE9FE;
            color: #7C3AED;
        }
        
        .receipt-item .med-instruction-box {
            display: block;
            font-size: 0.6rem;
            color: #64748B;
            font-style: italic;
            padding: 4px 10px;
            border-left: 3px solid #D97706;
            margin-top: 4px;
            background: #FFFBEB;
            border-radius: 4px;
        }
        
        .receipt-item .med-instruction-box i {
            color: #D97706;
            margin-right: 4px;
        }
        
        .receipt-item .med-instruction-box.otc-instruction {
            border-left-color: #7C3AED;
            background: #EDE9FE;
        }
        
        .receipt-item .med-instruction-box.otc-instruction i {
            color: #7C3AED;
        }
        
        .receipt-item .pharmacy-instruction {
            display: block;
            font-size: 0.55rem;
            color: #0B5ED7;
            padding: 3px 10px;
            border-left: 3px solid #0B5ED7;
            margin-top: 3px;
            background: #E8F0FE;
            border-radius: 4px;
        }
        
        .receipt-item .pharmacy-instruction i {
            color: #0B5ED7;
        }
        
        /* TOTALS */
        .receipt-totals {
            margin: 8px 0 4px 0;
            padding-top: 8px;
            border-top: 2px dashed #E2E8F0;
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
            border-top: 2px solid #1E293B;
            padding-top: 6px;
            margin-top: 4px;
            font-size: 0.9rem;
            font-weight: 700;
        }
        
        .receipt-grand-total .value {
            color: #0B5ED7;
            font-size: 1rem;
        }
        
        .receipt-grand-total .value.otc-total {
            color: #7C3AED;
        }
        
        .receipt-grand-total .value.paid-total {
            color: #059669;
        }
        
        .discount-value {
            color: #DC2626;
        }
        
        .category-totals {
            display: flex;
            gap: 8px;
            margin: 8px 0;
            padding: 4px 0;
        }
        
        .category-totals .cat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            flex: 1;
            text-align: center;
        }
        
        .category-totals .cat-item.other {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .category-totals .cat-item.medication {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .category-totals .cat-item.otc-cat {
            background: #EDE9FE;
            color: #7C3AED;
        }
        
        .category-totals .cat-item .cat-label {
            font-size: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .category-totals .cat-item .cat-value {
            font-weight: 700;
            font-size: 0.8rem;
            margin-top: 2px;
        }
        
        .payment-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .payment-status.paid { background: #D1FAE5; color: #059669; }
        .payment-status.pending { background: #FEF3C7; color: #D97706; }
        .payment-status.partial { background: #DBEAFE; color: #0B5ED7; }
        .payment-status.cancelled { background: #FEE2E2; color: #DC2626; }
        .payment-status.otc-paid { background: #EDE9FE; color: #7C3AED; }
        
        .payment-method-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            background: #F1F5F9;
            color: #475569;
        }
        
        .receipt-footer {
            text-align: center;
            font-size: 0.6rem;
            color: #94A3B8;
            padding-top: 12px;
            border-top: 2px dashed #E2E8F0;
            margin-top: 12px;
        }
        
        .receipt-footer .footer-brand {
            color: #0B5ED7;
            font-weight: 700;
            font-size: 0.7rem;
        }
        
        .receipt-footer .footer-brand.otc-brand {
            color: #7C3AED;
        }
        
        .receipt-footer .footer-divider {
            margin: 4px 0;
            border: none;
            border-top: 1px dashed #E2E8F0;
        }
        
        .receipt-footer .thank-you {
            font-size: 0.7rem;
            font-weight: 600;
            color: #1E293B;
            margin: 4px 0;
        }
        
        .receipt-footer .thank-you i {
            color: #DC2626;
            opacity: 0.6;
        }
        
        .admin-contact-line {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 0.5rem;
            color: #94A3B8;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #E2E8F0;
        }
        
        .admin-contact-line span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .admin-contact-line i {
            color: #059669;
        }
        
        .branch-info {
            text-align: center;
            font-size: 0.55rem;
            color: #64748B;
            margin: 4px 0;
        }
        
        .branch-info i {
            color: #0B5ED7;
        }
        
        .error-box {
            max-width: 450px;
            margin: 0 auto;
            background: #FEF2F2;
            border: 2px solid #FCA5A5;
            border-radius: 16px;
            padding: 32px 28px;
            text-align: center;
            color: #991B1B;
        }
        
        .error-box i { font-size: 3rem; display: block; margin-bottom: 12px; color: #DC2626; }
        .error-box h3 { font-size: 1.2rem; margin-bottom: 6px; color: #991B1B; }
        .error-box p { font-size: 0.85rem; color: #7F1D1D; }
        
        .error-box .back-btn {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 28px;
            background: #DC2626;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            border: none;
            cursor: pointer;
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
        
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .receipt-wrapper { max-width: 100%; margin: 0; }
            .receipt { border-radius: 0; box-shadow: none; padding: 16px 20px; }
            .receipt::before { display: none; }
            .page-header { display: none !important; }
            .no-print { display: none !important; }
            .error-box { display: none !important; }
            
            .category-totals .cat-item.other,
            .category-totals .cat-item.medication,
            .category-totals .cat-item.otc-cat {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .receipt-item .med-instruction-box {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .receipt-item .pharmacy-instruction {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .payment-status.paid,
            .payment-status.otc-paid {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .payment-method-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .receipt-logo {
                max-width: 80px;
                max-height: 50px;
            }
            
            .receipt-logo-text {
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 480px) {
            .receipt { padding: 16px 18px; border-radius: 12px; }
            .receipt-logo-text { font-size: 1.2rem; }
            .page-header { flex-direction: column; align-items: stretch; }
            .page-header .btn-group { justify-content: center; }
            .page-header .btn { flex: 1; justify-content: center; }
            .category-totals { flex-direction: column; }
            .category-totals .cat-item { flex: 1; }
            .receipt-title { font-size: 0.85rem; }
            .receipt-item .item-name { font-size: 0.7rem; }
            .receipt-item .item-price { font-size: 0.7rem; }
            .receipt-grand-total { font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">

    <!-- ================================================================ -->
    <!-- PAGE HEADER WITH PRINT BUTTONS -->
    <!-- ================================================================ -->
    <div class="page-header no-print">
        <a href="paid_bills.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-print <?= $is_otc ? 'otc-btn' : '' ?>">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ERROR -->
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
    <div class="receipt <?= $is_otc ? 'otc-receipt' : '' ?>" id="receipt">
        
        <!-- HEADER -->
        <div class="receipt-header <?= $is_otc ? 'otc-header' : '' ?>">
            <?php if ($logo_available): ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Logo" class="receipt-logo">
            <?php else: ?>
                <div class="receipt-logo-text <?= $is_otc ? 'otc-text' : '' ?>">
                    Braick <span>Dispensary</span>
                </div>
            <?php endif; ?>
            
            <div class="receipt-title <?= $is_otc ? 'otc-title' : '' ?>">
                <?= $is_otc ? '🧾 OTC Sale Receipt' : '🧾 Official Receipt' ?>
            </div>
            <div class="receipt-subtitle">
                <?= htmlspecialchars($bill['branch_name'] ?? $site_name) ?>
                <?php if (!empty($bill['branch_location'])): ?>
                    <br><i class="fas fa-map-marker-alt" style="font-size:0.5rem;"></i> 
                    <?= htmlspecialchars($bill['branch_location']) ?>
                <?php endif; ?>
            </div>
            <div class="branch-info">
                <i class="fas fa-phone"></i> <?= htmlspecialchars($site_phone) ?> &nbsp;|&nbsp;
                <i class="fas fa-envelope"></i> <?= htmlspecialchars($site_email) ?>
            </div>
            <div class="admin-contact-line">
                <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
            </div>
        </div>
        
        <!-- BODY -->
        <div class="receipt-body">
            
            <!-- Receipt Info -->
            <div class="receipt-row">
                <span class="label">Receipt #</span>
                <span class="value bold">
                    <?php if ($is_otc): ?>
                        <?= htmlspecialchars('OTC-REC-' . date('Ymd') . '-' . str_pad($bill['reference_id'] ?? 0, 6, '0', STR_PAD_LEFT)) ?>
                    <?php else: ?>
                        <?= htmlspecialchars('REC-' . date('Ymd') . '-' . str_pad($bill_id, 6, '0', STR_PAD_LEFT)) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label"><?= $is_otc ? 'Sale #' : 'Bill #' ?></span>
                <span class="value <?= $is_otc ? 'otc-value' : '' ?>">
                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label">Date & Time</span>
                <span class="value"><?= isset($bill['created_at']) ? date('d/m/Y h:i A', strtotime($bill['created_at'])) : 'N/A' ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="payment-status <?= $is_otc ? 'otc-paid' : ($bill['status'] ?? 'paid') ?>">
                        <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                        <?= $is_otc ? 'OTC Paid' : ucfirst($bill['status'] ?? 'Paid') ?>
                    </span>
                </span>
            </div>
            
            <hr class="receipt-divider">
            
            <!-- Patient / Customer Info -->
            <div class="receipt-row">
                <span class="label"><?= $is_otc ? '👤 Customer' : '👤 Patient' ?></span>
                <span class="value <?= $is_otc ? 'otc-value' : '' ?>">
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label"><?= $is_otc ? 'Customer ID' : 'Patient ID' ?></span>
                <span class="value"><?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($bill['phone']) && $bill['phone'] !== 'N/A'): ?>
            <div class="receipt-row">
                <span class="label"><i class="fas fa-phone"></i> Phone</span>
                <span class="value"><?= htmlspecialchars($bill['phone']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($bill['visit_number']) && !$is_otc): ?>
            <div class="receipt-row">
                <span class="label"><i class="fas fa-stethoscope"></i> Visit #</span>
                <span class="value"><?= htmlspecialchars($bill['visit_number']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($is_otc && !empty($bill['sale_number'])): ?>
            <div class="receipt-row">
                <span class="label"><i class="fas fa-shopping-cart"></i> Sale #</span>
                <span class="value otc-value"><?= htmlspecialchars($bill['sale_number']) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- CATEGORY TOTALS -->
            <!-- ================================================================ -->
            <?php if ($is_otc): ?>
                <div class="category-totals" style="grid-template-columns: 1fr;">
                    <div class="cat-item otc-cat" style="flex:1;">
                        <span class="cat-label"><i class="fas fa-shopping-cart"></i> OTC Sale</span>
                        <span class="cat-value"><?= $currency ?> <?= number_format($otc_total, 0) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="category-totals">
                    <div class="cat-item other">
                        <span class="cat-label"><i class="fas fa-file-invoice"></i> Other Bills</span>
                        <span class="cat-value"><?= $currency ?> <?= number_format($other_total, 0) ?></span>
                    </div>
                    <div class="cat-item medication">
                        <span class="cat-label"><i class="fas fa-pills"></i> Medications</span>
                        <span class="cat-value"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- OTC ITEMS SECTION -->
            <!-- ================================================================ -->
            <?php if ($is_otc && count($otc_items) > 0): ?>
                <div class="section-header otc-section">
                    <span><i class="fas fa-shopping-cart"></i> OTC Items</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($otc_total, 0) ?></span>
                </div>
                <div class="receipt-items">
                    <?php foreach ($otc_items as $item): ?>
                        <div class="receipt-item">
                            <div class="item-top">
                                <span class="item-name">
                                    <?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? 'N/A') ?>
                                    <?php if (isset($item['quantity']) && $item['quantity'] > 1): ?>
                                        <span class="item-qty">x<?= $item['quantity'] ?></span>
                                    <?php endif; ?>
                                    <span class="item-type">OTC Medication</span>
                                </span>
                                <span class="item-price">
                                    <?= $currency ?> <?= number_format($item['total_price'] ?? $item['unit_price'] ?? 0, 0) ?>
                                </span>
                            </div>
                            <?php if (!empty($item['instructions'])): ?>
                                <span class="med-instruction-box otc-instruction">
                                    <i class="fas fa-prescription"></i>
                                    <?= htmlspecialchars($item['instructions']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- OTHER BILLS SECTION -->
            <!-- ================================================================ -->
            <?php if (!$is_otc && count($other_items) > 0): ?>
                <div class="section-header other">
                    <span><i class="fas fa-file-invoice"></i> Other Bills</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($other_total, 0) ?></span>
                </div>
                <div class="receipt-items">
                    <?php foreach ($other_items as $item): ?>
                        <div class="receipt-item">
                            <div class="item-top">
                                <span class="item-name">
                                    <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                    <?php if (isset($item['quantity']) && $item['quantity'] > 1): ?>
                                        <span class="item-qty">x<?= $item['quantity'] ?></span>
                                    <?php endif; ?>
                                    <span class="item-type"><?= ucfirst($item['item_type'] ?? 'other') ?></span>
                                </span>
                                <span class="item-price">
                                    <?= $currency ?> <?= number_format($item['total_price'] ?? $item['unit_price'] ?? 0, 0) ?>
                                </span>
                            </div>
                            <?php if (!empty($item['description'])): ?>
                                <span style="font-size:0.55rem;color:#94A3B8;display:block;padding-left:4px;">
                                    <?= htmlspecialchars($item['description']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- MEDICATIONS SECTION - WITH FULL INSTRUCTIONS -->
            <!-- ================================================================ -->
            <?php if (!$is_otc && count($medication_items) > 0): ?>
                <div class="section-header medication">
                    <span><i class="fas fa-prescription"></i> Prescriptions (Medications)</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                </div>
                <div class="receipt-items">
                    <?php foreach ($medication_items as $item): 
                        // Get medication details from prescription_items
                        $dosage = $item['dosage'] ?? '';
                        $frequency = $item['frequency'] ?? '';
                        $route = $item['route'] ?? '';
                        $duration = $item['duration'] ?? '';
                        $rx_quantity = $item['rx_quantity'] ?? $item['quantity'] ?? 1;
                        $instructions = $item['instructions'] ?? '';
                        $pharmacy_instructions = $item['pharmacy_instructions'] ?? '';
                        $prescription_number = $item['prescription_number'] ?? '';
                        
                        // Check if we have any medication details to show
                        $has_med_details = !empty($dosage) || !empty($frequency) || !empty($route) || !empty($duration);
                    ?>
                        <div class="receipt-item">
                            <div class="item-top">
                                <span class="item-name">
                                    <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                    <?php if ($rx_quantity > 1): ?>
                                        <span class="item-qty">x<?= $rx_quantity ?></span>
                                    <?php endif; ?>
                                    <span class="item-type">
                                        <?= !empty($prescription_number) ? 'RX: ' . htmlspecialchars($prescription_number) : '' ?>
                                    </span>
                                </span>
                                <span class="item-price">
                                    <?= $currency ?> <?= number_format($item['total_price'] ?? $item['rx_total_price'] ?? 0, 0) ?>
                                </span>
                            </div>
                            
                            <!-- ================================================================ -->
                            <!-- MEDICATION DETAILS: DOSAGE, ROUTE, FREQUENCY, DURATION -->
                            <!-- ================================================================ -->
                            <?php if ($has_med_details): ?>
                                <span class="med-details">
                                    <?php if (!empty($dosage)): ?>
                                        <span class="med-tag dosage-tag">💊 <?= htmlspecialchars($dosage) ?> mg</span>
                                    <?php endif; ?>
                                    <?php if (!empty($route)): ?>
                                        <span class="med-tag route-tag">📌 <?= htmlspecialchars($route) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($frequency)): ?>
                                        <span class="med-tag freq-tag">⏰ <?= htmlspecialchars($frequency) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($duration)): ?>
                                        <span class="med-tag duration-tag">📅 <?= htmlspecialchars($duration) ?> days</span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            
                            <!-- ================================================================ -->
                            <!-- INSTRUCTIONS (From prescription_items) -->
                            <!-- ================================================================ -->
                            <?php if (!empty($instructions)): ?>
                                <span class="med-instruction-box">
                                    <i class="fas fa-prescription"></i>
                                    <strong>Instructions:</strong> <?= htmlspecialchars($instructions) ?>
                                </span>
                            <?php endif; ?>
                            
                            <!-- ================================================================ -->
                            <!-- PHARMACY INSTRUCTIONS (If any) -->
                            <!-- ================================================================ -->
                            <?php if (!empty($pharmacy_instructions)): ?>
                                <span class="pharmacy-instruction">
                                    <i class="fas fa-pharmacy"></i>
                                    <strong>Pharmacy:</strong> <?= htmlspecialchars($pharmacy_instructions) ?>
                                </span>
                            <?php endif; ?>
                            
                            <!-- ================================================================ -->
                            <!-- DEBUG: Show raw data if nothing else -->
                            <!-- ================================================================ -->
                            <?php if (!$has_med_details && empty($instructions) && empty($pharmacy_instructions)): ?>
                                <span class="med-details" style="color:#94A3B8;font-size:0.5rem;">
                                    <i class="fas fa-info-circle"></i> No additional instructions
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- TOTALS -->
            <!-- ================================================================ -->
            <div class="receipt-totals">
                <div class="receipt-total-row">
                    <span class="label">Subtotal</span>
                    <span class="value"><?= $currency ?> <?= number_format($bill['subtotal'] ?? $bill['total_amount'] ?? 0, 0) ?></span>
                </div>
                
                <?php $total_discount = (float)($bill['total_discount'] ?? 0); ?>
                <?php if ($total_discount > 0): ?>
                <div class="receipt-total-row">
                    <span class="label"><i class="fas fa-tag"></i> Discount</span>
                    <span class="value discount-value">-<?= $currency ?> <?= number_format($total_discount, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="receipt-total-row receipt-grand-total">
                    <span class="label">Total Amount</span>
                    <span class="value <?= $is_otc ? 'otc-total' : '' ?>">
                        <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                    </span>
                </div>
                
                <div class="receipt-total-row" style="border-top:1px dashed #E2E8F0;padding-top:4px;margin-top:2px;">
                    <span class="label" style="font-weight:600;">Amount Paid</span>
                    <span class="value paid-value" style="font-weight:700;"><?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span>
                </div>
                
                <?php if (($bill['balance'] ?? 0) > 0 && !$is_otc): ?>
                <div class="receipt-total-row">
                    <span class="label" style="font-weight:600;">Remaining Balance</span>
                    <span class="value balance-value" style="font-weight:700;"><?= $currency ?> <?= number_format($bill['balance'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- PAYMENT INFO -->
            <hr class="receipt-divider">
            <div class="receipt-row">
                <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                <span class="value">
                    <span class="payment-method-badge">
                        <i class="fas <?= ($bill['payment_method'] ?? 'cash') === 'cash' ? 'fa-money-bill-wave' : (($bill['payment_method'] ?? 'cash') === 'm-pesa' ? 'fa-mobile-alt' : 'fa-credit-card') ?>"></i>
                        <?= ucfirst(str_replace('_', ' ', $bill['payment_method'] ?? $payment['payment_method'] ?? 'Cash')) ?>
                    </span>
                </span>
            </div>
            <?php if ($payment && !empty($payment['reference_number'])): ?>
            <div class="receipt-row">
                <span class="label"><i class="fas fa-hashtag"></i> Reference #</span>
                <span class="value"><?= htmlspecialchars($payment['reference_number']) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="label"><i class="fas fa-user-check"></i> Received By</span>
                <span class="value"><?= htmlspecialchars($bill['cashier_name'] ?? $user_full_name) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label"><i class="fas fa-clock"></i> Received At</span>
                <span class="value"><?= isset($payment['received_at']) ? date('d/m/Y h:i A', strtotime($payment['received_at'])) : date('d/m/Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></span>
            </div>
            
            <!-- FOOTER -->
            <div class="receipt-footer">
                <div class="thank-you">
                    <i class="fas fa-heart"></i> 
                    <?= $is_otc ? 'Thank You for Your Purchase!' : 'Thank You for Choosing Us!' ?>
                </div>
                <div class="footer-brand <?= $is_otc ? 'otc-brand' : '' ?>"><?= htmlspecialchars($site_name) ?></div>
                <hr class="footer-divider">
                <div class="branch-info">
                    <?= htmlspecialchars($bill['branch_name'] ?? '') ?>
                    <?php if (!empty($bill['branch_location'])): ?>
                        <br><?= htmlspecialchars($bill['branch_location']) ?>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.5rem;color:#94A3B8;">
                    Tel: <?= htmlspecialchars($site_phone) ?> | Email: <?= htmlspecialchars($site_email) ?>
                </div>
                <div class="admin-contact-line" style="justify-content:center;">
                    <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
                </div>
                <hr class="footer-divider">
                <div style="font-size:0.5rem;color:#94A3B8;margin-top:4px;">
                    <?= date('d/m/Y h:i A') ?>
                </div>
                <div style="font-size:0.45rem;color:#CBD5E1;margin-top:2px;">
                    This is a computer generated receipt
                </div>
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

    console.log('%c🧾 Braick - Print Receipt (Beautiful Design)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Supports Regular Bills (with visit_id) and OTC Sales', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Beautiful design with gradient header', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Print button available', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Medications show dosage, route, frequency, instructions', 'font-size:13px; color:#D97706;');
    <?php if ($is_otc): ?>
        console.log('%c🛒 OTC Sale: <?= htmlspecialchars($bill['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
        console.log('%c👤 Customer: <?= htmlspecialchars($bill['patient_name'] ?? 'Walk-in') ?>', 'font-size:13px; color:#7C3AED;');
        console.log('%c💰 Total: <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
    <?php else: ?>
        console.log('%c📋 Regular Bill: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
        console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#0B5ED7;');
        console.log('%c💰 Total: <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
        console.log('%c💊 Medications: <?= count($medication_items) ?> items', 'font-size:13px; color:#D97706;');
        console.log('%c📄 Other Bills: <?= count($other_items) ?> items', 'font-size:13px; color:#0B5ED7;');
        console.log('%c📋 Each medication shows: Dosage, Route, Frequency, Duration, Instructions', 'font-size:13px; color:#34D399;');
    <?php endif; ?>
    console.log('%c📞 Admin: <?= htmlspecialchars($admin_phones_display) ?>', 'font-size:13px; color:#0B5ED7;');
    
    // Debug - show medication items data
    console.log('%c📋 Medication Items Data:', 'font-size:13px; color:#D97706;');
    <?php 
    if (!$is_otc && count($medication_items) > 0) {
        foreach ($medication_items as $idx => $item) {
            echo "console.log('  Item " . ($idx+1) . ": " . addslashes($item['item_name'] ?? 'N/A') . "');";
            echo "console.log('    Dosage: " . addslashes($item['dosage'] ?? 'empty') . "');";
            echo "console.log('    Frequency: " . addslashes($item['frequency'] ?? 'empty') . "');";
            echo "console.log('    Route: " . addslashes($item['route'] ?? 'empty') . "');";
            echo "console.log('    Duration: " . addslashes($item['duration'] ?? 'empty') . "');";
            echo "console.log('    Instructions: " . addslashes($item['instructions'] ?? 'empty') . "');";
            echo "console.log('    Pharmacy Instructions: " . addslashes($item['pharmacy_instructions'] ?? 'empty') . "');";
        }
    }
    ?>
</script>

</body>
</html>