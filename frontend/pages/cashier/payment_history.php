<?php
// ================================================================
// FILE: frontend/pages/cashier/payment_history.php
// CASHIER - PAYMENT HISTORY (GREEN THEME)
// VIEW ALL PAYMENTS WITH FILTERS - FIXED PATIENT NAME
// REMOVED: Total Amount Card
// USES SHARED HEADER WITH DARK MODE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Cashier
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';

// Initialize variables
$payments = [];
$total_payments = 0;
$total_amount = 0;
$currency = 'TSh';

try {
    $db = getDB();
    
    // ================================================================
    // BUILD DATE FILTER
    // ================================================================
    $date_condition = "";
    $params = [$selected_branch_id];
    
    switch ($filter) {
        case 'today':
            $date_condition = "AND DATE(p.received_at) = CURDATE()";
            break;
        case 'week':
            $date_condition = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $date_condition = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case '3months':
            $date_condition = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case '6months':
            $date_condition = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case 'year':
            $date_condition = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_condition = "AND DATE(p.received_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            } else {
                $date_condition = "";
            }
            break;
        case 'all':
        default:
            $date_condition = "";
            break;
    }
    
    // ================================================================
    // BUILD SEARCH CONDITION
    // ================================================================
    $search_condition = "";
    if (!empty($search)) {
        $search_condition = "AND (pat.full_name LIKE ? OR pat.patient_id LIKE ? OR pb.bill_number LIKE ? OR p.receipt_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // ================================================================
    // GET PAYMENTS - FIXED: Get patient name from patient_bills
    // ================================================================
    $sql = "
        SELECT 
            p.id,
            p.receipt_number,
            p.bill_id,
            p.patient_id as payment_patient_id,
            p.amount,
            p.payment_method,
            p.reference_number,
            p.received_by,
            p.received_at,
            p.branch_id as payment_branch_id,
            COALESCE(pat.full_name, 'Unknown Patient') as patient_name,
            COALESCE(pat.patient_id, 'N/A') as patient_id_number,
            pat.phone,
            pb.bill_number,
            pb.total_amount as bill_total,
            pb.patient_id as bill_patient_id,
            COALESCE(u.full_name, 'System') as received_by_name
        FROM payments p
        LEFT JOIN patient_bills pb ON p.bill_id = pb.id
        LEFT JOIN patients pat ON pb.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.branch_id = ?
        $date_condition
        $search_condition
        ORDER BY p.received_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    $total_payments = count($payments);
    
    // ================================================================
    // CALCULATE TOTAL AMOUNT
    // ================================================================
    $total_amount = 0;
    foreach ($payments as $payment) {
        $total_amount += $payment['amount'] ?? 0;
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
    $payments = [];
    $total_payments = 0;
    $total_amount = 0;
}

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
    <title>Payment History - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        /* DARK MODE - MATCH HEADER */
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
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        }

        /* Dark mode specific fixes */
        [data-theme="dark"] .bg-white {
            background-color: #1E293B !important;
        }

        [data-theme="dark"] .text-gray-700 {
            color: #CBD5E1 !important;
        }

        [data-theme="dark"] .text-gray-800 {
            color: #E2E8F0 !important;
        }

        [data-theme="dark"] .text-gray-900 {
            color: #F1F5F9 !important;
        }

        [data-theme="dark"] .border-gray-200 {
            border-color: #334155 !important;
        }

        [data-theme="dark"] .bg-gray-50 {
            background-color: #1E293B !important;
        }

        [data-theme="dark"] .bg-gray-100 {
            background-color: #2D3748 !important;
        }

        [data-theme="dark"] .shadow {
            box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important;
        }

        [data-theme="dark"] .shadow-md {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        }

        [data-theme="dark"] .shadow-lg {
            box-shadow: 0 10px 25px rgba(0,0,0,0.4) !important;
        }

        /* Dark mode for filter buttons */
        [data-theme="dark"] .filter-btn {
            border-color: #334155;
            color: #94A3B8;
        }

        [data-theme="dark"] .filter-btn:hover {
            border-color: #34D399;
            color: #34D399;
            background: rgba(5, 150, 105, 0.15);
        }

        [data-theme="dark"] .filter-btn.active {
            background: #059669;
            color: white;
            border-color: #059669;
        }

        [data-theme="dark"] .filter-btn.active:hover {
            background: #047857;
            border-color: #047857;
        }

        /* Dark mode for date picker */
        [data-theme="dark"] .date-picker-group .form-control {
            background: #1E293B;
            color: #F1F5F9;
            border-color: #334155;
        }

        [data-theme="dark"] .date-picker-group .form-control:focus {
            border-color: #34D399;
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1);
        }

        /* Dark mode for table */
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A2A;
        }

        [data-theme="dark"] .data-table td {
            border-bottom-color: #334155;
        }

        /* Dark mode for stat cards */
        [data-theme="dark"] .stat-card {
            background: #1E293B;
            border-color: #334155;
        }

        [data-theme="dark"] .stat-card:hover {
            border-color: #059669;
        }

        /* Dark mode for card */
        [data-theme="dark"] .card {
            background: #1E293B;
            border-color: #334155;
        }

        [data-theme="dark"] .card:hover {
            border-color: #059669;
        }

        /* Dark mode for page header */
        [data-theme="dark"] .page-header {
            background: linear-gradient(135deg, #059669, #047857) !important;
        }

        /* Dark mode for footer */
        [data-theme="dark"] .footer {
            border-top-color: #334155;
        }

        /* Dark mode for toast */
        [data-theme="dark"] .toast-custom.success {
            background: #059669;
        }

        [data-theme="dark"] .toast-custom.error {
            background: #DC2626;
        }

        /* Dark mode for header badges */
        [data-theme="dark"] .header-badge {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.1);
        }

        /* Dark mode for text colors */
        [data-theme="dark"] .text-gray-400 {
            color: #94A3B8 !important;
        }

        [data-theme="dark"] .text-gray-500 {
            color: #94A3B8 !important;
        }

        [data-theme="dark"] .text-gray-600 {
            color: #94A3B8 !important;
        }

        /* Dark mode for table header text */
        [data-theme="dark"] .data-table thead th {
            color: white;
        }

        /* Dark mode for bill number */
        [data-theme="dark"] .font-mono.text-gray-700 {
            color: #CBD5E1 !important;
        }

        /* Dark mode for table cell text */
        [data-theme="dark"] .font-semibold.text-gray-800 {
            color: #E2E8F0 !important;
        }

        [data-theme="dark"] .font-medium.text-sm {
            color: #F1F5F9 !important;
        }

        /* Dark mode for card title */
        [data-theme="dark"] .card-title {
            color: #F1F5F9 !important;
        }

        /* Dark mode for footer text */
        [data-theme="dark"] .footer .footer-brand {
            color: #34D399;
        }

        [data-theme="dark"] .footer .text-gray-300 {
            color: #475569 !important;
        }

        /* Dark mode for payment method badges */
        [data-theme="dark"] .method-badge.cash {
            background: #1A3A2A;
            color: #34D399;
        }

        [data-theme="dark"] .method-badge.m-pesa {
            background: #1E3A5F;
            color: #6EA8FE;
        }

        [data-theme="dark"] .method-badge.card {
            background: #3D2E0A;
            color: #FBBF24;
        }

        [data-theme="dark"] .method-badge.bank {
            background: #2D1B5F;
            color: #A78BFA;
        }

        /* Dark mode for role badge */
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }

        [data-theme="dark"] .role-badge-display[style*="background:rgba(255,255,255,0.2)"] {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        /* Dark mode for branch badge */
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }

        /* Dark mode for toast */
        [data-theme="dark"] .toast-custom {
            box-shadow: 0 20px 25px rgba(0,0,0,0.4);
        }

        /* Dark mode for dark toggle button */
        [data-theme="dark"] .dark-toggle-btn {
            background: #0F172A;
            border-color: #334155;
            color: #F1F5F9;
        }

        [data-theme="dark"] .dark-toggle-btn:hover {
            border-color: #34D399;
            background: #1E293B;
        }

        /* Dark mode for icon buttons */
        [data-theme="dark"] .icon-btn:hover {
            background: #0F172A;
            color: #34D399;
        }

        /* Dark mode for message alerts */
        [data-theme="dark"] .bg-green-100 {
            background-color: rgba(5, 150, 105, 0.15) !important;
        }

        [data-theme="dark"] .text-green-700 {
            color: #34D399 !important;
        }

        [data-theme="dark"] .border-green-200 {
            border-color: rgba(5, 150, 105, 0.3) !important;
        }

        [data-theme="dark"] .bg-red-100 {
            background-color: rgba(220, 38, 38, 0.15) !important;
        }

        [data-theme="dark"] .text-red-700 {
            color: #F87171 !important;
        }

        [data-theme="dark"] .border-red-200 {
            border-color: rgba(220, 38, 38, 0.3) !important;
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
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
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }
        
        .filter-btn.active {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .filter-btn.active:hover {
            background: var(--success-dark);
            border-color: var(--success-dark);
        }
        
        .filter-btn i {
            margin-right: 4px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        /* ================================================================
           DATE PICKER
           ================================================================ */
        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .date-picker-group .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            width: auto;
        }
        
        .date-picker-group .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .date-picker-group .btn-apply {
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--success);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-picker-group .btn-apply:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 700px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.72rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--success);
            color: white;
        }
        
        .btn-primary:hover {
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
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.65rem; 
            border-radius: 6px; 
        }
        
        /* ================================================================
           STATS CARD
           ================================================================ */
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--success);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-card .stat-number.green {
            color: var(--success);
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           PAYMENT METHOD BADGE
           ================================================================ */
        .method-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .method-badge.cash {
            background: #D1FAE5;
            color: #059669;
        }
        
        .method-badge.m-pesa {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .method-badge.card {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .method-badge.bank {
            background: #F3E8FF;
            color: #7C3AED;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
            box-shadow: var(--shadow-lg);
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
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card { padding: 14px 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .card { padding: 10px 12px; }
            .btn { padding: 4px 8px; font-size: 0.6rem; }
            .data-table { font-size: 0.65rem; min-width: 600px; }
        }
    </style>
    
    <!-- Preload dark mode from localStorage -->
    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<!-- TOP NAV is loaded from header -->

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
                <i class="fas fa-history"></i>
                Payment History
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-money-bill-wave"></i>
                View all payment transactions in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-receipt"></i>
                    <?= $total_payments ?> Payments
                </span>
                
                <?php if ($filter !== 'all' && $filter !== 'custom'): ?>
                <span class="header-badge">
                    <i class="fas fa-filter"></i>
                    <?= ucfirst(str_replace('months', ' Months', $filter)) ?>
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Filter:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1 Week
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Month
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3 Months
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6 Months
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Year
            </a>
        </div>
        
        <!-- ============================================================ -->
        <!-- DATE PICKER (Custom Range) -->
        <!-- ============================================================ -->
        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $start_date ?>" placeholder="Start Date">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= $end_date ?>" placeholder="End Date">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS - 2 CARDS ONLY (REMOVED Total Amount) -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5" style="max-width:1200px;margin:0 auto;">
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <p class="stat-number green"><?= $total_payments ?></p>
            <p class="stat-label">Total Payments</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <p class="stat-number green">
                <?php 
                    if ($filter === 'today') echo 'Today';
                    elseif ($filter === 'week') echo '7 Days';
                    elseif ($filter === 'month') echo '30 Days';
                    elseif ($filter === '3months') echo '90 Days';
                    elseif ($filter === '6months') echo '180 Days';
                    elseif ($filter === 'year') echo '365 Days';
                    elseif ($filter === 'custom') echo 'Custom';
                    else echo 'All Time';
                ?>
            </p>
            <p class="stat-label">Date Range</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card" style="max-width:1200px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Payment History
                <span class="text-sm font-normal text-gray-400">(<?= $total_payments ?> payments)</span>
            </h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <span class="text-xs text-gray-400">
                    <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
                </span>
            </div>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt #</th>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference #</th>
                        <th>Received By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($payments) && count($payments) > 0): ?>
                        <?php $i = 1; foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold" style="color:#0B5ED7;">
                                        <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-mono text-xs" style="color:var(--text-secondary);">
                                        <?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-sm" style="color:var(--text-primary);">
                                        <?= htmlspecialchars($payment['patient_name'] ?? 'Unknown Patient') ?>
                                    </div>
                                    <div class="text-xs" style="color:var(--text-secondary);">
                                        <?= htmlspecialchars($payment['patient_id_number'] ?? $payment['bill_patient_id'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-semibold" style="color:var(--success);">
                                        <?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $method = $payment['payment_method'] ?? 'cash';
                                        $methodClass = $method === 'cash' ? 'cash' : ($method === 'm-pesa' ? 'm-pesa' : ($method === 'card' ? 'card' : 'bank'));
                                    ?>
                                    <span class="method-badge <?= $methodClass ?>">
                                        <?= strtoupper(str_replace('_', ' ', $method)) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs font-mono" style="color:var(--text-secondary);"><?= htmlspecialchars($payment['reference_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="text-sm" style="color:var(--text-secondary);"><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></span>
                                </td>
                                <td class="text-xs" style="color:var(--text-secondary);">
                                    <?= isset($payment['received_at']) ? date('d/m/Y', strtotime($payment['received_at'])) : 'N/A' ?>
                                    <br>
                                    <span class="text-[0.6rem]" style="color:var(--text-secondary);opacity:0.6;">
                                        <?= isset($payment['received_at']) ? date('h:i A', strtotime($payment['received_at'])) : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <!-- View Bill -->
                                        <a href="view_bill.php?id=<?= $payment['bill_id'] ?>" class="btn btn-primary btn-sm" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Print Receipt -->
                                        <a href="print_receipt.php?bill_id=<?= $payment['bill_id'] ?>&payment_id=<?= $payment['id'] ?>&print=1" class="btn btn-outline btn-sm" title="Print Receipt" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-400">
                                <i class="fas fa-money-bill-wave text-3xl block mb-2 text-gray-300"></i>
                                <p class="text-lg">No payments found</p>
                                <p class="text-sm">
                                    <?php if ($filter !== 'all'): ?>
                                        No payments found for the selected date range
                                    <?php else: ?>
                                        No payments have been recorded yet. Process a payment to see it here.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Payment History
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
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    // Note: Dark mode is controlled by header.

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    if (!searchBtn && !searchInput) {
        searchBtn = document.querySelector('.top-nav .search-btn');
        searchInput = document.querySelector('.top-nav #searchInput');
    }
    
    function performSearch() {
        var query = searchInput?.value?.trim() || '';
        var filter = '<?= $filter ?>';
        var start_date = '<?= $start_date ?>';
        var end_date = '<?= $end_date ?>';
        if (query.length > 0) {
            window.location.href = 'payment_history.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&start_date=' + start_date + '&end_date=' + end_date;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
        searchInput.value = '<?= htmlspecialchars($search) ?>';
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
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<span class="spinner"></span> Loading...';
            btn.disabled = true;
        }
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
            showToast('✅ Refreshed', 'Page data updated', 'success');
        }, 2000);
    }

    // ================================================================
    // ADD CSS ANIMATIONS
    // ================================================================
    var style = document.createElement('style');
    style.textContent = `
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.08) !important; }
        .method-badge { transition: all 0.3s; }
        .method-badge:hover { transform: scale(1.05); }
        .btn-outline-light:hover { background: rgba(255,255,255,0.25) !important; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
    `;
    document.head.appendChild(style);

    console.log('%c💳 Braick - Payment History (FIXED - Patient Name from patient_bills)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Payments: <?= $total_payments ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Removed: Total Amount Card', 'font-size:13px; color:#DC2626;');
    console.log('%c👤 Patient: <?= htmlspecialchars($payments[0]['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#1E293B;');
    console.log('%c✅ Fixed: Using patient_bills.patient_id to get patient name', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode controlled by header', 'font-size:13px; color:#8B5CF6;');
</script>

</body>
</html>