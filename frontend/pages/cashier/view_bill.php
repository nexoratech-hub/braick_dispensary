<?php
// ================================================================
// FILE: frontend/pages/cashier/view_bill.php
// CASHIER - VIEW BILL DETAILS WITH PDF
// FIXED: Uses bills table (not patient_bills)
// WITH PDF GENERATION - Official Stamp & Admin Numbers
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
$user_phone = $_SESSION['phone'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

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
    
    // ================================================================
    // GET BRANCH PHONE
    // ================================================================
    $branch_phone = '';
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$user_branch_id]);
        $branch_phone = $stmt->fetchColumn();
    } catch (Exception $e) {
        $branch_phone = '';
    }
    
    // ================================================================
    // GET BILL DETAILS - USING bills TABLE (NOT patient_bills)
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            b.*,
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
            br.name as branch_name
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users u2 ON b.created_by = u2.id
        LEFT JOIN branches br ON b.branch_id = br.id
        WHERE b.id = ? AND b.branch_id = ?
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
    $admin_phones = [];
    $branch_phone = '';
    error_log("View bill error: " . $e->getMessage());
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
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
            --radius: 10px;
            --radius-lg: 14px;
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
            border-radius: var(--radius-lg);
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
            border-radius: var(--radius);
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
           BILL SUMMARY CARD
           ================================================================ */
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
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
            border-radius: var(--radius-lg);
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
            border-radius: var(--radius);
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
            border-radius: var(--radius);
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
            padding: 10px 16px;
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
            padding: 8px 14px;
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
            padding: 8px 14px;
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
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 16px;
            border-radius: var(--radius);
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
        
        /* ================================================================
           PDF MODAL
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
            margin-top: 0;
            padding-top: 28px;
        }
        
        /* PDF Styles */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 3px solid #059669;
            margin-bottom: 16px;
            page-break-after: avoid;
            break-after: avoid;
            margin-top: 0;
            padding-top: 0;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #059669;
            letter-spacing: -0.5px;
            margin-top: 4px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .doc-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #059669;
            margin-top: 4px;
            background: #D1FAE5;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .pdf-section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #059669;
            border-bottom: 2px solid #34D399;
            padding-bottom: 4px;
            margin: 6px 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 2px 0;
            border-bottom: 1px solid #E2E8F0;
            font-size: 14px;
        }
        
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 130px;
            flex-shrink: 0;
            font-size: 14px;
        }
        
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 14px;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 14px;
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin: 4px 0;
        }
        
        .pdf-content .pdf-table th {
            background: #059669;
            color: white;
            padding: 4px 10px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid #047857;
        }
        
        .pdf-content .pdf-table td {
            padding: 4px 10px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 13px;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .pdf-content .pdf-empty {
            padding: 6px 0;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
            text-align: center;
            background: #F8FAFC;
            border-radius: 4px;
            margin: 2px 0;
        }
        
        .pdf-content .pdf-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid var(--text-secondary);
            margin-left: 4px;
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 14px;
            border: 3px solid #059669;
            border-radius: 10px;
            background: #D1FAE5;
            min-width: 150px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #059669;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 10px;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 6px;
            font-size: 12px;
            color: #94A3B8;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: var(--radius);
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
            border-radius: var(--radius-lg);
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

<main class="main-content">

    <!-- PAGE HEADER -->
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
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
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
        
    <!-- BILL SUMMARY -->
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
        
        <div class="bill-summary-grid">
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

    <!-- PATIENT INFO -->
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

    <!-- SUMMARY STATS -->
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

    <!-- BILL ITEMS TABLE -->
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
                                    <span class="status-badge <?= ($item['status'] ?? 'pending') ?>">
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
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

    <!-- PAYMENTS TABLE -->
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

    <!-- ACTION BUTTONS -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
        <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
            <a href="process_payment.php?bill_id=<?= $bill_id ?>" class="btn btn-success">
                <i class="fas fa-money-bill-wave"></i> Process Payment
            </a>
        <?php endif; ?>
        <a href="pending_bills.php" class="btn btn-primary">
            <i class="fas fa-list"></i> Pending Bills
        </a>
        <button onclick="generatePDF()" class="btn btn-outline">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
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

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bill Details
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                Bill PDF - <?= htmlspecialchars($bill['bill_number'] ?? 'Bill') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

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
    // PDF GENERATION - WITH OFFICIAL STAMP AND ADMIN NUMBERS
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var adminPhones = '<?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>';
        var currency = '<?= $currency ?>';
        var billNumber = '<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>';
        var patientName = '<?= htmlspecialchars($bill['patient_name'] ?? 'Unknown Patient') ?>';
        var patientId = '<?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?>';
        var patientPhone = '<?= htmlspecialchars($bill['phone'] ?? 'N/A') ?>';
        var patientEmail = '<?= htmlspecialchars($bill['email'] ?? 'N/A') ?>';
        var patientGender = '<?= htmlspecialchars($bill['gender'] ?? 'N/A') ?>';
        var patientDob = '<?= $bill['date_of_birth'] ? date('F d, Y', strtotime($bill['date_of_birth'])) : 'N/A' ?>';
        var visitNumber = '<?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>';
        var doctorName = '<?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?>';
        var createdBy = '<?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?>';
        var billStatus = '<?= ucfirst($bill['status'] ?? 'Pending') ?>';
        var branchName = '<?= htmlspecialchars($bill['branch_name'] ?? $user_branch_name) ?>';
        var createdAt = '<?= date('F d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?>';
        var subtotal = <?= $subtotal ?>;
        var discount = <?= $discount_amount ?>;
        var total = <?= $total_amount ?>;
        var paid = <?= $paid_amount ?>;
        var balance = <?= $balance ?>;
        var totalItems = <?= $total_items ?>;
        
        var itemsHtml = '';
        var counter = 1;
        <?php foreach ($items as $item): ?>
            itemsHtml += `
                <tr>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;">${counter}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;text-transform:capitalize;"><?= htmlspecialchars($item['item_type'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;"><?= $item['quantity'] ?? 1 ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600;font-size:13px;"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;"><span style="background:<?= ($item['status'] ?? 'pending') === 'paid' ? '#D1FAE5' : '#FEF3C7' ?>;color:<?= ($item['status'] ?? 'pending') === 'paid' ? '#059669' : '#D97706' ?>;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600;text-transform:capitalize;"><?= ucfirst($item['status'] ?? 'Pending') ?></span></td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        var paymentsHtml = '';
        var payCounter = 1;
        <?php foreach ($payments as $payment): ?>
            paymentsHtml += `
                <tr>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;">${payCounter}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#059669;"><?= htmlspecialchars($payment['receipt_number'] ?? $payment['payment_number'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600;font-size:13px;color:#059669;"><?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;text-transform:capitalize;"><?= ucfirst($payment['payment_method'] ?? 'Cash') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;"><span style="background:#D1FAE5;color:#059669;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600;"><?= ucfirst($payment['status'] ?? 'Completed') ?></span></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;color:#64748B;"><?= isset($payment['received_at']) ? date('M d, Y h:i A', strtotime($payment['received_at'])) : date('M d, Y h:i A', strtotime($payment['created_at'] ?? 'now')) ?></td>
                </tr>
            `;
            payCounter++;
        <?php endforeach; ?>
        
        var html = `
            <!-- PDF HEADER -->
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;width:auto;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                    <div class="clinic-name">BRAICK DISPENSARY</div>
                    <div class="clinic-sub">Tunajali Afya Yako</div>
                </div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:4px;padding-top:4px;border-top:1px solid #E2E8F0;font-size:0.6rem;color:#64748B;">
                    <span>📞 Admin Contacts: ${adminPhones}</span>
                    <span>🏢 Branch: ${branchName}</span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div class="doc-title">
                    💰 Bill Details Report - #${billNumber}
                </div>
            </div>
            
            <!-- BILL SUMMARY -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-file-invoice"></i> Bill Summary</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div class="pdf-row"><span class="pdf-label">Bill Number</span><span class="pdf-value"><strong>#${billNumber}</strong></span></div>
                    <div class="pdf-row"><span class="pdf-label">Status</span><span class="pdf-value"><span style="background:${billStatus === 'Paid' ? '#D1FAE5' : (billStatus === 'Pending' ? '#FEF3C7' : '#FEE2E2')};color:${billStatus === 'Paid' ? '#059669' : (billStatus === 'Pending' ? '#D97706' : '#DC2626')};padding:2px 12px;border-radius:12px;font-size:13px;font-weight:600;">${billStatus}</span></span></div>
                    <div class="pdf-row"><span class="pdf-label">Created</span><span class="pdf-value">${createdAt}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Branch</span><span class="pdf-value">${branchName}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Visit</span><span class="pdf-value">${visitNumber}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Doctor</span><span class="pdf-value">${doctorName}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Created By</span><span class="pdf-value">${createdBy}</span></div>
                </div>
            </div>
            
            <!-- PATIENT INFORMATION -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-user"></i> Patient Information</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div class="pdf-row" style="grid-column:span 3;"><span class="pdf-label">Full Name</span><span class="pdf-value"><strong>${patientName}</strong></span></div>
                    <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value">${patientId}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value">${patientPhone}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Email</span><span class="pdf-value">${patientEmail}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value">${patientGender}</span></div>
                    <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value">${patientDob}</span></div>
                </div>
            </div>
            
            <!-- FINANCIAL SUMMARY -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-money-bill-wave"></i> Financial Summary</div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:4px;margin:4px 0;">
                    <div style="background:#E8F0FE;padding:4px 6px;border-radius:6px;text-align:center;border:1px solid #6EA8FE;"><div style="font-size:16px;font-weight:700;color:#0B5ED7;">${currency} ${subtotal.toLocaleString()}</div><div style="font-size:9px;color:#64748B;text-transform:uppercase;">💰 Subtotal</div></div>
                    <div style="background:#FEF3C7;padding:4px 6px;border-radius:6px;text-align:center;border:1px solid #D97706;"><div style="font-size:16px;font-weight:700;color:#D97706;">-${currency} ${discount.toLocaleString()}</div><div style="font-size:9px;color:#64748B;text-transform:uppercase;">🏷️ Discount</div></div>
                    <div style="background:#D1FAE5;padding:4px 6px;border-radius:6px;text-align:center;border:1px solid #059669;"><div style="font-size:16px;font-weight:700;color:#059669;">${currency} ${total.toLocaleString()}</div><div style="font-size:9px;color:#64748B;text-transform:uppercase;">📋 Total</div></div>
                    <div style="background:#D1FAE5;padding:4px 6px;border-radius:6px;text-align:center;border:1px solid #059669;"><div style="font-size:16px;font-weight:700;color:#059669;">${currency} ${paid.toLocaleString()}</div><div style="font-size:9px;color:#64748B;text-transform:uppercase;">✅ Paid</div></div>
                    <div style="background:${balance > 0 ? '#FEE2E2' : '#D1FAE5'};padding:4px 6px;border-radius:6px;text-align:center;border:1px solid ${balance > 0 ? '#DC2626' : '#059669'};"><div style="font-size:16px;font-weight:700;color:${balance > 0 ? '#DC2626' : '#059669'};">${currency} ${balance.toLocaleString()}</div><div style="font-size:9px;color:#64748B;text-transform:uppercase;">⚖️ Balance</div></div>
                </div>
            </div>
            
            <!-- BILL ITEMS -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-list-ul"></i> Bill Items (${totalItems})</div>
                <div class="pdf-table-wrap">
                    <table class="pdf-table">
                        <thead>
                            <tr><th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:12px;">#</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:12px;">Item Name</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:12px;">Type</th><th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:12px;">Qty</th><th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:12px;">Unit Price</th><th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:12px;">Total</th><th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:12px;">Status</th></tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                            <tr style="background:#D1FAE5;font-weight:700;">
                                <td colspan="5" style="text-align:right;font-size:14px;padding:5px 10px;">TOTAL</td>
                                <td style="text-align:right;font-size:14px;color:#059669;padding:5px 10px;">${currency} ${subtotal.toLocaleString()}</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                ${discount > 0 ? `
                <div style="display:flex;justify-content:flex-end;gap:18px;flex-wrap:wrap;padding:4px 10px;background:#F8FAFC;border-radius:0 0 4px 4px;border:1px solid #E2E8F0;border-top:none;">
                    <span style="font-size:14px;color:#64748B;">Discount: <strong style="color:#D97706;">-${currency} ${discount.toLocaleString()}</strong></span>
                    <span style="font-size:14px;font-weight:700;">Grand Total: <strong style="color:#059669;">${currency} ${total.toLocaleString()}</strong></span>
                </div>
                ` : ''}
            </div>
            
            <!-- PAYMENTS -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-credit-card"></i> Payment History (${<?= $total_payments ?>})</div>
                <?php if (count($payments) > 0): ?>
                <div class="pdf-table-wrap">
                    <table class="pdf-table">
                        <thead>
                            <tr><th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:12px;">#</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:12px;">Receipt #</th><th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:12px;">Amount</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:12px;">Method</th><th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:12px;">Status</th><th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:12px;">Date</th></tr>
                        </thead>
                        <tbody>
                            ${paymentsHtml}
                            <tr style="background:#D1FAE5;font-weight:700;">
                                <td colspan="2" style="text-align:right;font-size:14px;padding:5px 10px;">TOTAL PAID</td>
                                <td style="text-align:right;font-size:14px;color:#059669;padding:5px 10px;">${currency} ${paid.toLocaleString()}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="pdf-empty">No payments recorded for this bill</div>
                <?php endif; ?>
            </div>
            
            <!-- PDF FOOTER WITH OFFICIAL STAMP -->
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Generated by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Bill_<?= htmlspecialchars($bill['bill_number'] ?? 'bill') ?>_<?= $bill_id ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { 
                mode: ['css', 'legacy']
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c🟢 Braick - View Bill (Fixed - bills table)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:12px; color:#059669;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:12px; color:#34D399;');
    console.log('%c📋 Bill #: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($total_amount, 0) ?> | Paid: <?= $currency ?> <?= number_format($paid_amount, 0) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📞 Admin Contacts: <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ PDF with Official Stamp & Admin Numbers', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>