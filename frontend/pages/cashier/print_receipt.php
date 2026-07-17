<?php
// ================================================================
// FILE: frontend/pages/cashier/print_receipt.php
// CASHIER - PRINT RECEIPT
// DISPLAYS PAID BILL RECEIPT FOR PRINTING
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Cashier Dodoma
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$receipt_id = isset($_GET['receipt_id']) ? (int)$_GET['receipt_id'] : 0;
$unread_notifications = 0;
$message = '';
$message_type = '';

try {
    $db = getDB();
    $today = date('Y-m-d');
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS
    // ================================================================
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
    
    // ================================================================
    // IF BILL_ID IS PROVIDED, GET THE LATEST RECEIPT FOR THIS BILL
    // ================================================================
    if ($bill_id > 0) {
        $stmt = $db->prepare("
            SELECT id FROM receipts 
            WHERE bill_id = ? 
            ORDER BY printed_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$bill_id]);
        $receipt = $stmt->fetch();
        if ($receipt) {
            $receipt_id = $receipt['id'];
        }
    }
    
    // ================================================================
    // GET RECEIPT DETAILS
    // ================================================================
    if ($receipt_id > 0) {
        $stmt = $db->prepare("
            SELECT r.*, 
                   pb.bill_number, pb.total_amount, pb.paid_amount, pb.balance, pb.status,
                   p.full_name as patient_name, p.patient_id, p.phone, p.email, p.address,
                   v.visit_number, v.visit_type, v.created_at as visit_date,
                   u.full_name as doctor_name,
                   c.full_name as cashier_name
            FROM receipts r
            JOIN patient_bills pb ON r.bill_id = pb.id
            JOIN patients p ON pb.patient_id = p.id
            LEFT JOIN visits v ON pb.visit_id = v.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN users c ON r.printed_by = c.id
            WHERE r.id = ? AND pb.branch_id = ?
        ");
        $stmt->execute([$receipt_id, $user_branch_id]);
        $receipt_data = $stmt->fetch();
    }
    
    // ================================================================
    // IF NO RECEIPT FOUND, TRY TO GET THE LATEST PAID BILL
    // ================================================================
    if (empty($receipt_data) && $bill_id > 0) {
        $stmt = $db->prepare("
            SELECT pb.*, 
                   p.full_name as patient_name, p.patient_id, p.phone, p.email, p.address,
                   v.visit_number, v.visit_type, v.created_at as visit_date,
                   u.full_name as doctor_name
            FROM patient_bills pb
            JOIN patients p ON pb.patient_id = p.id
            LEFT JOIN visits v ON pb.visit_id = v.id
            LEFT JOIN users u ON v.doctor_id = u.id
            WHERE pb.id = ? AND pb.branch_id = ? AND pb.status = 'paid'
        ");
        $stmt->execute([$bill_id, $user_branch_id]);
        $bill_data = $stmt->fetch();
        
        if ($bill_data) {
            // Create a virtual receipt from bill data
            $receipt_data = [
                'receipt_number' => 'RCP-' . date('Ymd') . '-' . str_pad($bill_data['id'], 6, '0', STR_PAD_LEFT),
                'bill_number' => $bill_data['bill_number'],
                'total_amount' => $bill_data['total_amount'],
                'paid_amount' => $bill_data['paid_amount'] ?? $bill_data['total_amount'],
                'balance' => 0,
                'status' => 'paid',
                'patient_name' => $bill_data['patient_name'],
                'patient_id' => $bill_data['patient_id'],
                'phone' => $bill_data['phone'],
                'email' => $bill_data['email'],
                'address' => $bill_data['address'],
                'visit_number' => $bill_data['visit_number'],
                'visit_type' => $bill_data['visit_type'],
                'visit_date' => $bill_data['visit_date'],
                'doctor_name' => $bill_data['doctor_name'],
                'cashier_name' => $user_full_name,
                'printed_at' => date('Y-m-d H:i:s'),
                'payment_method' => 'cash',
                'created_at' => $bill_data['created_at']
            ];
        }
    }
    
    // ================================================================
    // GET BILL ITEMS
    // ================================================================
    $bill_items = [];
    if (!empty($receipt_data)) {
        $bill_id_to_use = $bill_id > 0 ? $bill_id : 0;
        if ($bill_id_to_use > 0) {
            $stmt = $db->prepare("
                SELECT * FROM bill_items 
                WHERE bill_id = ?
                ORDER BY created_at
            ");
            $stmt->execute([$bill_id_to_use]);
            $bill_items = $stmt->fetchAll();
        }
    }
    
    // ================================================================
    // GET PAYMENT DETAILS
    // ================================================================
    $payment_details = [];
    if (!empty($receipt_data) && $bill_id > 0) {
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE bill_id = ?
            ORDER BY received_at DESC
            LIMIT 1
        ");
        $stmt->execute([$bill_id]);
        $payment_details = $stmt->fetch();
    }
    
    // ================================================================
    // GET RECENT RECEIPTS FOR DROPDOWN
    // ================================================================
    $stmt = $db->prepare("
        SELECT r.id, r.receipt_number, pb.bill_number, p.full_name as patient_name,
               r.printed_at
        FROM receipts r
        JOIN patient_bills pb ON r.bill_id = pb.id
        JOIN patients p ON pb.patient_id = p.id
        WHERE pb.branch_id = ?
        ORDER BY r.printed_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_branch_id]);
    $recent_receipts = $stmt->fetchAll();
    
} catch (Exception $e) {
    $receipt_data = null;
    $bill_items = [];
    $payment_details = [];
    $recent_receipts = [];
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// INCLUDE CASHIER HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
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
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           RECEIPT STYLES
           ================================================================ */
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .receipt-container {
            background: #1E293B;
            border-color: #334155;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 16px;
        }
        
        .receipt-header .clinic-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .receipt-header .clinic-address {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .receipt-header .receipt-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 8px;
        }
        
        .receipt-header .receipt-number {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .receipt-divider {
            border-top: 1px dashed var(--border-color);
            margin: 12px 0;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.8rem;
        }
        
        .receipt-row .label {
            color: var(--text-secondary);
        }
        
        .receipt-row .value {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .receipt-items {
            margin: 12px 0;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .receipt-item:last-child {
            border-bottom: none;
        }
        
        .receipt-total {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 1rem;
            font-weight: 700;
            border-top: 2px solid var(--border-color);
            margin-top: 8px;
        }
        
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed var(--border-color);
            padding-top: 16px;
            margin-top: 16px;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
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
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
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
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
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
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
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
            color: var(--primary); 
            font-weight: 600; 
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .receipt-container { padding: 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .receipt-container { padding: 12px; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
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
           PRINT STYLES
           ================================================================ */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .receipt-container {
                box-shadow: none !important;
                border: none !important;
                padding: 20px !important;
            }
            
            .page-header {
                display: none !important;
            }
            
            .footer {
                display: none !important;
            }
            
            .top-nav {
                display: none !important;
            }
            
            .sidebar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
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
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">
                <i class="fas fa-print"></i>
                Print Receipt
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-receipt"></i>
                Print receipt for paid bill
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($receipt_data['patient_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;" class="no-print">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light" style="background:rgba(255,255,255,0.25);">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SELECT RECEIPT - Dropdown -->
    <!-- ================================================================ -->
    <div class="card no-print mb-5" style="max-width:600px;margin:0 auto 20px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Select Receipt
            </h3>
        </div>
        <form method="GET" action="">
            <div class="flex gap-3">
                <select name="receipt_id" class="form-control" style="flex:1;padding:8px 12px;border:2px solid var(--border-color);border-radius:8px;background:var(--bg-card);color:var(--text-primary);">
                    <option value="">-- Select Receipt --</option>
                    <?php foreach ($recent_receipts as $receipt): ?>
                        <option value="<?= $receipt['id'] ?>" <?= ($receipt_id == $receipt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($receipt['receipt_number']) ?> - <?= htmlspecialchars($receipt['patient_name']) ?> (<?= date('M d, Y', strtotime($receipt['printed_at'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">View</button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPT -->
    <!-- ================================================================ -->
    <?php if (!empty($receipt_data)): ?>
    <div class="receipt-container animate-fade-in-up" id="receiptContainer">
        
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="clinic-name">🏥 Braick Dispensary</div>
            <div class="clinic-address"><?= htmlspecialchars($branch_name) ?>, Tanzania</div>
            <div class="clinic-address"><?= date('Y') ?></div>
            <div class="receipt-title">PATIENT RECEIPT</div>
            <div class="receipt-number">#<?= htmlspecialchars($receipt_data['receipt_number'] ?? 'N/A') ?></div>
        </div>
        
        <!-- Patient & Bill Info -->
        <div class="receipt-row">
            <span class="label">Bill Number</span>
            <span class="value"><?= htmlspecialchars($receipt_data['bill_number'] ?? 'N/A') ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Patient</span>
            <span class="value"><?= htmlspecialchars($receipt_data['patient_name'] ?? 'N/A') ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Patient ID</span>
            <span class="value"><?= htmlspecialchars($receipt_data['patient_id'] ?? 'N/A') ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Phone</span>
            <span class="value"><?= htmlspecialchars($receipt_data['phone'] ?? 'N/A') ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Visit Type</span>
            <span class="value"><?= ucfirst(htmlspecialchars($receipt_data['visit_type'] ?? 'N/A')) ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Doctor</span>
            <span class="value">Dr. <?= htmlspecialchars($receipt_data['doctor_name'] ?? 'Not Assigned') ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Date</span>
            <span class="value"><?= isset($receipt_data['created_at']) ? date('F d, Y h:i A', strtotime($receipt_data['created_at'])) : 'N/A' ?></span>
        </div>
        
        <div class="receipt-divider"></div>
        
        <!-- Items -->
        <div class="receipt-items">
            <div class="receipt-row" style="font-weight:600;border-bottom:1px solid var(--border-color);padding-bottom:4px;margin-bottom:4px;">
                <span>Item</span>
                <span>Amount</span>
            </div>
            <?php if (count($bill_items) > 0): ?>
                <?php foreach ($bill_items as $item): ?>
                    <div class="receipt-item">
                        <span><?= htmlspecialchars($item['item_name']) ?></span>
                        <span>TSh <?= number_format($item['total_price'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="receipt-item">
                    <span>Consultation Fee</span>
                    <span>TSh <?= number_format($receipt_data['total_amount'] ?? 0) ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-divider"></div>
        
        <!-- Totals -->
        <div class="receipt-row">
            <span class="label">Subtotal</span>
            <span class="value">TSh <?= number_format($receipt_data['total_amount'] ?? 0) ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Discount</span>
            <span class="value">TSh 0</span>
        </div>
        <div class="receipt-total">
            <span>TOTAL</span>
            <span>TSh <?= number_format($receipt_data['total_amount'] ?? 0) ?></span>
        </div>
        
        <!-- Payment Details -->
        <div class="receipt-divider"></div>
        <div class="receipt-row">
            <span class="label">Payment Method</span>
            <span class="value"><?= strtoupper($payment_details['payment_method'] ?? $receipt_data['payment_method'] ?? 'CASH') ?></span>
        </div>
        <?php if (!empty($payment_details['reference_number'])): ?>
        <div class="receipt-row">
            <span class="label">Reference</span>
            <span class="value"><?= htmlspecialchars($payment_details['reference_number']) ?></span>
        </div>
        <?php endif; ?>
        <div class="receipt-row">
            <span class="label">Paid By</span>
            <span class="value"><?= htmlspecialchars($receipt_data['cashier_name'] ?? $user_full_name) ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">Payment Date</span>
            <span class="value"><?= isset($receipt_data['printed_at']) ? date('F d, Y h:i A', strtotime($receipt_data['printed_at'])) : date('F d, Y h:i A') ?></span>
        </div>
        
        <!-- Footer -->
        <div class="receipt-footer">
            <p>Thank you for choosing Braick Dispensary</p>
            <p style="margin-top:4px;">This is a computer-generated receipt</p>
            <p style="margin-top:4px;font-size:0.6rem;">Receipt #<?= htmlspecialchars($receipt_data['receipt_number'] ?? 'N/A') ?></p>
        </div>
        
    </div>
    
    <!-- Print Actions -->
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-success" style="padding:12px 32px;font-size:1rem;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="pending_bills.php" class="btn btn-outline" style="padding:12px 32px;font-size:1rem;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    
    <?php else: ?>
    
    <!-- No Receipt Found -->
    <div class="text-center py-8 text-gray-400" style="max-width:600px;margin:0 auto;">
        <i class="fas fa-receipt text-4xl block mb-3"></i>
        <p class="text-lg">No receipt found</p>
        <p class="text-sm mt-1">Select a receipt from the dropdown above or process a payment first</p>
        <a href="pending_bills.php" class="text-primary hover:underline mt-2 block">Go to Pending Bills</a>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Print Receipt
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">● Live</span>
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
<!-- CASHIER GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/cashier_global_stats.js"></script>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
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
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // MONITOR CASHIER STATS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var checkCashierStats = setInterval(function() {
            if (window.CashierStats) {
                console.log('%c💰 Cashier Stats System Connected', 'font-size:14px; font-weight:bold; color:#34D399;');
                console.log('%c🔄 Auto-update every ' + window.CashierStats.config.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#64748B;');
                clearInterval(checkCashierStats);
            }
        }, 500);
    });

    console.log('%c🧾 Braick - Print Receipt', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🧾 Receipt: <?= htmlspecialchars($receipt_data['receipt_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($receipt_data['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Amount: TSh <?= number_format($receipt_data['total_amount'] ?? 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update every 3 seconds via cashier_global_stats.js', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Click Print button to print receipt', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>