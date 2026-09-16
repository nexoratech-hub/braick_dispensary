<?php
// ================================================================
// FILE: frontend/pages/admin/add_payment.php
// ADMIN - ADD PAYMENT TO BILL (GREEN THEME - SAME AS CASHIER)
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$bill_item_id = isset($_GET['bill_item_id']) ? (int)$_GET['bill_item_id'] : 0;
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$selected_branch_id = $_GET['branch'] ?? $_GET['branch_id'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_params');
    exit;
}

// ================================================================
// FETCH BILL DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender,
            p.date_of_birth,
            u.full_name as created_by_name,
            br.name as branch_name
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN branches br ON b.branch_id = br.id
        WHERE b.id = ?
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
// FETCH BILL ITEM
// ================================================================
$bill_item = null;
if ($bill_item_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE id = ? AND bill_id = ?");
        $stmt->execute([$bill_item_id, $bill_id]);
        $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $bill_item = null; }
}

// ================================================================
// FETCH ALL BILL ITEMS
// ================================================================
$all_items = [];
try {
    $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
    $stmt->execute([$bill_id]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $all_items = []; }

// ================================================================
// CALCULATE TOTALS
// ================================================================
$subtotal = 0;
$total_paid = 0;
$pending_total = 0;
$pending_items_count = 0;

foreach ($all_items as $item) {
    $item_total = $item['total_price'] ?? 0;
    $subtotal += $item_total;
    if ($item['status'] === 'paid' || $item['status'] === 'refunded') {
        $total_paid += $item_total;
    } else {
        $pending_total += $item_total;
        $pending_items_count++;
    }
}

$discount_amount = $bill['discount_amount'] ?? 0;
$pharmacy_discount = $bill['pharmacy_discount'] ?? 0;
$cashier_discount = $bill['cashier_discount'] ?? 0;
$total_discount = $bill['total_discount'] ?? 0;
$total_amount = $bill['total_amount'] ?? $subtotal;
$paid_amount = $bill['paid_amount'] ?? 0;
$remaining_balance = $bill['balance'] ?? ($total_amount - $paid_amount);

if ($remaining_balance < 0) $remaining_balance = 0;

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
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

$user_branch_name = '';
if ($user_branch_id > 0) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) $user_branch_name = $branch['name'];
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$currency = 'TSh';

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

        $stmt = $db->prepare("SELECT balance, patient_id, total_amount, paid_amount, status FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $current_bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_bill) throw new Exception("Bill not found");

        $current_balance = (float)$current_bill['balance'];
        $patient_id = $current_bill['patient_id'];

        if ($payment_type === 'full') {
            $amount_to_pay = $current_balance - $discount_amount;
            if ($amount_to_pay < 0) $amount_to_pay = 0;
            $new_balance = 0;
            $status = 'paid';
            $bill_discount = $discount_amount;
        } else {
            if ($partial_amount <= 0) throw new Exception("Please enter a valid partial amount");
            if ($partial_amount > $current_balance) throw new Exception("Partial amount cannot exceed balance");

            $amount_to_pay = $partial_amount;
            $new_balance = $current_balance - $partial_amount - $discount_amount;
            if ($new_balance < 0) $new_balance = 0;
            $status = $new_balance > 0 ? 'partial' : 'paid';
            $bill_discount = $discount_amount;

            if ($discount_amount > $current_balance - $partial_amount) {
                throw new Exception("Discount cannot exceed remaining balance after partial payment");
            }
        }

        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        if ($payment_type === 'partial') {
            $receipt_number = 'RCP-PARTIAL-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }

        $stmt = $db->prepare("
            UPDATE bills 
            SET paid_amount = paid_amount + ?,
                balance = ?,
                discount_amount = discount_amount + ?,
                total_discount = total_discount + ?,
                cashier_discount = cashier_discount + ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $amount_to_pay, $new_balance, $bill_discount,
            $bill_discount, $bill_discount, $status, $bill_id
        ]);

        if ($status === 'paid') {
            $stmt = $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE bill_id = ? AND status = 'pending'");
            $stmt->execute([$bill_id]);
        } elseif ($status === 'partial' && $bill_item_id > 0) {
            $stmt = $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE id = ? AND bill_id = ?");
            $stmt->execute([$bill_item_id, $bill_id]);
        }

        $stmt = $db->prepare("
            INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $notes = $bill_discount > 0 ? 'Discount: ' . $currency . ' ' . number_format($bill_discount, 0) : '';
        $notes .= $payment_type === 'partial' ? ' Partial payment' : ' Full payment';
        $stmt->execute([
            $receipt_number, $bill_id, $patient_id, $amount_to_pay,
            $payment_method, $user_id, $bill['branch_id'] ?? $user_branch_id, trim($notes)
        ]);

        $stmt = $db->prepare("SELECT id FROM otc_sales WHERE bill_id = ? AND payment_status IN ('pending', 'partial')");
        $stmt->execute([$bill_id]);
        $otc_sale = $stmt->fetch();

        if ($otc_sale) {
            $stmt = $db->prepare("UPDATE otc_sales SET payment_status = ?, updated_at = NOW() WHERE bill_id = ?");
            $stmt->execute([$status === 'paid' ? 'paid' : 'partial', $bill_id]);
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
            SELECT b.*, p.full_name as patient_name, p.patient_id as patient_code,
                   p.phone as patient_phone, p.gender, p.date_of_birth,
                   u.full_name as created_by_name, br.name as branch_name
            FROM bills b
            LEFT JOIN patients p ON b.patient_id = p.id
            LEFT JOIN users u ON b.created_by = u.id
            LEFT JOIN branches br ON b.branch_id = br.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
        $stmt->execute([$bill_id]);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subtotal = 0; $total_paid = 0; $pending_total = 0; $pending_items_count = 0;
        foreach ($all_items as $item) {
            $item_total = $item['total_price'] ?? 0;
            $subtotal += $item_total;
            if ($item['status'] === 'paid' || $item['status'] === 'refunded') {
                $total_paid += $item_total;
            } else {
                $pending_total += $item_total;
                $pending_items_count++;
            }
        }
        $discount_amount = $bill['discount_amount'] ?? 0;
        $total_amount = $bill['total_amount'] ?? $subtotal;
        $paid_amount = $bill['paid_amount'] ?? 0;
        $remaining_balance = $bill['balance'] ?? ($total_amount - $paid_amount);
        if ($remaining_balance < 0) $remaining_balance = 0;

    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Payment error: " . $e->getMessage());
    }
}

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - GREEN THEME + DARK MODE -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES - GREEN THEME
       ================================================================ */
    :root {
        --page-green: #059669;
        --page-green-dark: #047857;
        --page-green-light: #34D399;
        --page-green-bg: #D1FAE5;
        --page-success: #059669;
        --page-success-dark: #047857;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-input-bg: #FFFFFF;
        --page-hover: #F8FAFC;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
        --page-shadow-md: 0 4px 6px rgba(0,0,0,0.07);
        --page-shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        --page-header-from: #059669;
        --page-header-to: #047857;
        --page-header-shadow: rgba(5, 150, 105, 0.25);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-input-bg: #1E293B;
        --page-hover: #0F172A;
        --page-green-bg: #1A3A2A;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B5F;
        --page-header-from: #047857;
        --page-header-to: #065F46;
        --page-header-shadow: rgba(5, 150, 105, 0.15);
    }

    /* ================================================================
       BODY & MAIN CONTENT - DARK MODE
       ================================================================ */
    body {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    .main-content {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
    }

    /* ================================================================
       GREEN PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #059669 0%, #047857 40%, #065F46 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(5, 150, 105, 0.3), 0 4px 12px rgba(5, 150, 105, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(5, 150, 105, 0.4), 0 6px 16px rgba(5, 150, 105, 0.25);
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        position: relative;
        z-index: 2;
        letter-spacing: -0.02em;
    }

    .page-header-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-subtitle strong {
        color: white;
        font-weight: 700;
    }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(10px);
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       SUMMARY BOX
       ================================================================ */
    .summary-box {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-left: 5px solid #059669;
        margin-bottom: 24px;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
        box-shadow: var(--page-shadow-sm);
    }

    html[data-theme="dark"] .summary-box {
        background: #1E293B;
        border-color: #334155;
        border-left-color: #34D399;
    }

    .summary-box .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .summary-box .summary-item {
        border-bottom-color: #334155;
    }

    .summary-box .summary-item:last-child { border-bottom: none; }

    .summary-box .summary-item .label {
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
    }

    .summary-box .summary-item .value {
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .summary-box .summary-item .value { color: #F1F5F9; }

    .summary-box .summary-item.total {
        border-top: 2px solid var(--page-border, #E2E8F0);
        padding-top: 10px;
        margin-top: 6px;
        font-size: 1rem;
    }

    .summary-box .summary-item.total .label { font-weight: 700; }
    .summary-box .summary-item.total .value { color: #059669; font-size: 1.15rem; }

    .summary-box .summary-item.balance {
        background: rgba(220, 38, 38, 0.08);
        padding: 10px 14px;
        border-radius: 10px;
        margin-top: 8px;
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .summary-box .summary-item.balance .label { color: #DC2626; font-weight: 700; }
    .summary-box .summary-item.balance .value { color: #DC2626; font-size: 1.15rem; font-weight: 800; }

    html[data-theme="dark"] .summary-box .summary-item.balance {
        background: rgba(248, 113, 113, 0.1);
        border-color: rgba(248, 113, 113, 0.3);
    }

    html[data-theme="dark"] .summary-box .summary-item.balance .label,
    html[data-theme="dark"] .summary-box .summary-item.balance .value { color: #F87171; }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border, #E2E8F0);
        max-width: 1100px;
        margin: 0 auto;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-md);
    }

    html[data-theme="dark"] .form-card {
        background: #1E293B;
        border-color: #334155;
    }

    .form-card:hover {
        border-color: #059669;
        box-shadow: var(--page-shadow-lg);
    }

    .form-card .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .form-card .form-header {
        border-bottom-color: #334155;
    }

    .form-card .form-header .form-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #059669, #047857);
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
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    html[data-theme="dark"] .form-card .form-header .form-title { color: #F1F5F9; }

    .form-card .form-header .form-subtitle {
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
    }

    /* ================================================================
       FORM ELEMENTS
       ================================================================ */
    .form-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 5px;
        display: block;
    }

    html[data-theme="dark"] .form-label { color: #F1F5F9; }

    .form-label .required { color: #DC2626; margin-left: 2px; }
    .form-label .label-icon { margin-right: 4px; color: #059669; }

    html[data-theme="dark"] .form-label .label-icon { color: #34D399; }

    .form-label .label-badge {
        font-weight: 400;
        font-size: 0.6rem;
        padding: 1px 10px;
        border-radius: 12px;
        background: var(--page-hover, #F1F5F9);
        color: var(--page-text-secondary, #64748B);
        margin-left: 6px;
    }

    html[data-theme="dark"] .form-label .label-badge {
        background: #0F172A;
        color: #94A3B8;
    }

    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    html[data-theme="dark"] .form-control {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control::placeholder { color: #64748B; }
    html[data-theme="dark"] .form-control option { background: #1E293B; color: #F1F5F9; }

    .form-control:focus {
        border-color: #059669;
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
    }

    html[data-theme="dark"] .form-control:focus {
        border-color: #34D399;
        box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.15);
    }

    .form-control:disabled {
        background: var(--page-hover, #F1F5F9);
        color: var(--page-text-secondary, #64748B);
        cursor: not-allowed;
    }

    html[data-theme="dark"] .form-control:disabled {
        background: #1E293B;
        color: #94A3B8;
    }

    select.form-control { appearance: auto; cursor: pointer; }

    /* ================================================================
       AMOUNT INPUT
       ================================================================ */
    .amount-input-wrap {
        position: relative;
        display: block;
        width: 100%;
    }

    .amount-input-wrap .currency-prefix {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        z-index: 1;
    }

    .amount-input-wrap input {
        padding-left: 44px !important;
        text-align: right;
        font-family: 'Courier New', monospace;
        font-size: 0.95rem;
        font-weight: 700;
        width: 100%;
    }

    /* ================================================================
       PAYMENT TYPE TOGGLE
       ================================================================ */
    .payment-type-toggle {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .payment-type-toggle .toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    html[data-theme="dark"] .payment-type-toggle .toggle-btn {
        background: #1E293B;
        border-color: #334155;
        color: #94A3B8;
    }

    .payment-type-toggle .toggle-btn:hover {
        border-color: #059669;
        color: #059669;
        transform: translateY(-1px);
    }

    html[data-theme="dark"] .payment-type-toggle .toggle-btn:hover {
        border-color: #34D399;
        color: #34D399;
    }

    .payment-type-toggle .toggle-btn.active {
        border-color: #059669;
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }

    .payment-type-toggle .toggle-btn.active:hover {
        background: #047857;
        transform: translateY(-1px);
    }

    /* ================================================================
       GRID
       ================================================================ */
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-row { margin-bottom: 20px; }
    .form-row:last-child { margin-bottom: 0; }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn:hover { transform: translateY(-2px); }

    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }

    .btn-success:hover {
        background: #047857;
        box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        color: white;
    }

    .btn-warning {
        background: #D97706;
        color: white;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
    }

    .btn-warning:hover {
        background: #B45309;
        box-shadow: 0 6px 24px rgba(217, 119, 6, 0.35);
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .btn-outline {
        color: #94A3B8;
        border-color: #334155;
    }

    .btn-outline:hover {
        border-color: #059669;
        color: #059669;
    }

    html[data-theme="dark"] .btn-outline:hover {
        border-color: #34D399;
        color: #34D399;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    html[data-theme="dark"] .form-actions {
        border-top-color: #334155;
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box.success { background: #D1FAE5; border: 2px solid #059669; color: #047857; }
    .message-box.error { background: #FEE2E2; border: 2px solid #DC2626; color: #B91C1C; }

    html[data-theme="dark"] .message-box.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .message-box.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 9999;
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

    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }

    /* ================================================================
       SPINNER
       ================================================================ */
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

    /* ================================================================
       HELP TEXT
       ================================================================ */
    .help-text {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
        display: block;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .grid-2 { grid-template-columns: 1fr; gap: 14px; }
        .form-card { padding: 18px 16px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .payment-type-toggle { flex-direction: column; }
        .payment-type-toggle .toggle-btn { width: 100%; justify-content: center; }
        .summary-box { padding: 16px 18px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .page-header-card, .form-actions, .btn, .btn-outline-light, .payment-type-toggle, .toast-custom { display: none !important; }
        .form-card, .summary-box { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Green Page Header Card -->
    <div class="page-header-card">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-hand-holding-usd"></i>
                Add Payment
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-file-invoice"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="page-header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="page-header-badge" style="background:rgba(255,255,255,0.25);">
                    <i class="fas fa-money-bill"></i>
                    Balance: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Bill
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-list"></i> All Bills
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;margin-top:2px;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Bill Summary -->
    <div class="summary-box">
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
            <span class="value" style="color:#059669;"><?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span>
        </div>
        <div class="summary-item">
            <span class="label">Discount</span>
            <span class="value" style="color:#D97706;">- <?= $currency ?> <?= number_format($bill['discount_amount'] ?? 0, 0) ?></span>
        </div>
        <div class="summary-item total">
            <span class="label">Grand Total</span>
            <span class="value"><?= $currency ?> <?= number_format(($bill['total_amount'] ?? 0) - ($bill['discount_amount'] ?? 0), 0) ?></span>
        </div>
        <div class="summary-item balance">
            <span class="label"><i class="fas fa-exclamation-triangle"></i> Remaining Balance</span>
            <span class="value"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="form-card">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div>
                <h3 class="form-title">Process Payment</h3>
                <p class="form-subtitle">Enter payment details for bill <?= htmlspecialchars($bill['bill_number'] ?? '') ?></p>
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
                            <option value="<?= $key ?>" <?= $key === 'cash' ? 'selected' : '' ?>><?= $label ?></option>
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
                    <span class="help-text">Maximum discount: <?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                </div>

                <!-- Partial Amount -->
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
                    <span class="help-text">Amount to pay now</span>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="summary-box" style="margin-top:16px;border-left-color:#34D399;">
                <div class="summary-item">
                    <span class="label">Remaining Balance</span>
                    <span class="value" id="displayBalance"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Discount</span>
                    <span class="value" id="displayDiscount" style="color:#D97706;">- <?= $currency ?> 0</span>
                </div>
                <div class="summary-item total" style="border-top-color:#059669;">
                    <span class="label" style="font-weight:700;">Amount to Pay</span>
                    <span class="value" id="displayAmountToPay" style="color:#059669;font-size:1.2rem;"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                </div>
                <div class="summary-item" style="border-top:1px solid var(--page-border);padding-top:10px;margin-top:6px;">
                    <span class="label">New Balance</span>
                    <span class="value" id="displayNewBalance" style="color:#DC2626;"><?= $currency ?> 0</span>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-success" id="submitBtn">
                    <i class="fas fa-check-circle"></i> Process Payment
                </button>
                <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

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
        input.dataset.rawValue = parseFloat(val) || 0;
    }

    function getRawValue(input) {
        var raw = input.dataset.rawValue;
        if (raw !== undefined && raw !== '') return parseFloat(raw) || 0;
        var val = input.value.replace(/,/g, '');
        return parseFloat(val) || 0;
    }

    function formatNumber(num) {
        return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ================================================================
    // SET PAYMENT TYPE
    // ================================================================
    function setPaymentType(type) {
        document.getElementById('paymentType').value = type;

        var buttons = document.querySelectorAll('.payment-type-toggle .toggle-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.dataset.type === type) btn.classList.add('active');
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
            document.getElementById('displayNewBalance').style.color = '#059669';
        } else {
            document.getElementById('displayNewBalance').style.color = '#DC2626';
        }
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
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
                         'Amount to Pay: ' + document.getElementById('displayAmountToPay').textContent + '\n' +
                         'New Balance: ' + document.getElementById('displayNewBalance').textContent + '\n\n' +
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
        var initialType = '<?= $remaining_balance > 0 ? "partial" : "full" ?>';
        setPaymentType(initialType);
        updateTotals();
    });

    console.log('%c💰 Braick - Add Payment (GREEN THEME)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill["bill_number"] ?? "N/A") ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Balance: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>