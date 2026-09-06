<?php
// ================================================================
// FILE: frontend/pages/cashier/process_otc_payment.php
// CASHIER - PROCESS OTC PAYMENT
// FIXED: Updates sold_by to Cashier ID (not pharmacist)
// FIXED: Dark Purple theme (OTC branding)
// FIXED: Shows discount from otc_sales table before payment
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

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET SALE ID
// ================================================================
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

if ($sale_id <= 0) {
    header('Location: pending_bills.php?error=Invalid OTC sale ID');
    exit;
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // GET OTC SALE DETAILS - Include discount
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            o.*,
            o.discount_amount,
            o.subtotal,
            o.total_amount,
            u.full_name as pharmacist_name
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        WHERE o.id = ? AND o.branch_id = ? AND o.payment_status = 'pending'
    ");
    $stmt->execute([$sale_id, $user_branch_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        $message = "OTC sale not found or already paid!";
        $message_type = 'error';
    }

    // ================================================================
    // GET OTC ITEMS
    // ================================================================
    $items = [];
    if ($sale) {
        $stmt = $db->prepare("
            SELECT * FROM otc_sale_items 
            WHERE sale_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // HANDLE PAYMENT SUBMISSION
    // ✅ FIXED: Updates sold_by to Cashier ID
    // ✅ FIXED: Includes discount in payment notes
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        
        if ($amount <= 0) {
            $message = "Please enter a valid amount!";
            $message_type = 'error';
        } elseif ($amount != $sale['total_amount']) {
            $message = "Amount must be exactly " . $currency . " " . number_format($sale['total_amount'], 0);
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                // Generate receipt number
                $receipt_number = 'RCP-OTC-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // ✅ FIXED: Update OTC sale - set sold_by to Cashier ID
                $stmt = $db->prepare("
                    UPDATE otc_sales 
                    SET payment_status = 'paid',
                        payment_method = ?,
                        sold_by = ?,
                        updated_at = NOW()
                    WHERE id = ? AND branch_id = ? AND payment_status = 'pending'
                ");
                $stmt->execute([
                    $payment_method, 
                    $user_id,  // ✅ Cashier ID
                    $sale_id, 
                    $user_branch_id
                ]);
                
                // ✅ Insert payment record with discount info
                $discount_note = '';
                if (($sale['discount_amount'] ?? 0) > 0) {
                    $discount_note = ' | Discount Applied: ' . $currency . ' ' . number_format($sale['discount_amount'], 0);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO payments (
                        receipt_number, 
                        bill_id, 
                        patient_id, 
                        amount, 
                        payment_method, 
                        received_by, 
                        branch_id, 
                        received_at, 
                        notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $receipt_number,
                    $sale['bill_id'] ?? null,
                    $sale['patient_id'] ?? null,
                    $amount,
                    $payment_method,
                    $user_id,
                    $user_branch_id,
                    'OTC Sale #' . $sale['sale_number'] . ' payment processed by ' . $user_full_name . $discount_note
                ]);
                $payment_id = $db->lastInsertId();
                
                // Update bill if exists
                if (!empty($sale['bill_id'])) {
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET paid_amount = paid_amount + ?,
                            balance = balance - ?,
                            status = 'paid',
                            updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$amount, $amount, $sale['bill_id'], $user_branch_id]);
                    
                    // Update bill items
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET status = 'paid',
                            updated_at = NOW()
                        WHERE bill_id = ?
                    ");
                    $stmt->execute([$sale['bill_id']]);
                }
                
                $db->commit();
                
                // Redirect to receipt with cashier info
                if (!empty($payment_id)) {
                    header('Location: receipt.php?payment_id=' . $payment_id);
                } else {
                    header('Location: pending_bills.php?success=OTC payment processed successfully by ' . $user_full_name);
                }
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error processing payment: " . $e->getMessage();
                $message_type = 'error';
                error_log("OTC payment error: " . $e->getMessage());
            }
        }
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $sale = null;
    $items = [];
    error_log("Process OTC payment error: " . $e->getMessage());
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process OTC Payment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            /* ✅ DARK PURPLE THEME - OTC Branding */
            --primary: #6D28D9;
            --primary-dark: #4C1D95;
            --primary-light: #A78BFA;
            --primary-bg: #2A1A3A;
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
            --otc-color: #8B5CF6;
            --otc-bg: #2A1A3A;
            --otc-header-bg-from: #4C1D95;
            --otc-header-bg-to: #6D28D9;
            --otc-table-header: linear-gradient(135deg, #4C1D95, #6D28D9, #8B5CF6);
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1A1A2E;
            --gray-100: #1E293B;
            --gray-200: #2D3748;
            --gray-300: #4A5568;
            --gray-400: #718096;
            --gray-500: #A0AEC0;
            --gray-600: #CBD5E1;
            --gray-700: #E2E8F0;
            --primary-bg: #1A1A2E;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3A2A1A;
            --purple-bg: #1A1A3A;
            --otc-bg: #1A1A3A;
            --otc-header-bg-from: #3B1A6A;
            --otc-header-bg-to: #5B2A8A;
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
        ::-webkit-scrollbar-thumb { background: var(--otc-color); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ✅ DARK PURPLE PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #4C1D95, #6D28D9, #8B5CF6);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(76, 29, 149, 0.35);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .page-header {
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(139, 92, 246, 0.08);
            border-radius: 50%;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .sale-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .sale-card:hover {
            border-color: var(--otc-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        /* ✅ DARK PURPLE SALE HEADER */
        .sale-header {
            background: linear-gradient(135deg, #4C1D95, #6D28D9);
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            color: white;
        }
        
        .sale-header .sale-number {
            font-weight: 700;
            font-size: 1.1rem;
            color: #C4B5FD;
            font-family: monospace;
        }
        
        .sale-header .sale-status {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            background: rgba(251, 191, 36, 0.2);
            color: #FCD34D;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }
        
        .sale-body {
            padding: 20px 24px;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
            padding-bottom: 16px;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 16px;
        }
        
        .customer-info .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .customer-info .info-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .customer-info .info-item .value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        /* ✅ DARK PURPLE TABLE HEADER */
        .items-table-wrap {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 12px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: white;
            background: linear-gradient(135deg, #4C1D95, #6D28D9, #8B5CF6);
            border-bottom: 3px solid #A78BFA;
            white-space: nowrap;
        }
        
        .items-table thead th:first-child { border-radius: 6px 0 0 0; }
        .items-table thead th:last-child { border-radius: 0 6px 0 0; }
        
        .items-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-table tbody tr:hover td {
            background: var(--purple-bg);
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .items-table .text-right { text-align: right; }
        .items-table .font-mono { font-family: 'Courier New', monospace; }
        .items-table .item-name { font-weight: 500; }
        .items-table .item-instruction {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-style: italic;
            display: block;
            margin-top: 2px;
        }
        
        /* ✅ TOTALS WITH DISCOUNT */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 2px solid var(--border-color);
        }
        
        .totals-box {
            width: 100%;
            max-width: 350px;
        }
        
        .totals-box .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.85rem;
            align-items: center;
        }
        
        .totals-box .total-row .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .totals-box .total-row .value {
            font-weight: 600;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
        }
        
        .totals-box .total-row.discount-row {
            color: var(--warning);
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 8px;
            margin-bottom: 4px;
        }
        
        .totals-box .total-row.discount-row .value {
            color: var(--warning);
        }
        
        .totals-box .total-row.grand-total {
            border-top: 2px solid var(--border-color);
            padding-top: 10px;
            margin-top: 4px;
            font-size: 1rem;
        }
        
        .totals-box .total-row.grand-total .label {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .totals-box .total-row.grand-total .value {
            color: var(--otc-color);
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        /* ✅ PAYMENT SECTION */
        .payment-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 20px 24px;
            max-width: 800px;
            margin: 20px auto 0;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .payment-section:hover {
            border-color: var(--otc-color);
            box-shadow: var(--shadow-md);
        }
        
        .payment-section .payment-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .payment-section .payment-title i {
            color: var(--otc-color);
        }
        
        .payment-section .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: end;
        }
        
        .payment-section .form-group {
            flex: 1;
            min-width: 180px;
        }
        
        .payment-section .form-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 4px;
        }
        
        .payment-section .form-control {
            width: 100%;
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            font-family: monospace;
        }
        
        .payment-section .form-control:focus {
            border-color: var(--otc-color);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }
        
        .payment-section .form-control:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .payment-section .amount-display {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--otc-color);
            font-family: monospace;
            padding: 6px 14px;
            background: var(--purple-bg);
            border-radius: var(--radius);
            border: 2px solid var(--otc-color);
            display: inline-block;
            min-width: 200px;
            text-align: center;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-otc {
            background: linear-gradient(135deg, #6D28D9, #8B5CF6);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        .btn-otc:hover {
            background: linear-gradient(135deg, #5B21B6, #7C3AED);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--otc-color);
            color: var(--otc-color);
            transform: translateY(-2px);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        .btn-block { width: 100%; justify-content: center; }
        
        .cashier-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
            background: rgba(139, 92, 246, 0.25);
            color: #C4B5FD;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }
        
        /* ✅ DISCOUNT BADGE */
        .discount-badge-display {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #D97706;
        }
        
        [data-theme="dark"] .discount-badge-display {
            background: #3A2A1A;
            color: #F59E0B;
            border-color: #D97706;
        }
        
        .message-box {
            padding: 12px 20px;
            border-radius: var(--radius);
            border: 2px solid transparent;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .message-box.success {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        .message-box.error {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        .message-box.warning {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }
        .message-box i { font-size: 1.2rem; }
        
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--otc-color); font-weight: 600; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
            max-width: 800px;
            margin: 0 auto;
        }
        .empty-state i { font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 12px; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-primary); margin-bottom: 8px; }
        .empty-state p { color: var(--text-secondary); font-size: 0.9rem; }
        
        .pharmacist-info {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.7);
            background: rgba(255,255,255,0.08);
            padding: 4px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        [data-theme="dark"] .pharmacist-info {
            background: rgba(255,255,255,0.05);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .customer-info { grid-template-columns: 1fr; }
            .payment-section .form-row { flex-direction: column; }
            .payment-section .form-group { width: 100%; }
            .sale-body { padding: 14px 16px; }
            .payment-section { padding: 14px 16px; }
            .totals-box { max-width: 100%; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER - DARK PURPLE -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                Process OTC Payment
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(139,92,246,0.3);border-color:rgba(139,92,246,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> RECEPTION
                    </span>
                <?php endif; ?>
                <span class="cashier-badge">
                    <i class="fas fa-user-tie"></i> Cashier: <?= htmlspecialchars($user_full_name) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-cash-register"></i>
                Complete OTC sale payment
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="header-badge" style="background:rgba(139,92,246,0.2);border-color:rgba(139,92,246,0.2);">
                    <i class="fas fa-shopping-cart"></i> OTC Payment
                </span>
                <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FCD34D;">
                        <i class="fas fa-tag"></i> Discount: <?= $currency ?> <?= number_format($sale['discount_amount'] ?? 0, 0) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($sale): ?>
    
    <!-- SALE CARD -->
    <div class="sale-card animate-fade-in-up">
        <div class="sale-header">
            <div>
                <span class="sale-number"><?= htmlspecialchars($sale['sale_number']) ?></span>
                <span style="font-size:0.7rem;color:rgba(255,255,255,0.6);margin-left:10px;">
                    <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?>
                </span>
            </div>
            <div>
                <span class="sale-status"><i class="fas fa-clock"></i> Pending</span>
                <!-- ✅ Shows pharmacist who created sale -->
                <span class="pharmacist-info" style="margin-left:8px;">
                    <i class="fas fa-user-md"></i> Created by: <?= htmlspecialchars($sale['pharmacist_name'] ?? 'N/A') ?>
                </span>
            </div>
        </div>
        
        <div class="sale-body">
            <!-- Customer Info -->
            <div class="customer-info">
                <div class="info-item">
                    <span class="label">Customer Name</span>
                    <span class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($sale['patient_id'])): ?>
                    <div class="info-item">
                        <span class="label">Patient ID</span>
                        <span class="value"><?= htmlspecialchars($sale['patient_id']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="label">Payment Status</span>
                    <span class="value" style="color:var(--warning);font-weight:600;">⏳ Pending</span>
                </div>
            </div>
            
            <!-- Items Table - DARK PURPLE HEADER -->
            <?php if (count($items) > 0): ?>
            <div class="items-table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Item Name</th>
                            <th style="text-align:right;">Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="item-name"><?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? 'N/A') ?></span>
                                    <?php if (!empty($item['instructions'])): ?>
                                        <span class="item-instruction">
                                            <i class="fas fa-edit"></i> <?= htmlspecialchars($item['instructions']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                                <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-4" style="color:var(--text-secondary);">
                    <i class="fas fa-box-open mr-1"></i> No items in this sale
                </div>
            <?php endif; ?>
            
            <!-- ✅ TOTALS WITH DISCOUNT -->
            <div class="totals-section">
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label"><i class="fas fa-calculator"></i> Subtotal</span>
                        <span class="value"><?= $currency ?> <?= number_format($sale['subtotal'] ?? 0, 0) ?></span>
                    </div>
                    
                    <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                        <div class="total-row discount-row">
                            <span class="label">
                                <i class="fas fa-tag"></i> Discount 
                                <span class="discount-badge-display">-<?= $currency ?> <?= number_format($sale['discount_amount'], 0) ?></span>
                            </span>
                            <span class="value" style="color:var(--warning);">
                                -<?= $currency ?> <?= number_format($sale['discount_amount'], 0) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="total-row" style="color:var(--text-secondary);">
                            <span class="label"><i class="fas fa-tag"></i> Discount</span>
                            <span class="value" style="color:var(--text-secondary);">None</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="total-row grand-total">
                        <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                        <span class="value"><?= $currency ?> <?= number_format($sale['total_amount'] ?? 0, 0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PAYMENT SECTION -->
    <div class="payment-section animate-fade-in-up">
        <div class="payment-title">
            <i class="fas fa-cash-register"></i>
            Complete Payment
            <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);margin-left:4px;">
                (No additional discount)
            </span>
            <span style="font-size:0.6rem;font-weight:600;color:var(--otc-color);margin-left:auto;background:var(--purple-bg);padding:2px 12px;border-radius:12px;border:1px solid var(--otc-color);">
                <i class="fas fa-user-tie"></i> Cashier: <?= htmlspecialchars($user_full_name) ?>
            </span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="process_payment">
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-money-bill"></i> Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">💰 Cash</option>
                        <option value="m-pesa">📱 M-Pesa</option>
                        <option value="airtel_money">📱 Airtel Money</option>
                        <option value="tigo_pesa">📱 Tigo Pesa</option>
                        <option value="halopesa">📱 HaloPesa</option>
                        <option value="card">💳 Card</option>
                        <option value="bank">🏦 Bank Transfer</option>
                        <option value="other">📦 Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-coins"></i> Amount to Pay</label>
                    <div class="amount-display">
                        <?= $currency ?> <?= number_format($sale['total_amount'], 0) ?>
                    </div>
                    <input type="hidden" name="amount" value="<?= $sale['total_amount'] ?>">
                    <span style="font-size:0.6rem;color:var(--text-secondary);">
                        <i class="fas fa-info-circle"></i> Full amount required
                        <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                            <span style="color:var(--warning);margin-left:4px;">
                                (Discount: <?= $currency ?> <?= number_format($sale['discount_amount'], 0) ?> applied)
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="form-group" style="flex:0 0 auto;">
                    <button type="submit" class="btn btn-otc btn-block" style="padding:10px 32px;">
                        <i class="fas fa-check-circle"></i> Complete Payment
                    </button>
                </div>
            </div>
            
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border-color);display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="pending_bills.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <span style="font-size:0.6rem;color:var(--text-secondary);display:flex;align-items:center;">
                    <i class="fas fa-lock mr-1"></i> 
                    Total: <?= $currency ?> <?= number_format($sale['total_amount'], 0) ?>
                    <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                        <span style="margin-left:6px;color:var(--warning);">
                            (Discount: <?= $currency ?> <?= number_format($sale['discount_amount'], 0) ?> applied)
                        </span>
                    <?php endif; ?>
                </span>
                <span style="font-size:0.55rem;color:var(--text-secondary);background:var(--gray-50);padding:2px 10px;border-radius:10px;border:1px solid var(--border-color);">
                    <i class="fas fa-user-tie"></i> Processing by: <?= htmlspecialchars($user_full_name) ?>
                </span>
            </div>
        </form>
    </div>
    
    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-shopping-cart"></i>
            <h3>OTC Sale Not Found</h3>
            <p>The OTC sale you are trying to process does not exist or has already been paid.</p>
            <a href="pending_bills.php" class="btn btn-otc mt-4">
                <i class="fas fa-arrow-left"></i> Back to Pending Bills
            </a>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Process OTC Payment
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
                <?php if ($is_reception): ?>
                    <span style="color:#FCD34D;font-weight:500;font-size:0.6rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 Reception</span>
                <?php endif; ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
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
    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        syncDarkMode();
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDarkMode();
        });
        document.addEventListener('darkModeChanged', function(e) {
            if (e.detail && e.detail.isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        });
    })();

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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

    console.log('%c🛒 Braick - Process OTC Payment (DARK PURPLE + DISCOUNT)', 'font-size:18px; font-weight:bold; color:#8B5CF6;');
    console.log('%c✅ FIXED: Updates sold_by to Cashier ID (not pharmacist)', 'font-size:13px; color:#A78BFA;');
    console.log('%c✅ FIXED: Shows discount from otc_sales table', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Cashier: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#FBBF24;');
    console.log('%c✅ Pharmacist: <?= htmlspecialchars($sale['pharmacist_name'] ?? 'N/A') ?> (created sale)', 'font-size:13px; color:#64748B;');
    <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
        console.log('%c✅ Discount: <?= $currency ?> <?= number_format($sale['discount_amount'], 0) ?> applied', 'font-size:13px; color:#FCD34D;');
    <?php else: ?>
        console.log('%c✅ No discount applied', 'font-size:13px; color:#64748B;');
    <?php endif; ?>
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($sale['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#8B5CF6;');
</script>

</body>
</html>