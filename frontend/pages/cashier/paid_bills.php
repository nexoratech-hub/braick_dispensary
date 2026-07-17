<?php
// ================================================================
// FILE: frontend/pages/cashier/paid_bills.php
// CASHIER - PAID BILLS LIST
// WITH AUTO-UPDATE (3 SECONDS) - NO REFRESH NEEDED
// WITH 2 CLICKABLE STAT CARDS
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
$unread_notifications = 0;
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

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
    // GET STATS FOR CARDS
    // ================================================================
    
    // 1. Paid Bills Count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $paid_bills = $stmt->fetch()['count'] ?? 0;
    
    // 2. Total Patients with Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT patient_id) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $patients_paid = $stmt->fetch()['count'] ?? 0;
    
    // ================================================================
    // GET PAID BILLS LIST
    // ================================================================
    $query = "
        SELECT pb.*, p.full_name as patient_name, p.patient_id, p.phone,
               v.visit_number, v.visit_type, v.created_at as visit_date,
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as payment_count,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = pb.id) as total_paid,
               (SELECT payment_method FROM payments WHERE bill_id = pb.id ORDER BY received_at DESC LIMIT 1) as payment_method,
               (SELECT received_at FROM payments WHERE bill_id = pb.id ORDER BY received_at DESC LIMIT 1) as paid_at
        FROM patient_bills pb
        JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE pb.branch_id = ? AND pb.status = 'paid'
    ";
    $params = [$user_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(pb.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(pb.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY pb.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $paid_bills_list = $stmt->fetchAll();
    
    // ================================================================
    // GET RECENT PAYMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, pb.bill_number, pb.total_amount,
               pat.full_name as patient_name, pat.patient_id,
               u.full_name as cashier_name
        FROM payments p
        JOIN patient_bills pb ON p.bill_id = pb.id
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.received_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $recent_payments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $paid_bills = 0;
    $patients_paid = 0;
    $paid_bills_list = [];
    $recent_payments = [];
    $message = "Database error: " . $e->getMessage();
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
    <title>Paid Bills - Braick Dispensary</title>
    
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
        
        .page-header .header-badge .count {
            color: #34D399;
            font-weight: 700;
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
           STAT CARDS - CLICKABLE (2 CARDS ONLY)
           ================================================================ */
        .stat-card {
            border-radius: 14px;
            padding: 18px 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card:active {
            transform: scale(0.97);
        }
        
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        
        .stat-card .stat-icon {
            font-size: 1.6rem;
            opacity: 0.9;
            margin-bottom: 4px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-update {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.5);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-card .stat-update .live-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
        }
        
        .stat-card .stat-arrow {
            position: absolute;
            bottom: 12px;
            right: 16px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-arrow {
            transform: translateX(4px);
            color: rgba(255,255,255,0.8);
        }
        
        /* ================================================================
           TABLE STYLES
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 1000px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--bg-body);
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
            font-size: 0.75rem;
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
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        
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
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 12px;
        }
        
        .status-badge.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        [data-theme="dark"] .status-badge.paid {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           SCROLL CONTAINER
           ================================================================ */
        .scroll-container {
            max-height: 250px;
            overflow-y: auto;
        }
        
        .scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* ================================================================
           FORM
           ================================================================ */
        .form-control {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.08);
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
            .stat-card .stat-number { font-size: 1.6rem; }
            .stat-card { padding: 14px 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .stat-card { padding: 12px 14px; }
            .data-table { font-size: 0.7rem; min-width: 700px; }
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
            <input type="text" id="searchInput" placeholder="Search paid bills by patient or bill number..." value="<?= htmlspecialchars($search) ?>">
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i>
                Paid Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-money-bill"></i>
                View all paid bills in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-check-circle" style="color:#34D399;"></i>
                    <span class="count" id="paidCount"><?= $paid_bills ?></span> Paid Bills
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <span class="count" id="patientsPaidCount"><?= $patients_paid ?></span> Patients
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending Bills
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 2 CARDS ONLY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 gap-3 mb-5">
        
        <!-- Card 1: Paid Bills -> paid_bills.php -->
        <a href="paid_bills.php" class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="paidBills"><?= number_format($paid_bills) ?></div>
            <div class="stat-label">Paid Bills</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 2: Patients with Paid Bills -> ../reception/patients.php -->
        <a href="../reception/patients.php" class="stat-card blue">
            <span class="stat-icon">👥</span>
            <div class="stat-number" id="patientsPaid"><?= number_format($patients_paid) ?></div>
            <div class="stat-label">Patients with Paid Bills</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">Search:</label>
                <input type="text" name="search" class="form-control" placeholder="Patient or Bill #" value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">From:</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" style="width:150px;">
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">To:</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" style="width:150px;">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
                <a href="paid_bills.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PAID BILLS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Paid Bills
                <span class="text-sm font-normal text-gray-400" id="paidBillsCount">(<?= count($paid_bills_list) ?> paid bills)</span>
            </h3>
            <span class="text-xs text-gray-400" id="lastUpdateTime">● Live</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius:8px 0 0 0;">Bill #</th>
                        <th>Patient</th>
                        <th>Visit Type</th>
                        <th>Doctor</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                        <th>Date</th>
                        <th style="border-radius:0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="paidBillsList">
                    <?php if (count($paid_bills_list) > 0): ?>
                        <?php foreach ($paid_bills_list as $bill): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($bill['bill_number']) ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($bill['doctor_name'] ?? 'N/A') ?></td>
                                <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                                <td class="font-semibold text-green-600">TSh <?= number_format($bill['total_paid'] ?? 0) ?></td>
                                <td>
                                    <span class="status-badge paid">✅ Paid</span>
                                </td>
                                <td>
                                    <span class="text-sm capitalize">
                                        <?php 
                                            $method = $bill['payment_method'] ?? 'cash';
                                            $methodIcon = $method === 'cash' ? '💵' : ($method === 'm-pesa' ? '📱' : '💳');
                                            echo $methodIcon . ' ' . strtoupper($method);
                                        ?>
                                    </span>
                                </td>
                                <td class="text-sm text-gray-500"><?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-primary btn-sm" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print_receipt.php?bill_id=<?= $bill['id'] ?>" class="btn btn-success btn-sm" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-400">
                                <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                                <p>No paid bills found</p>
                                <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
                                    <p class="text-sm mt-1">Try adjusting your filters</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary -->
        <?php if (count($paid_bills_list) > 0): ?>
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex justify-between text-sm text-gray-500">
                <span>Showing <strong><?= count($paid_bills_list) ?></strong> paid bills</span>
                <span>Total Amount: <strong class="text-green-600">TSh <?= number_format(array_sum(array_column($paid_bills_list, 'total_amount'))) ?></strong></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS -->
    <!-- ================================================================ -->
    <div class="card mt-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i> Recent Payments
            </h3>
        </div>
        <div class="scroll-container" id="recentPaymentsList">
            <?php if (count($recent_payments) > 0): ?>
                <?php foreach ($recent_payments as $payment): ?>
                    <div class="flex items-center justify-between p-2 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition">
                        <div>
                            <p class="font-medium text-sm text-gray-800 dark:text-gray-200"><?= htmlspecialchars($payment['patient_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($payment['bill_number']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-sm text-green-600 dark:text-green-400">TSh <?= number_format($payment['amount'] ?? 0) ?></p>
                            <p class="text-xs text-gray-400">
                                <?php 
                                    $method = $payment['payment_method'] ?? 'cash';
                                    $methodIcon = $method === 'cash' ? '💵' : ($method === 'm-pesa' ? '📱' : '💳');
                                    echo $methodIcon . ' ' . strtoupper($method);
                                ?>
                                • <?= isset($payment['received_at']) ? time_ago($payment['received_at']) : 'N/A' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 text-gray-400">
                    <i class="fas fa-clock text-2xl block mb-2"></i>
                    <p class="text-sm">No recent payments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Paid Bills
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
        var dateFrom = '<?= $date_from ?>';
        var dateTo = '<?= $date_to ?>';
        if (query.length > 0) {
            window.location.href = 'paid_bills.php?search=' + encodeURIComponent(query) + '&date_from=' + dateFrom + '&date_to=' + dateTo;
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
    // TIME AGO FUNCTION
    // ================================================================
    function time_ago(timestamp) {
        if (!timestamp) return 'N/A';
        var now = new Date();
        var past = new Date(timestamp);
        var diff = Math.floor((now - past) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return past.toLocaleDateString();
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

    console.log('%c✅ Braick - Paid Bills (2 Cards Only)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Paid Bills: <?= $paid_bills ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Patients with Paid Bills: <?= $patients_paid ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 Auto-update every 3 seconds via cashier_global_stats.js', 'font-size:13px; color:#34D399;');
    console.log('%c✅ 2 Cards: Paid Bills and Patients with Paid Bills', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>