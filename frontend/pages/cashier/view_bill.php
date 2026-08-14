<?php
// ================================================================
// FILE: frontend/pages/cashier/view_bill.php
// CASHIER - VIEW BILL DETAILS
// DISPLAYS FULL BILL WITH ITEMS, PAYMENTS, AND SUMMARY
// FIXED: Uses shared header with clock
// FIXED: Dark mode fully working with header
// FIXED: Green theme applied throughout
// FIXED: Removed duplicate "Dr." in doctor name
// FIXED: Bill summary grid - 3 items per row
// FIXED: Removed Print button
// FIXED: Removed Reference column from payments table
// ALLOWS: Cashier, Reception, Admin
// BRAICK DISPENSARY
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
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
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

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS ADMIN OR RECEPTION
// ================================================================
$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET BILL ID FROM URL
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bill_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // ================================================================
    // GET BILL DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            pb.*,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone,
            p.email,
            p.gender,
            p.date_of_birth,
            p.address,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            u.full_name as doctor_name,
            u2.full_name as created_by_name,
            b.name as branch_name
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users u2 ON pb.created_by = u2.id
        LEFT JOIN branches b ON pb.branch_id = b.id
        WHERE pb.id = ? AND pb.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: dashboard.php?error=Bill not found');
        exit;
    }

    // ================================================================
    // GET BILL ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
        ORDER BY id ASC
    ");
    $stmt->execute([$bill_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // GET PAYMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE bill_id = ? 
        ORDER BY id DESC
    ");
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // CALCULATE TOTALS
    // ================================================================
    $total_items = count($items);
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (float)$item['total_price'];
    }
    
    $discount_amount = (float)($bill['discount_amount'] ?? 0);
    $total_amount = (float)$bill['total_amount'];
    $paid_amount = (float)$bill['paid_amount'];
    $balance = (float)$bill['balance'];
    $total_payments = count($payments);
    $total_paid_amount = 0;
    foreach ($payments as $payment) {
        $total_paid_amount += (float)$payment['amount'];
    }

    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bill = null;
    $items = [];
    $payments = [];
    $total_items = 0;
    $subtotal = 0;
    $discount_amount = 0;
    $total_amount = 0;
    $paid_amount = 0;
    $balance = 0;
    $total_payments = 0;
    $total_paid_amount = 0;
    $currency = 'TSh';
    error_log("View bill error: " . $e->getMessage());
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #F0FDF4;
            --table-hover: #D1FAE5;
            --footer-border: #E2E8F0;
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #D97706;
            --badge-partial-bg: #DBEAFE;
            --badge-partial-text: #2563EB;
            --badge-paid-bg: #D1FAE5;
            --badge-paid-text: #059669;
            --badge-cancelled-bg: #FEE2E2;
            --badge-cancelled-text: #DC2626;
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
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
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
            --table-stripe: #1A3A2A;
            --table-hover: #1A4A3A;
            --footer-border: #334155;
            --badge-pending-bg: #3D2E0A;
            --badge-pending-text: #FBBF24;
            --badge-partial-bg: #1E3A5F;
            --badge-partial-text: #60A5FA;
            --badge-paid-bg: #1A3A2A;
            --badge-paid-text: #34D399;
            --badge-cancelled-bg: #3A1A1A;
            --badge-cancelled-text: #F87171;
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 30px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--page-header-bg-from), var(--page-header-bg-to));
            border-radius: 14px;
            padding: 20px 28px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 3px 16px var(--page-header-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.6rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
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
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.3s;
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
            transform: translateY(-1px);
        }
        
        /* ================================================================
           BILL SUMMARY CARD - 3 ITEMS PER ROW
           ================================================================ */
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 18px;
        }
        
        .bill-summary-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .bill-summary-card .bill-number-large {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
            font-family: monospace;
        }
        
        .bill-summary-card .bill-status-large {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .bill-summary-card .bill-status-large.pending {
            background: var(--badge-pending-bg);
            color: var(--badge-pending-text);
        }
        .bill-summary-card .bill-status-large.partial {
            background: var(--badge-partial-bg);
            color: var(--badge-partial-text);
        }
        .bill-summary-card .bill-status-large.paid {
            background: var(--badge-paid-bg);
            color: var(--badge-paid-text);
        }
        .bill-summary-card .bill-status-large.cancelled {
            background: var(--badge-cancelled-bg);
            color: var(--badge-cancelled-text);
        }
        
        /* ================================================================
           BILL SUMMARY GRID - 3 COLUMNS
           ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px 24px;
            margin-top: 12px;
        }
        
        .bill-summary-grid .summary-item {
            display: flex;
            flex-direction: column;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .bill-summary-grid .summary-item:nth-last-child(-n+3) {
            border-bottom: none;
        }
        
        .bill-summary-grid .summary-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .bill-summary-grid .summary-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 1px;
        }
        
        .bill-summary-grid .summary-item .value.green {
            color: var(--success);
        }
        
        .bill-summary-grid .summary-item .value.red {
            color: var(--danger);
        }
        
        .bill-summary-grid .summary-item .value.blue {
            color: var(--primary);
        }
        
        /* ================================================================
           PATIENT INFO CARD
           ================================================================ */
        .patient-info-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 18px;
        }
        
        .patient-info-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .patient-info-card .patient-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            background: var(--success);
        }
        
        .patient-info-card .patient-name-large {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .patient-info-card .patient-details {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 3px;
        }
        
        .patient-info-card .patient-details span {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-info-card .patient-details span i {
            color: var(--success);
            width: 15px;
            font-size: 0.7rem;
        }
        
        /* ================================================================
           SUMMARY STATS
           ================================================================ */
        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }
        
        .summary-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .summary-box:hover {
            border-color: var(--success);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-box .number {
            font-size: 1.4rem;
            font-weight: 700;
        }
        .summary-box .number.green { color: var(--success); }
        .summary-box .number.red { color: var(--danger); }
        .summary-box .number.blue { color: var(--primary); }
        .summary-box .number.orange { color: var(--warning); }
        
        .summary-box .label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        /* ================================================================
           TABLES
           ================================================================ */
        .table-wrapper {
            background: var(--bg-card);
            border-radius: 12px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
            transition: all 0.3s ease;
        }
        
        .table-wrapper .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: var(--gray-50);
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
            transition: background 0.3s ease;
        }
        
        [data-theme="dark"] .table-wrapper .table-header {
            background: var(--gray-700);
        }
        
        .table-wrapper .table-header .table-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-wrapper .table-header .table-title i {
            color: var(--success);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 9px 14px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #ffffff;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table td {
            padding: 9px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table .item-total { font-weight: 600; }
        
        /* Payments Table */
        .payments-table thead th {
            background: var(--primary) !important;
            border-bottom-color: var(--primary-dark) !important;
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        .status-badge.pending {
            background: var(--badge-pending-bg);
            color: var(--badge-pending-text);
        }
        .status-badge.partial {
            background: var(--badge-partial-bg);
            color: var(--badge-partial-text);
        }
        .status-badge.paid {
            background: var(--badge-paid-bg);
            color: var(--badge-paid-text);
        }
        .status-badge.cancelled {
            background: var(--badge-cancelled-bg);
            color: var(--badge-cancelled-text);
        }
        
        .payment-method-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 8px;
        }
        .payment-method-badge.cash {
            background: var(--success-bg);
            color: var(--success);
        }
        .payment-method-badge.card {
            background: #DBEAFE;
            color: #2563EB;
        }
        .payment-method-badge.mobile {
            background: var(--purple-bg);
            color: var(--purple);
        }
        .payment-method-badge.bank {
            background: var(--warning-bg);
            color: var(--warning);
        }
        .payment-method-badge.insurance {
            background: #E0E7FF;
            color: #4F46E5;
        }
        [data-theme="dark"] .payment-method-badge.card {
            background: #1E3A5F;
            color: #60A5FA;
        }
        [data-theme="dark"] .payment-method-badge.insurance {
            background: #1E3A5F;
            color: #818CF8;
        }
        
        /* ================================================================
           BUTTONS - NO PRINT BUTTON
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.65rem; border-radius: 6px; }
        .btn-lg { padding: 8px 20px; font-size: 0.85rem; border-radius: 10px; }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            max-width: 1400px;
            margin: 0 auto 14px;
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid transparent;
            font-size: 0.85rem;
            transition: all 0.3s ease;
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
        .message-box i { margin-right: 8px; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 12px 0;
            border-top: 2px solid var(--footer-border);
            margin-top: 18px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 14px;
        }
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .bill-summary-card { padding: 14px 16px; }
            .bill-summary-card .bill-number-large { font-size: 1.2rem; }
            .bill-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .bill-summary-grid .summary-item:nth-last-child(-n+3) {
                border-bottom: 1px solid var(--border-color);
            }
            .bill-summary-grid .summary-item:nth-last-child(-n+2) {
                border-bottom: none;
            }
            .patient-info-card { padding: 14px 16px; }
            .summary-section { grid-template-columns: repeat(3, 1fr); }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 6px 10px; }
            .page-header .btn-outline-light { padding: 5px 10px; font-size: 0.7rem; }
        }
        
        @media (max-width: 480px) {
            .bill-summary-grid {
                grid-template-columns: 1fr;
            }
            .bill-summary-grid .summary-item {
                border-bottom: 1px solid var(--border-color);
            }
            .bill-summary-grid .summary-item:last-child {
                border-bottom: none;
            }
            .summary-section { grid-template-columns: 1fr 1fr; }
            .patient-info-card .patient-details { flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - FROM HEADER -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Bill Details
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.3);border-color:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> RECEPTION
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?>
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-cube"></i>
                    <?= $total_items ?> item(s)
                </span>
                
                <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= $currency ?> <?= number_format($total_amount, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-list"></i> Pending
            </a>
            <!-- PRINT BUTTON REMOVED -->
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($bill): ?>
        
    <!-- ================================================================ -->
    <!-- BILL SUMMARY - 3 ITEMS PER ROW -->
    <!-- ================================================================ -->
    <div class="bill-summary-card animate-fade-in-up">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <div class="bill-number-large">#<?= htmlspecialchars($bill['bill_number']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?>
                    <?php if ($bill['updated_at'] != $bill['created_at']): ?>
                        <span class="mx-1">|</span>
                        <i class="fas fa-edit mr-1"></i>
                        Updated: <?= date('M d, Y h:i A', strtotime($bill['updated_at'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <span class="bill-status-large <?= $bill['status'] ?>">
                    <i class="fas <?= $bill['status'] === 'paid' ? 'fa-check-circle' : ($bill['status'] === 'partial' ? 'fa-clock' : 'fa-hourglass-half') ?>"></i>
                    <?= ucfirst($bill['status']) ?>
                </span>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- GRID: 3 ITEMS PER ROW -->
        <!-- ================================================================ -->
        <div class="bill-summary-grid">
            <!-- Row 1: Branch, Visit, Type -->
            <div class="summary-item">
                <span class="label">Branch</span>
                <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? $user_branch_name) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Visit Number</span>
                <span class="value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Visit Type</span>
                <span class="value capitalize"><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></span>
            </div>
            
            <!-- Row 2: Doctor, Created By, Status -->
            <div class="summary-item">
                <span class="label">Doctor</span>
                <span class="value" style="color:var(--primary);">
                    <?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?>
                </span>
            </div>
            <div class="summary-item">
                <span class="label">Created By</span>
                <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Status</span>
                <span class="value">
                    <span class="bill-status-large <?= $bill['status'] ?>" style="font-size:0.65rem;padding:2px 12px;">
                        <?= ucfirst($bill['status']) ?>
                    </span>
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="patient-info-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="flex items-center gap-4 flex-wrap">
            <div class="patient-avatar">
                <?= strtoupper(substr($bill['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <div class="patient-name-large"><?= htmlspecialchars($bill['patient_name'] ?? 'Unknown Patient') ?></div>
                <div class="patient-details">
                    <span><i class="fas fa-hashtag"></i> <?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($bill['email'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-calendar-alt"></i> <?= $bill['date_of_birth'] ? date('M d, Y', strtotime($bill['date_of_birth'])) : 'N/A' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATS -->
    <!-- ================================================================ -->
    <div class="summary-section">
        <div class="summary-box">
            <p class="number blue"><?= $total_items ?></p>
            <p class="label">📦 Items</p>
        </div>
        <div class="summary-box">
            <p class="number green"><?= $currency ?> <?= number_format($subtotal, 0) ?></p>
            <p class="label">💰 Subtotal</p>
        </div>
        <?php if ($discount_amount > 0): ?>
        <div class="summary-box">
            <p class="number orange">-<?= $currency ?> <?= number_format($discount_amount, 0) ?></p>
            <p class="label">🏷️ Discount</p>
        </div>
        <?php endif; ?>
        <div class="summary-box">
            <p class="number <?= $balance > 0 ? 'red' : 'green' ?>"><?= $currency ?> <?= number_format($total_amount, 0) ?></p>
            <p class="label">📋 Total</p>
        </div>
        <div class="summary-box">
            <p class="number green"><?= $currency ?> <?= number_format($paid_amount, 0) ?></p>
            <p class="label">✅ Paid</p>
        </div>
        <div class="summary-box">
            <p class="number <?= $balance > 0 ? 'red' : 'green' ?>"><?= $currency ?> <?= number_format($balance, 0) ?></p>
            <p class="label">⚖️ Balance</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="table-wrapper animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list-ul"></i>
                Bill Items
                <span class="text-xs" style="color:var(--text-secondary);">(<?= $total_items ?>)</span>
            </div>
            <div class="text-xs" style="color:var(--text-secondary);">
                <i class="fas fa-clock"></i> <?= date('h:i:s A') ?>
            </div>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:35px;text-align:center;">#</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php $counter = 1; foreach ($items as $item): ?>
                            <tr>
                                <td style="text-align:center;"><?= $counter++ ?></td>
                                <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                                <td>
                                    <span class="text-xs capitalize" style="color:var(--text-secondary);">
                                        <?= htmlspecialchars($item['item_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= $item['quantity'] ?? 1 ?></td>
                                <td style="text-align:right;"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="text-align:right;font-weight:600;"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= ($item['payment_status'] ?? 'pending') ?>">
                                        <?= ucfirst($item['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--table-stripe);font-weight:700;">
                            <td colspan="5" style="text-align:right;font-size:0.85rem;">TOTAL</td>
                            <td style="text-align:right;font-size:0.85rem;color:var(--success);">
                                <?= $currency ?> <?= number_format($subtotal, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:25px 20px;color:var(--text-secondary);font-size:0.85rem;">
                                <i class="fas fa-info-circle mr-1"></i> No items found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($discount_amount > 0): ?>
        <div style="padding:8px 18px;border-top:2px solid var(--border-color);display:flex;justify-content:flex-end;gap:18px;flex-wrap:wrap;background:var(--gray-50);">
            <span style="font-size:0.8rem;color:var(--text-secondary);">
                Discount: <strong style="color:var(--warning);">-<?= $currency ?> <?= number_format($discount_amount, 0) ?></strong>
            </span>
            <span style="font-size:0.8rem;font-weight:700;color:var(--text-primary);">
                Grand Total: <strong style="color:var(--success);"><?= $currency ?> <?= number_format($total_amount, 0) ?></strong>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENTS TABLE - REFERENCE COLUMN REMOVED -->
    <!-- ================================================================ -->
    <div class="table-wrapper animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-credit-card"></i>
                Payment History
                <span class="text-xs" style="color:var(--text-secondary);">(<?= $total_payments ?>)</span>
            </div>
            <div class="text-xs" style="color:var(--text-secondary);">
                Paid: <strong style="color:var(--success);"><?= $currency ?> <?= number_format($total_paid_amount, 0) ?></strong>
                | Balance: <strong style="color:<?= $balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= $currency ?> <?= number_format($balance, 0) ?></strong>
            </div>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="data-table payments-table">
                <thead>
                    <tr>
                        <th style="width:35px;text-align:center;">#</th>
                        <th>Receipt #</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Method</th>
                        <!-- REFERENCE COLUMN REMOVED -->
                        <th style="text-align:center;">Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payments) > 0): ?>
                        <?php $counter = 1; foreach ($payments as $payment): ?>
                            <tr>
                                <td style="text-align:center;"><?= $counter++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color:var(--primary);font-size:0.75rem;">
                                        <?= htmlspecialchars($payment['receipt_number'] ?? $payment['payment_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:var(--success);font-size:0.8rem;">
                                    <?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="payment-method-badge <?= $payment['payment_method'] ?? 'cash' ?>">
                                        <?= ucfirst($payment['payment_method'] ?? 'Cash') ?>
                                    </span>
                                </td>
                                <!-- REFERENCE COLUMN REMOVED -->
                                <td style="text-align:center;">
                                    <span class="status-badge <?= ($payment['status'] ?? 'completed') === 'completed' ? 'paid' : 'pending' ?>">
                                        <?= ucfirst($payment['status'] ?? 'Completed') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;color:var(--text-secondary);">
                                    <?php if (isset($payment['received_at']) && !empty($payment['received_at'])): ?>
                                        <?= date('M d, Y h:i A', strtotime($payment['received_at'])) ?>
                                    <?php elseif (isset($payment['created_at']) && !empty($payment['created_at'])): ?>
                                        <?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?>
                                    <?php else: ?>
                                        <?= date('M d, Y h:i A', strtotime($payment['payment_date'] ?? $payment['updated_at'] ?? 'now')) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--table-stripe);font-weight:700;">
                            <td colspan="2" style="text-align:right;font-size:0.85rem;">TOTAL PAID</td>
                            <td style="text-align:right;font-size:0.85rem;color:var(--success);">
                                <?= $currency ?> <?= number_format($total_paid_amount, 0) ?>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:25px 20px;color:var(--text-secondary);font-size:0.85rem;">
                                <i class="fas fa-info-circle mr-1"></i> No payments recorded
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS - PRINT BUTTON REMOVED -->
    <!-- ================================================================ -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
        <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
            <a href="process_payment.php?bill_id=<?= $bill_id ?>" class="btn btn-success">
                <i class="fas fa-money-bill-wave"></i> Process Payment
            </a>
        <?php endif; ?>
        <a href="pending_bills.php" class="btn btn-primary">
            <i class="fas fa-list"></i> Pending Bills
        </a>
        <!-- PRINT BUTTON REMOVED -->
        <?php if ($is_admin): ?>
            <button onclick="editBill(<?= $bill_id ?>)" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button onclick="voidBill(<?= $bill_id ?>)" class="btn btn-danger">
                <i class="fas fa-ban"></i> Void
            </button>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h3>Bill Not Found</h3>
            <p>The bill you are looking for does not exist or you don't have permission to view it.</p>
            <a href="dashboard.php" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bill Details
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
                <?php if ($is_reception): ?>
                    <span style="color:#FCD34D;font-weight:500;font-size:0.55rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 Reception</span>
                <?php endif; ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
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
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var clockDisplay = document.getElementById('clockDisplay');
        if (clockDisplay) {
            clockDisplay.textContent = dateStr + ' • ' + timeStr;
        }
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // ADMIN FUNCTIONS
    // ================================================================
    function editBill(billId) {
        showToast('✏️ Edit Bill', 'Redirecting to edit page...', 'info');
        setTimeout(function() {
            window.location.href = 'edit_bill.php?id=' + billId;
        }, 1000);
    }
    
    function voidBill(billId) {
        if (confirm('Are you sure you want to void this bill? This action cannot be undone.')) {
            showToast('⏳ Processing', 'Voiding bill...', 'warning');
            fetch('void_bill.php?id=' + billId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('✅ Bill Voided', 'Bill has been voided successfully', 'success');
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else {
                    showToast('❌ Error', data.message || 'Failed to void bill', 'error');
                }
            })
            .catch(function(error) {
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
            });
        }
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    console.log('%c🟢 Braick - View Bill Details (3 Items Per Row)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:12px; color:#059669;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:12px; color:#34D399;');
    console.log('%c📋 Bill #: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($total_amount, 0) ?> | Paid: <?= $currency ?> <?= number_format($paid_amount, 0) ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ Fixed: 3 items per row in bill summary', 'font-size:12px; color:#34D399;');
    console.log('%c❌ Print button removed', 'font-size:12px; color:#DC2626;');
    console.log('%c❌ Reference column removed from payments table', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>