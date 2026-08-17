<?php
// ================================================================
// FILE: frontend/pages/admin/add_payment.php
// ADMIN - ADD PAYMENT TO BILL (GREEN THEME - SAME AS CASHIER)
// BRAICK DISPENSARY - GREEN THEME
// WITH LOGIN PROTECTION
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$bill_item_id = isset($_GET['bill_item_id']) ? (int)$_GET['bill_item_id'] : 0;
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$selected_branch_id = $_GET['branch'] ?? $_GET['branch_id'] ?? 'all';

if ($bill_id <= 0 || $bill_item_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_params');
    exit;
}

// ================================================================
// FETCH BILL DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            pb.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender,
            p.date_of_birth,
            u.full_name as created_by_name,
            b.name as branch_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND payment_status = 'pending') as pending_items_count
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN users u ON pb.created_by = u.id
        LEFT JOIN branches b ON pb.branch_id = b.id
        WHERE pb.id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=bill_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill: " . $e->getMessage());
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// FETCH BILL ITEM DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*
        FROM bill_items bi
        WHERE bi.id = ? AND bi.bill_id = ?
    ");
    $stmt->execute([$bill_item_id, $bill_id]);
    $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill_item) {
        header('Location: view_bill.php?id=' . $bill_id . '&branch=' . urlencode($selected_branch_id) . '&error=item_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill item: " . $e->getMessage());
    header('Location: view_bill.php?id=' . $bill_id . '&branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// FETCH ALL BILL ITEMS FOR THIS BILL
// ================================================================
$all_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*
        FROM bill_items bi
        WHERE bi.bill_id = ?
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_items = [];
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$subtotal = 0;
$total_paid = 0;
$pending_total = 0;
$pending_items_count = 0;

foreach ($all_items as $item) {
    $subtotal += ($item['total_price'] ?? 0);
    if ($item['payment_status'] === 'paid') {
        $total_paid += ($item['total_price'] ?? 0);
    } else {
        $pending_total += ($item['total_price'] ?? 0);
        $pending_items_count++;
    }
}

$discount_amount = $bill['discount_amount'] ?? 0;
$grand_total = $subtotal - $discount_amount;
$remaining_balance = $bill['balance'] ?? $grand_total;

// ================================================================
// GET PAYMENT METHODS
// ================================================================
$payment_methods = [
    'cash' => '💰 Cash',
    'm-pesa' => '📱 M-Pesa',
    'airtel_money' => '📱 Airtel Money',
    'tigo_pesa' => '📱 Tigo Pesa',
    'halopesa' => '📱 Halo Pesa',
    'bank' => '🏦 Bank Transfer',
    'card' => '💳 Card Payment',
    'insurance' => '🏥 Insurance',
    'other' => '📦 Other'
];

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger',
        'completed' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getItemTypeLabel($type) {
    $labels = [
        'registration' => 'Registration',
        'consultation' => 'Consultation',
        'lab_test' => 'Lab Test',
        'medication' => 'Medication',
        'procedure' => 'Procedure',
        'tool' => 'Tool/Supply',
        'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst($type);
}

function getItemTypeColor($type) {
    $colors = [
        'registration' => 'blue',
        'consultation' => 'purple',
        'lab_test' => 'orange',
        'medication' => 'green',
        'procedure' => 'red',
        'tool' => 'teal',
        'other' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$currency = 'TSh';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';

// ================================================================
// PROCESS PAYMENT
// ================================================================
$message = '';
$message_type = '';
$payment_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_type = $_POST['payment_type'] ?? 'full';
    $discount_amount = isset($_POST['discount_amount']) ? floatval(str_replace(',', '', $_POST['discount_amount'])) : 0;
    $partial_amount = isset($_POST['partial_amount']) ? floatval(str_replace(',', '', $_POST['partial_amount'])) : 0;
    
    try {
        $db->beginTransaction();
        
        // Get current bill balance
        $stmt = $db->prepare("SELECT balance, patient_id, total_amount, paid_amount FROM patient_bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $current_bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_bill) {
            throw new Exception("Bill not found");
        }
        
        $current_balance = (float)$current_bill['balance'];
        $patient_id = $current_bill['patient_id'];
        
        if ($payment_type === 'full') {
            // Full payment
            $amount_to_pay = $current_balance - $discount_amount;
            if ($amount_to_pay < 0) $amount_to_pay = 0;
            $new_balance = 0;
            $status = 'paid';
            $bill_discount = $discount_amount;
            
        } else {
            // Partial payment
            if ($partial_amount <= 0) {
                throw new Exception("Please enter a valid partial amount");
            }
            if ($partial_amount > $current_balance) {
                throw new Exception("Partial amount cannot exceed balance");
            }
            
            $amount_to_pay = $partial_amount;
            $new_balance = $current_balance - $partial_amount - $discount_amount;
            if ($new_balance < 0) $new_balance = 0;
            $status = $new_balance > 0 ? 'partial' : 'paid';
            $bill_discount = $discount_amount;
            
            // Validate discount doesn't exceed remaining balance
            if ($discount_amount > $current_balance - $partial_amount) {
                throw new Exception("Discount cannot exceed remaining balance after partial payment");
            }
        }
        
        // Generate receipt number
        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        if ($payment_type === 'partial') {
            $receipt_number = 'RCP-PARTIAL-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        // Update bill
        $stmt = $db->prepare("
            UPDATE patient_bills 
            SET paid_amount = paid_amount + ?,
                balance = ?,
                discount_amount = discount_amount + ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$amount_to_pay, $new_balance, $bill_discount, $status, $bill_id]);
        
        // Update bill items
        if ($status === 'paid') {
            $stmt = $db->prepare("
                UPDATE bill_items 
                SET is_paid = 1, 
                    payment_status = 'paid', 
                    paid_at = NOW()
                WHERE bill_id = ? AND (is_paid = 0 OR is_paid IS NULL)
            ");
            $stmt->execute([$bill_id]);
        }
        
        // Record payment
        $stmt = $db->prepare("
            INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $notes = $bill_discount > 0 ? 'Discount: ' . $currency . ' ' . number_format($bill_discount, 2) : '';
        $notes .= $payment_type === 'partial' ? ' Partial payment' : ' Full payment';
        $stmt->execute([
            $receipt_number,
            $bill_id,
            $patient_id,
            $amount_to_pay,
            $payment_method,
            $_SESSION['user_id'],
            $selected_branch_id,
            trim($notes)
        ]);
        
        // Update OTC sales if any
        $stmt = $db->prepare("
            SELECT id FROM otc_sales 
            WHERE bill_id = ? AND payment_status IN ('pending', 'partial')
        ");
        $stmt->execute([$bill_id]);
        $otc_sale = $stmt->fetch();
        
        if ($otc_sale) {
            $stmt = $db->prepare("
                UPDATE otc_sales 
                SET payment_status = 'paid',
                    updated_at = NOW()
                WHERE bill_id = ?
            ");
            $stmt->execute([$bill_id]);
        }
        
        $db->commit();
        
        $payment_success = true;
        $message = "✅ Payment processed successfully!";
        $message .= "<br>📋 Receipt #: <strong>$receipt_number</strong>";
        $message .= "<br>💰 Amount: <strong>" . $currency . " " . number_format($amount_to_pay, 0) . "</strong>";
        if ($bill_discount > 0) {
            $message .= "<br>🎯 Discount: <strong>" . $currency . " " . number_format($bill_discount, 0) . "</strong>";
        }
        if ($status === 'paid') {
            $message .= "<br>✅ Bill fully paid!";
        } else {
            $message .= "<br>🔄 Remaining balance: <strong>" . $currency . " " . number_format($new_balance, 0) . "</strong>";
        }
        $message_type = 'success';
        
        // Refresh bill data
        $stmt = $db->prepare("
            SELECT 
                pb.*,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone as patient_phone,
                p.gender,
                p.date_of_birth,
                u.full_name as created_by_name,
                b.name as branch_name
            FROM patient_bills pb
            LEFT JOIN patients p ON pb.patient_id = p.id
            LEFT JOIN users u ON pb.created_by = u.id
            LEFT JOIN branches b ON pb.branch_id = b.id
            WHERE pb.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Refresh all items
        $stmt = $db->prepare("
            SELECT bi.*
            FROM bill_items bi
            WHERE bi.bill_id = ?
            ORDER BY bi.created_at DESC
        ");
        $stmt->execute([$bill_id]);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recalculate totals
        $subtotal = 0;
        $total_paid = 0;
        $pending_total = 0;
        $pending_items_count = 0;
        foreach ($all_items as $item) {
            $subtotal += ($item['total_price'] ?? 0);
            if ($item['payment_status'] === 'paid') {
                $total_paid += ($item['total_price'] ?? 0);
            } else {
                $pending_total += ($item['total_price'] ?? 0);
                $pending_items_count++;
            }
        }
        $discount_amount = $bill['discount_amount'] ?? 0;
        $grand_total = $subtotal - $discount_amount;
        $remaining_balance = $bill['balance'] ?? $grand_total;
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Payment error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME (Same as Cashier)
           ================================================================ */
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #D1FAE5;
            --table-hover: #A7F3D0;
            --toast-bg: #FFFFFF;
            --toast-text: #1E293B;
            --input-bg: #FFFFFF;
            --input-border: #E2E8F0;
            --input-text: #1E293B;
            --empty-state-color: #64748B;
            --footer-border: #E2E8F0;
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #D97706;
            --badge-partial-bg: #DBEAFE;
            --badge-partial-text: #2563EB;
            --badge-paid-bg: #D1FAE5;
            --badge-paid-text: #059669;
            --badge-cancelled-bg: #FEE2E2;
            --badge-cancelled-text: #DC2626;
            --bill-header-bg: #F8FAFC;
            --bill-footer-bg: #F8FAFC;
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
            --patient-card-header-bg: #059669;
            --patient-card-header-text: #FFFFFF;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1A3A2A;
            --table-hover: #1A4A3A;
            --toast-bg: #1E293B;
            --toast-text: #F1F5F9;
            --input-bg: #1E293B;
            --input-border: #334155;
            --input-text: #F1F5F9;
            --empty-state-color: #94A3B8;
            --footer-border: #334155;
            --badge-pending-bg: #3D2E0A;
            --badge-pending-text: #FBBF24;
            --badge-partial-bg: #1E3A5F;
            --badge-partial-text: #60A5FA;
            --badge-paid-bg: #1A3A2A;
            --badge-paid-text: #34D399;
            --badge-cancelled-bg: #3A1A1A;
            --badge-cancelled-text: #F87171;
            --bill-header-bg: #1E293B;
            --bill-footer-bg: #1E293B;
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
            --patient-card-header-bg: #047857;
            --patient-card-header-text: #FFFFFF;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--page-header-bg-from), var(--page-header-bg-to));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px var(--page-header-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1100px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        .form-label .label-badge {
            font-weight: 400;
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: var(--gray-100);
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row { margin-bottom: 20px; }
        .form-row:last-child { margin-bottom: 0; }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        }
        
        .btn-warning:hover {
            box-shadow: 0 6px 24px rgba(217, 119, 6, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 5px 14px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .amount-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .amount-display.blue { color: var(--primary); }
        .amount-display.green { color: var(--success); }
        .amount-display.red { color: var(--danger); }
        .amount-display.purple { color: var(--purple); }
        
        .summary-box {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border-left: 4px solid var(--primary);
            margin-bottom: 20px;
        }
        
        .summary-box .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.85rem;
        }
        
        .summary-box .summary-item .label {
            color: var(--text-secondary);
        }
        
        .summary-box .summary-item .value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .summary-box .summary-item.total {
            border-top: 2px solid var(--border-color);
            padding-top: 8px;
            margin-top: 4px;
            font-size: 1rem;
        }
        
        .summary-box .summary-item.total .value {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .summary-box .summary-item.balance .value {
            color: var(--danger);
        }
        
        .amount-input-wrap {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .amount-input-wrap .currency-prefix {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            z-index: 1;
        }
        .amount-input-wrap input {
            padding-left: 36px !important;
            text-align: right;
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 600;
            width: 100%;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .message-box {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message-box.success { background: var(--success-bg); border: 1px solid var(--success); color: var(--success); }
        .message-box.error { background: var(--danger-bg); border: 1px solid var(--danger); color: var(--danger); }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .grid-2 { grid-template-columns: 1fr; gap: 14px; }
            .form-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .page-header .btn-outline-light { padding: 4px 10px; font-size: 0.7rem; }
        }
        
        /* Payment type toggle */
        .payment-type-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .payment-type-toggle .toggle-btn {
            padding: 6px 16px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .payment-type-toggle .toggle-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .payment-type-toggle .toggle-btn.active {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }
        
        .payment-type-toggle .toggle-btn.active:hover {
            background: var(--success-dark);
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-hand-holding-usd"></i>
                Add Payment
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-money-bill-wave"></i>
                <strong>Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill"></i>
                    Balance: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="view_bill_item.php?id=<?= $bill_item_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-invoice"></i> View Bill
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?> animate-fade-in-up">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="summary-box animate-fade-in-up">
        <div class="summary-item">
            <span class="label">Bill Number</span>
            <span class="value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Patient</span>
            <span class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?> (<?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?>)</span>
        </div>
        <div class="summary-item">
            <span class="label">Phone</span>
            <span class="value"><?= htmlspecialchars($bill['patient_phone'] ?? 'N/A') ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Branch</span>
            <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Created By</span>
            <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Total Amount</span>
            <span class="value"><?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Paid Amount</span>
            <span class="value" style="color:var(--success);"><?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Discount</span>
            <span class="value" style="color:var(--warning);">- <?= $currency ?> <?= number_format($bill['discount_amount'] ?? 0, 0) ?></span>
        </div>
        <div class="summary-item total">
            <span class="label">Grand Total</span>
            <span class="value" style="color:var(--primary);font-size:1.2rem;"><?= $currency ?> <?= number_format(($bill['total_amount'] ?? 0) - ($bill['discount_amount'] ?? 0), 0) ?></span>
        </div>
        <div class="summary-item balance">
            <span class="label"><i class="fas fa-exclamation-triangle"></i> Remaining Balance</span>
            <span class="value" style="font-size:1.2rem;"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div>
                <h3 class="form-title">Process Payment</h3>
                <p class="form-subtitle">Enter payment details for <?= htmlspecialchars($bill_item['item_name'] ?? 'this item') ?></p>
            </div>
        </div>
        
        <form method="POST" action="" id="paymentForm">
            <input type="hidden" name="action" value="process_payment">
            
            <!-- Payment Type Toggle -->
            <div class="payment-type-toggle">
                <button type="button" class="toggle-btn active" data-type="full" onclick="setPaymentType('full')">
                    <i class="fas fa-check-circle"></i> Full Payment
                </button>
                <button type="button" class="toggle-btn" data-type="partial" onclick="setPaymentType('partial')">
                    <i class="fas fa-hand-holding-heart"></i> Partial Payment
                </button>
            </div>
            <input type="hidden" name="payment_type" id="paymentType" value="full">
            
            <div class="grid-2">
                <!-- Payment Method -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-credit-card label-icon"></i> Payment Method <span class="required">*</span>
                    </label>
                    <select name="payment_method" class="form-control" required id="paymentMethod">
                        <?php foreach ($payment_methods as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $key === 'cash' ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Payment Date -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt label-icon"></i> Payment Date
                    </label>
                    <input type="text" class="form-control" value="<?= date('M d, Y h:i A') ?>" disabled>
                </div>
            </div>
            
            <div class="grid-2">
                <!-- Discount -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-percent label-icon"></i> Discount Amount
                        <span class="label-badge">Optional</span>
                    </label>
                    <div class="amount-input-wrap">
                        <span class="currency-prefix"><?= $currency ?></span>
                        <input type="text" name="discount_amount" id="discountAmount" class="form-control" 
                               placeholder="0" value="0" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                    <small class="text-gray-400 text-xs">Maximum discount: <?= $currency ?> <?= number_format($remaining_balance, 0) ?></small>
                </div>
                
                <!-- Partial Amount (shown only for partial payment) -->
                <div class="form-row" id="partialAmountRow">
                    <label class="form-label">
                        <i class="fas fa-hand-holding-heart label-icon"></i> Partial Amount <span class="required">*</span>
                        <span class="label-badge">For partial payment</span>
                    </label>
                    <div class="amount-input-wrap">
                        <span class="currency-prefix"><?= $currency ?></span>
                        <input type="text" name="partial_amount" id="partialAmount" class="form-control" 
                               placeholder="0" value="0" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                    <small class="text-gray-400 text-xs">Amount to pay now</small>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="summary-box" style="margin-top:16px;border-left-color:var(--success);">
                <div class="summary-item">
                    <span class="label">Remaining Balance</span>
                    <span class="value" id="displayBalance"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Discount</span>
                    <span class="value" id="displayDiscount" style="color:var(--warning);">- <?= $currency ?> 0</span>
                </div>
                <div class="summary-item total" style="border-top-color:var(--success);">
                    <span class="label" style="font-weight:700;">Amount to Pay</span>
                    <span class="value" id="displayAmountToPay" style="color:var(--success);font-size:1.2rem;"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                </div>
                <div class="summary-item" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
                    <span class="label">New Balance</span>
                    <span class="value" id="displayNewBalance" style="color:var(--danger);"><?= $currency ?> 0</span>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-success" id="submitBtn">
                    <i class="fas fa-check-circle"></i> Process Payment
                </button>
                <a href="view_bill_item.php?id=<?= $bill_item_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add Payment
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // FORMAT AMOUNT WITH COMMAS
    // ================================================================
    function formatAmount(input) {
        var val = input.value.replace(/[^0-9.]/g, '');
        var parts = val.split('.');
        var whole = parts[0];
        var decimal = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
        
        if (whole.length > 0) {
            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        input.value = whole + decimal;
        var rawValue = parseFloat(val) || 0;
        input.dataset.rawValue = rawValue;
    }
    
    function getRawValue(input) {
        var raw = input.dataset.rawValue;
        if (raw !== undefined && raw !== '') {
            return parseFloat(raw) || 0;
        }
        var val = input.value.replace(/,/g, '');
        return parseFloat(val) || 0;
    }

    // ================================================================
    // SET PAYMENT TYPE
    // ================================================================
    function setPaymentType(type) {
        document.getElementById('paymentType').value = type;
        
        var buttons = document.querySelectorAll('.payment-type-toggle .toggle-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.dataset.type === type) {
                btn.classList.add('active');
            }
        });
        
        var partialRow = document.getElementById('partialAmountRow');
        var partialInput = document.getElementById('partialAmount');
        var submitBtn = document.getElementById('submitBtn');
        
        if (type === 'partial') {
            partialRow.style.display = 'block';
            submitBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Process Partial Payment';
            submitBtn.className = 'btn btn-warning';
        } else {
            partialRow.style.display = 'none';
            partialInput.value = '0';
            partialInput.dataset.rawValue = 0;
            submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Process Full Payment';
            submitBtn.className = 'btn btn-success';
        }
        
        updateTotals();
    }

    // ================================================================
    // UPDATE TOTALS
    // ================================================================
    function updateTotals() {
        var balance = <?= $remaining_balance ?>;
        var discount = getRawValue(document.getElementById('discountAmount'));
        var partial = getRawValue(document.getElementById('partialAmount'));
        var paymentType = document.getElementById('paymentType').value;
        var currency = '<?= $currency ?>';
        
        if (discount > balance) {
            discount = balance;
            document.getElementById('discountAmount').value = formatNumber(discount);
            document.getElementById('discountAmount').dataset.rawValue = discount;
        }
        
        var amountToPay = 0;
        var newBalance = 0;
        
        if (paymentType === 'full') {
            amountToPay = balance - discount;
            if (amountToPay < 0) amountToPay = 0;
            newBalance = balance - amountToPay - discount;
            if (newBalance < 0) newBalance = 0;
        } else {
            if (partial > balance) {
                partial = balance;
                document.getElementById('partialAmount').value = formatNumber(partial);
                document.getElementById('partialAmount').dataset.rawValue = partial;
            }
            amountToPay = partial;
            newBalance = balance - partial - discount;
            if (newBalance < 0) newBalance = 0;
        }
        
        document.getElementById('displayBalance').textContent = currency + ' ' + formatNumber(balance);
        document.getElementById('displayDiscount').textContent = '- ' + currency + ' ' + formatNumber(discount);
        document.getElementById('displayAmountToPay').textContent = currency + ' ' + formatNumber(amountToPay);
        document.getElementById('displayNewBalance').textContent = currency + ' ' + formatNumber(newBalance);
        
        if (newBalance <= 0) {
            document.getElementById('displayNewBalance').style.color = 'var(--success)';
        } else {
            document.getElementById('displayNewBalance').style.color = 'var(--danger)';
        }
    }

    function formatNumber(num) {
        return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

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
    // FORM VALIDATION
    // ================================================================
    document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
        var paymentType = document.getElementById('paymentType').value;
        var discount = getRawValue(document.getElementById('discountAmount'));
        var balance = <?= $remaining_balance ?>;
        
        if (discount > balance) {
            e.preventDefault();
            showToast('⚠️ Invalid Discount', 'Discount cannot exceed remaining balance', 'warning');
            return false;
        }
        
        if (paymentType === 'partial') {
            var partial = getRawValue(document.getElementById('partialAmount'));
            if (partial <= 0) {
                e.preventDefault();
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return false;
            }
            if (partial > balance) {
                e.preventDefault();
                showToast('⚠️ Amount Exceeds', 'Partial amount cannot exceed remaining balance', 'warning');
                return false;
            }
        }
        
        var confirmMsg = '💰 Payment Confirmation\n' +
                         '═══════════════════════════\n' +
                         'Type: ' + (paymentType === 'full' ? 'Full Payment' : 'Partial Payment') + '\n' +
                         'Method: ' + document.getElementById('paymentMethod').selectedOptions[0].text + '\n' +
                         'Balance: ' + '<?= $currency ?> ' + formatNumber(balance) + '\n' +
                         (discount > 0 ? 'Discount: -<?= $currency ?> ' + formatNumber(discount) + '\n' : '') +
                         '───────────────────────────\n' +
                         'Amount to Pay: <?= $currency ?> ' + document.getElementById('displayAmountToPay').textContent.replace('<?= $currency ?> ', '') + '\n' +
                         'New Balance: <?= $currency ?> ' + document.getElementById('displayNewBalance').textContent.replace('<?= $currency ?> ', '') + '\n\n' +
                         'Confirm this payment?';
        
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
        
        var btn = document.getElementById('submitBtn');
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        btn.disabled = true;
        
        return true;
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Set initial payment type
        var initialType = '<?= $remaining_balance > 0 && $pending_items_count > 0 ? "partial" : "full" ?>';
        setPaymentType(initialType);
        updateTotals();
    });

    console.log('%c💰 Braick - Add Payment (ADMIN - GREEN THEME)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Balance: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Green theme - matching cashier process_payment page', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>