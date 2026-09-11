<?php
// ================================================================
// FILE: frontend/pages/cashier/print_receipt.php
// CASHIER - PRINT RECEIPT - BEAUTIFUL DESIGN
// ✅ PREMIUM AMOUNT HIDDEN (only Paid Amount shown)
// ✅ BRANCH PHONE & EMAIL FROM branches TABLE
// ✅ SUPPORTS: Regular Bills AND OTC Sales
// ✅ Medications show dosage, route, frequency, instructions
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

// ================================================================
// ✅ BRANCH INFO (PHONE, EMAIL, LOCATION, NAME)
// ================================================================
$branch_name = $user_branch_name;
$branch_phone = '+255 700 000 001';
$branch_email = 'info@braick.com';
$branch_location = 'Tanzania';

try {
    $stmt = $db->prepare("
        SELECT name, location, phone, email, logo 
        FROM branches 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($branch_data) {
        $branch_name = $branch_data['name'] ?? $user_branch_name;
        $branch_phone = $branch_data['phone'] ?? '+255 700 000 001';
        $branch_email = $branch_data['email'] ?? 'info@braick.com';
        $branch_location = $branch_data['location'] ?? 'Tanzania';
    }
} catch (Exception $e) {
    error_log("Branch fetch error: " . $e->getMessage());
}

// ================================================================
// ✅ ADMIN CONTACTS (FROM users TABLE)
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
$admin_phones_display = !empty($admin_phones) 
    ? implode(' | ', array_filter($admin_phones)) 
    : $branch_phone;

try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $site_name = $settings['site_name'] ?? 'Braick Dispensary';
    $currency = $settings['currency'] ?? 'TSh';
    
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
                
                // Use branch data if available
                $branch_name = $otc_sale['branch_name'] ?? $branch_name;
                $branch_location = $otc_sale['branch_location'] ?? $branch_location;
                $branch_phone = $otc_sale['branch_phone'] ?? $branch_phone;
                $branch_email = $otc_sale['branch_email'] ?? $branch_email;
                
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
                    'branch_name' => $branch_name,
                    'branch_location' => $branch_location,
                    'branch_phone' => $branch_phone,
                    'branch_email' => $branch_email,
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
    // CASE 2: REGULAR BILL
    // ================================================================
    if (!$is_otc && $bill_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                b.*,
                b.discount_amount as pharmacy_discount,
                b.cashier_discount,
                b.total_discount,
                b.premium_amount,
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
            $stmt = $db->prepare("
                SELECT bi.*
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
            
            $visit_id = $bill['visit_id'] ?? 0;
            
            $prescription_items_map = [];
            $prescription_numbers = [];
            
            if ($visit_id > 0) {
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
                
                foreach ($prescription_data as $rx) {
                    if (!empty($rx['medication_name'])) {
                        $key = trim($rx['medication_name']);
                        if (!isset($prescription_items_map[$key]) || 
                            (empty($prescription_items_map[$key]['instructions']) && !empty($rx['instructions']))) {
                            $prescription_items_map[$key] = $rx;
                        }
                        if (!empty($rx['prescription_number']) && !in_array($rx['prescription_number'], $prescription_numbers)) {
                            $prescription_numbers[] = $rx['prescription_number'];
                        }
                    }
                }
            }
            
            $all_items = [];
            foreach ($bill_items_data as $item) {
                $item_name = trim($item['item_name'] ?? '');
                $clean_name = preg_replace('/\s*\(Batch:.*\)/', '', $item_name);
                $clean_name = trim($clean_name);
                
                $rx_details = null;
                if (!empty($clean_name) && isset($prescription_items_map[$clean_name])) {
                    $rx_details = $prescription_items_map[$clean_name];
                } elseif (!empty($item_name) && isset($prescription_items_map[$item_name])) {
                    $rx_details = $prescription_items_map[$item_name];
                } else {
                    foreach ($prescription_items_map as $rx_name => $rx_data) {
                        if (stripos($item_name, $rx_name) !== false || stripos($rx_name, $item_name) !== false) {
                            $rx_details = $rx_data;
                            break;
                        }
                    }
                }
                
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
            
            $medication_items = [];
            $other_items = [];
            foreach ($all_items as $item) {
                if ($item['item_type'] === 'medication') {
                    $medication_items[] = $item;
                } else {
                    $other_items[] = $item;
                }
            }
            
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
            
            // Use branch data
            $branch_name = $bill['branch_name'] ?? $branch_name;
            $branch_location = $bill['branch_location'] ?? $branch_location;
            $branch_phone = $bill['branch_phone'] ?? $branch_phone;
            $branch_email = $bill['branch_email'] ?? $branch_email;
            
            $bill['is_otc'] = false;
            $bill['reference_id'] = $bill_id;
            $bill['branch_name'] = $branch_name;
            $bill['branch_location'] = $branch_location;
            $bill['branch_phone'] = $branch_phone;
            $bill['branch_email'] = $branch_email;
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

$show_receipt = !$has_error && $bill;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_otc ? 'OTC Receipt' : 'Receipt' ?> - <?= htmlspecialchars($branch_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0f2f5 0%, #e8ecf1 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .receipt-wrapper {
            max-width: 480px;
            margin: 0 auto;
        }
        
        .page-header {
            max-width: 480px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .page-header .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .page-header .btn-back {
            background: white;
            color: #475569;
            border: 2px solid #E2E8F0;
        }
        
        .page-header .btn-back:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(11,94,215,0.15);
        }
        
        .page-header .btn-print {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }
        
        .page-header .btn-print:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(11,94,215,0.35);
        }
        
        .page-header .btn-print.otc-btn {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
        }
        
        .page-header .btn-print.otc-btn:hover {
            box-shadow: 0 8px 25px rgba(124,58,237,0.35);
        }
        
        .receipt {
            background: #FFFFFF;
            padding: 0;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 4px 20px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
            font-family: 'Courier Prime', 'Courier New', monospace;
        }
        
        .receipt::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #0B5ED7, #059669, #7C3AED, #0B5ED7);
            background-size: 300% 100%;
            animation: gradientShift 4s ease infinite;
        }
        
        .receipt.otc-receipt::before {
            background: linear-gradient(90deg, #7C3AED, #8B5CF6, #A78BFA, #7C3AED);
            background-size: 300% 100%;
            animation: gradientShift 4s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .receipt-inner {
            padding: 28px 30px 24px 30px;
        }
        
        .receipt-header {
            text-align: center;
            padding-bottom: 18px;
            border-bottom: 2px dashed #E2E8F0;
            margin-bottom: 16px;
            position: relative;
        }
        
        .receipt-header.otc-header {
            border-bottom-color: #C4B5FD;
        }
        
        .receipt-logo {
            display: block;
            margin: 0 auto 8px auto;
            max-width: 110px;
            max-height: 65px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.08));
        }
        
        .receipt-logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: 1.5px;
            margin-bottom: 2px;
            font-family: 'Inter', sans-serif;
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
            font-size: 0.95rem;
            font-weight: 700;
            color: #1E293B;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 2px;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-title.otc-title {
            color: #7C3AED;
        }
        
        .receipt-subtitle {
            font-size: 0.65rem;
            color: #64748B;
            margin-top: 3px;
            line-height: 1.5;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-subtitle i {
            color: #0B5ED7;
            font-size: 0.55rem;
        }
        
        .branch-info {
            text-align: center;
            font-size: 0.6rem;
            color: #64748B;
            margin: 6px 0 0 0;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .branch-info i {
            color: #0B5ED7;
            margin-right: 2px;
        }
        
        .admin-contact-line {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.55rem;
            color: #94A3B8;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #E2E8F0;
            font-family: 'Inter', sans-serif;
        }
        
        .admin-contact-line span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .admin-contact-line i {
            color: #059669;
            font-size: 0.5rem;
        }
        
        .receipt-body {
            font-size: 0.75rem;
            color: #1E293B;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-row .label {
            color: #64748B;
            font-weight: 500;
        }
        
        .receipt-row .value {
            font-weight: 600;
            color: #0F172A;
            text-align: right;
            max-width: 60%;
        }
        
        .receipt-row .value.bold { font-weight: 700; }
        .receipt-row .value.otc-value { color: #7C3AED; }
        
        .receipt-divider {
            border: none;
            border-top: 1px dashed #CBD5E1;
            margin: 8px 0;
        }
        
        .section-header {
            font-weight: 700;
            font-size: 0.75rem;
            padding: 5px 0;
            margin: 10px 0 6px 0;
            border-bottom: 2px solid;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Inter', sans-serif;
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
            padding: 2px 0;
        }
        
        .receipt-item {
            padding: 7px 0;
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
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-item .item-price {
            font-weight: 600;
            white-space: nowrap;
            margin-left: 10px;
            font-size: 0.75rem;
            font-family: 'Courier Prime', monospace;
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
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-item .med-details {
            display: block;
            font-size: 0.55rem;
            color: #64748B;
            margin-top: 3px;
            padding-left: 2px;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-item .med-details .med-tag {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 6px;
            margin-right: 4px;
            margin-bottom: 2px;
            font-size: 0.5rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-item .med-details .med-tag.dosage-tag { background: #DBEAFE; color: #0B5ED7; }
        .receipt-item .med-details .med-tag.route-tag { background: #D1FAE5; color: #059669; }
        .receipt-item .med-details .med-tag.freq-tag { background: #FEF3C7; color: #D97706; }
        .receipt-item .med-details .med-tag.duration-tag { background: #EDE9FE; color: #7C3AED; }
        
        .receipt-item .med-instruction-box {
            display: block;
            font-size: 0.6rem;
            color: #64748B;
            font-style: italic;
            padding: 5px 12px;
            border-left: 3px solid #D97706;
            margin-top: 5px;
            background: #FFFBEB;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-item .med-instruction-box i { color: #D97706; margin-right: 5px; }
        
        .receipt-item .med-instruction-box.otc-instruction {
            border-left-color: #7C3AED;
            background: #EDE9FE;
        }
        
        .receipt-item .med-instruction-box.otc-instruction i { color: #7C3AED; }
        
        .receipt-item .pharmacy-instruction {
            display: block;
            font-size: 0.55rem;
            color: #0B5ED7;
            padding: 4px 12px;
            border-left: 3px solid #0B5ED7;
            margin-top: 4px;
            background: #E8F0FE;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-item .pharmacy-instruction i { color: #0B5ED7; margin-right: 5px; }
        
        .receipt-totals {
            margin: 10px 0 4px 0;
            padding-top: 10px;
            border-top: 2px dashed #E2E8F0;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.75rem;
        }
        
        .receipt-total-row .label { color: #64748B; font-weight: 500; }
        .receipt-total-row .value { font-weight: 600; font-family: 'Courier Prime', monospace; }
        
        .receipt-grand-total {
            border-top: 2px solid #1E293B;
            padding-top: 8px;
            margin-top: 6px;
            font-size: 0.95rem;
            font-weight: 700;
        }
        
        .receipt-grand-total .value { color: #0B5ED7; font-size: 1.05rem; }
        .receipt-grand-total .value.otc-total { color: #7C3AED; }
        .receipt-grand-total .value.paid-total { color: #059669; }
        .discount-value { color: #DC2626; }
        
        .category-totals {
            display: flex;
            gap: 10px;
            margin: 10px 0;
            padding: 2px 0;
            font-family: 'Inter', sans-serif;
        }
        
        .category-totals .cat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 14px;
            border-radius: 12px;
            flex: 1;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .category-totals .cat-item.other {
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            color: #0B5ED7;
        }
        
        .category-totals .cat-item.medication {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            color: #D97706;
        }
        
        .category-totals .cat-item.otc-cat {
            background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
            color: #7C3AED;
        }
        
        .category-totals .cat-item .cat-label {
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .category-totals .cat-item .cat-label i { margin-right: 3px; }
        
        .category-totals .cat-item .cat-value {
            font-weight: 800;
            font-size: 0.85rem;
            margin-top: 3px;
            font-family: 'Courier Prime', monospace;
        }
        
        .payment-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-family: 'Inter', sans-serif;
        }
        
        .payment-status.paid { background: #D1FAE5; color: #059669; }
        .payment-status.pending { background: #FEF3C7; color: #D97706; }
        .payment-status.partial { background: #DBEAFE; color: #0B5ED7; }
        .payment-status.cancelled { background: #FEE2E2; color: #DC2626; }
        .payment-status.otc-paid { background: #EDE9FE; color: #7C3AED; }
        
        .payment-method-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 600;
            background: #F1F5F9;
            color: #475569;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-footer {
            text-align: center;
            font-size: 0.6rem;
            color: #94A3B8;
            padding-top: 14px;
            border-top: 2px dashed #E2E8F0;
            margin-top: 14px;
            font-family: 'Inter', sans-serif;
        }
        
        .receipt-footer .footer-brand {
            color: #0B5ED7;
            font-weight: 800;
            font-size: 0.75rem;
        }
        
        .receipt-footer .footer-brand.otc-brand { color: #7C3AED; }
        
        .receipt-footer .footer-divider {
            margin: 6px 0;
            border: none;
            border-top: 1px dashed #E2E8F0;
        }
        
        .receipt-footer .thank-you {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1E293B;
            margin: 6px 0;
        }
        
        .receipt-footer .thank-you i {
            color: #DC2626;
            opacity: 0.6;
            margin-right: 4px;
        }
        
        .receipt-footer .footer-note {
            font-size: 0.5rem;
            color: #94A3B8;
            margin-top: 4px;
        }
        
        .error-box {
            max-width: 480px;
            margin: 0 auto;
            background: linear-gradient(135deg, #FEF2F2, #FEE2E2);
            border: 2px solid #FCA5A5;
            border-radius: 20px;
            padding: 40px 32px;
            text-align: center;
            color: #991B1B;
            box-shadow: 0 20px 60px rgba(220,38,38,0.1);
        }
        
        .error-box i { font-size: 3rem; display: block; margin-bottom: 16px; color: #DC2626; }
        .error-box h3 { font-size: 1.3rem; margin-bottom: 8px; color: #991B1B; font-family: 'Inter', sans-serif; }
        .error-box p { font-size: 0.85rem; color: #7F1D1D; font-family: 'Inter', sans-serif; }
        
        .error-box .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 32px;
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(220,38,38,0.3);
        }
        
        .error-box .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220,38,38,0.4);
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
            .receipt { border-radius: 0; box-shadow: none; padding: 0; max-width: 100%; }
            .receipt::before { display: none; }
            .receipt-inner { padding: 16px 20px 14px 20px; }
            .page-header { display: none !important; }
            .no-print { display: none !important; }
            .error-box { display: none !important; }
            
            .category-totals .cat-item.other,
            .category-totals .cat-item.medication,
            .category-totals .cat-item.otc-cat {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-shadow: none !important;
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
            
            .receipt-logo { max-width: 85px; max-height: 50px; }
            .receipt-logo-text { font-size: 1.2rem; }
        }
        
        @media (max-width: 480px) {
            .receipt-inner { padding: 18px 16px 16px 16px; }
            .receipt { border-radius: 14px; }
            .receipt-logo-text { font-size: 1.2rem; }
            .page-header { flex-direction: column; align-items: stretch; }
            .page-header .btn-group { justify-content: center; }
            .page-header .btn { flex: 1; justify-content: center; }
            .category-totals { flex-direction: column; }
            .category-totals .cat-item { flex: 1; }
            .receipt-title { font-size: 0.8rem; }
            .receipt-item .item-name { font-size: 0.68rem; }
            .receipt-item .item-price { font-size: 0.68rem; }
            .receipt-grand-total { font-size: 0.85rem; }
            .receipt-grand-total .value { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">

    <!-- PAGE HEADER WITH PRINT BUTTONS -->
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

    <!-- ERROR -->
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

    <!-- RECEIPT -->
    <div class="receipt <?= $is_otc ? 'otc-receipt' : '' ?>" id="receipt">
        <div class="receipt-inner">
        
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
                <strong><?= htmlspecialchars($branch_name) ?></strong>
                <?php if (!empty($branch_location)): ?>
                    <br><i class="fas fa-map-marker-alt"></i> 
                    <?= htmlspecialchars($branch_location) ?>
                <?php endif; ?>
            </div>
            <div class="branch-info">
                <?php if (!empty($branch_phone)): ?>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($branch_phone) ?></span>
                <?php endif; ?>
                <?php if (!empty($branch_email)): ?>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($branch_email) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($admin_phones_display) && $admin_phones_display !== $branch_phone): ?>
            <div class="admin-contact-line">
                <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
            </div>
            <?php endif; ?>
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
                        <i class="fas fa-circle" style="font-size:0.35rem;"></i>
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
            
            <!-- CATEGORY TOTALS -->
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
            
            <!-- OTC ITEMS SECTION -->
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
            
            <!-- OTHER BILLS SECTION -->
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
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- MEDICATIONS SECTION -->
            <?php if (!$is_otc && count($medication_items) > 0): ?>
                <div class="section-header medication">
                    <span><i class="fas fa-prescription"></i> Prescriptions (Medications)</span>
                    <span class="section-total"><?= $currency ?> <?= number_format($medication_total, 0) ?></span>
                </div>
                <div class="receipt-items">
                    <?php foreach ($medication_items as $item): 
                        $dosage = $item['dosage'] ?? '';
                        $frequency = $item['frequency'] ?? '';
                        $route = $item['route'] ?? '';
                        $duration = $item['duration'] ?? '';
                        $rx_quantity = $item['rx_quantity'] ?? $item['quantity'] ?? 1;
                        $instructions = $item['instructions'] ?? '';
                        $pharmacy_instructions = $item['pharmacy_instructions'] ?? '';
                        $prescription_number = $item['prescription_number'] ?? '';
                        
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
                            
                            <?php if (!empty($instructions)): ?>
                                <span class="med-instruction-box">
                                    <i class="fas fa-prescription"></i>
                                    <strong>Instructions:</strong> <?= htmlspecialchars($instructions) ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($pharmacy_instructions)): ?>
                                <span class="pharmacy-instruction">
                                    <i class="fas fa-pharmacy"></i>
                                    <strong>Pharmacy:</strong> <?= htmlspecialchars($pharmacy_instructions) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- TOTALS - PREMIUM HIDDEN, ONLY PAID SHOWN -->
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
                
                <!-- ✅ PAID AMOUNT ONLY - NO PREMIUM SHOWN -->
                <div class="receipt-total-row" style="border-top:1px dashed #E2E8F0;padding-top:6px;margin-top:4px;">
                    <span class="label" style="font-weight:700;font-size:0.85rem;">Amount Paid</span>
                    <span class="value paid-value" style="font-weight:700;font-size:0.9rem;color:#059669;">
                        <?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?>
                    </span>
                </div>
                
                <?php if (($bill['balance'] ?? 0) > 0 && !$is_otc): ?>
                <div class="receipt-total-row">
                    <span class="label" style="font-weight:600;">Remaining Balance</span>
                    <span class="value balance-value" style="font-weight:700;color:#DC2626;"><?= $currency ?> <?= number_format($bill['balance'] ?? 0, 0) ?></span>
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
                <div class="footer-brand <?= $is_otc ? 'otc-brand' : '' ?>"><?= htmlspecialchars($branch_name) ?></div>
                <hr class="footer-divider">
                <div class="branch-info">
                    <?php if (!empty($branch_location)): ?>
                        <?= htmlspecialchars($branch_location) ?>
                    <?php endif; ?>
                </div>
                <div class="footer-note">
                    <?php if (!empty($branch_phone)): ?>
                        Tel: <?= htmlspecialchars($branch_phone) ?>
                    <?php endif; ?>
                    <?php if (!empty($branch_email)): ?>
                        | Email: <?= htmlspecialchars($branch_email) ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($admin_phones_display) && $admin_phones_display !== $branch_phone): ?>
                <div class="admin-contact-line" style="justify-content:center;">
                    <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
                </div>
                <?php endif; ?>
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

    console.log('%c🧾 Braick - Print Receipt', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Phone & Email from branches table', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Premium amount HIDDEN - only Paid Amount shown', 'font-size:13px; color:#DC2626;');
    console.log('%c📍 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📞 Phone: <?= htmlspecialchars($branch_phone) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📧 Email: <?= htmlspecialchars($branch_email) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👑 Admin: <?= htmlspecialchars($admin_phones_display) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>