<?php
// ================================================================
// FILE: frontend/pages/cashier/dashboard.php
// CASHIER DASHBOARD (GREEN THEME)
// WITH AUTO-UPDATE (3 SECONDS) - NO REFRESH NEEDED
// WITH CLICKABLE STAT CARDS (5 CARDS)
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
    // GET CASHIER STATISTICS
    // ================================================================
    
    // 1. Pending Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status IN ('pending', 'partial')
    ");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch()['count'] ?? 0;
    
    // 2. Today's Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) = ?
    ");
    $stmt->execute([$user_branch_id, $today]);
    $today_payments = $stmt->fetch()['count'] ?? 0;
    
    // 3. Total Bills
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_bills = $stmt->fetch()['count'] ?? 0;
    
    // 4. Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $paid_bills = $stmt->fetch()['count'] ?? 0;
    
    // 5. Today's Receipts
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM receipts 
        WHERE DATE(printed_at) = ?
    ");
    $stmt->execute([$today]);
    $today_receipts = $stmt->fetch()['count'] ?? 0;
    
    // 6. Recent Payments
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
    
    // 7. Payment Methods
    $stmt = $db->prepare("
        SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) = ?
        GROUP BY payment_method
    ");
    $stmt->execute([$user_branch_id, $today]);
    $payment_methods = $stmt->fetchAll();
    
    // 8. Patients with Bills (for quick access)
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id,
            (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND branch_id = ? AND status IN ('pending', 'partial')) as pending_bills_count,
            (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND branch_id = ? AND status = 'paid') as paid_bills_count
        FROM patients p
        WHERE p.branch_id = ?
        AND EXISTS (SELECT 1 FROM patient_bills WHERE patient_id = p.id AND branch_id = ?)
        ORDER BY p.full_name
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id, $user_branch_id, $user_branch_id, $user_branch_id]);
    $patients_with_bills = $stmt->fetchAll();
    
} catch (Exception $e) {
    $pending_bills = 0;
    $today_payments = 0;
    $total_bills = 0;
    $paid_bills = 0;
    $today_receipts = 0;
    $recent_payments = [];
    $payment_methods = [];
    $patients_with_bills = [];
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
    <title>Cashier Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME
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
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
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
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
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
           TOP NAV - GREEN THEME
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
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
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
            background: var(--success);
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
            background: var(--success-dark);
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
            border-color: var(--success);
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
            color: var(--success);
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
            border-color: var(--success);
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
           STAT CARDS - CLICKABLE (5 CARDS)
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
        
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.green { background: linear-gradient(135deg, var(--success), var(--success-dark)); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        
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
            border-color: var(--success);
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
        .card-title .title-orange { color: #D97706; }
        
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
            background: var(--success);
            border-radius: 4px;
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
            color: var(--success); 
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
            .patient-item { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .stat-card { padding: 12px 14px; }
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
           PATIENT BILLS ITEM
           ================================================================ */
        .patient-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .patient-item:hover {
            background: var(--bg-body);
            border-radius: 8px;
        }
        
        .patient-item:last-child {
            border-bottom: none;
        }
        
        .patient-item .patient-name {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .patient-item .patient-id {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .patient-item .bill-count {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .patient-item .bill-count.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .patient-item .bill-count.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        [data-theme="dark"] .patient-item .bill-count.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .patient-item .bill-count.paid {
            background: #1A3A2A;
            color: #34D399;
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
            <input type="text" id="searchInput" placeholder="Search patients...">
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
                <i class="fas fa-home"></i>
                Cashier Dashboard
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Welcome back, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-clock"></i>
                    <span class="count" id="pendingCount"><?= $pending_bills ?></span> Pending Bills
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-receipt"></i> Pending Bills
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - CLICKABLE NAVIGATION (5 CARDS) -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
        
        <!-- Card 1: Pending Bills -> pending_bills.php -->
        <a href="pending_bills.php" class="stat-card red">
            <span class="stat-icon">⏳</span>
            <div class="stat-number" id="pendingBills"><?= $pending_bills ?></div>
            <div class="stat-label">Pending Bills</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 2: Today's Payments -> pending_bills.php -->
        <a href="pending_bills.php" class="stat-card blue">
            <span class="stat-icon">💳</span>
            <div class="stat-number" id="todayPayments"><?= $today_payments ?></div>
            <div class="stat-label">Today's Payments</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 3: Total Bills -> pending_bills.php -->
        <a href="pending_bills.php" class="stat-card orange">
            <span class="stat-icon">📋</span>
            <div class="stat-number" id="totalBills"><?= number_format($total_bills) ?></div>
            <div class="stat-label">Total Bills</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 4: Paid Bills -> paid_bills.php -->
        <a href="paid_bills.php" class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="paidBills"><?= number_format($paid_bills) ?></div>
            <div class="stat-label">Paid Bills</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 5: Today's Receipts -> print_receipt.php -->
        <a href="print_receipt.php" class="stat-card teal">
            <span class="stat-icon">🧾</span>
            <div class="stat-number" id="todayReceipts"><?= $today_receipts ?></div>
            <div class="stat-label">Today's Receipts</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- PATIENTS WITH BILLS - QUICK ACCESS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users title-blue mr-2"></i> Patients with Bills
                <span class="text-sm font-normal text-gray-400">(Click to view patient bills)</span>
            </h3>
            <a href="../reception/patients.php" class="text-primary text-sm hover:underline">View All →</a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2" id="patientsWithBills">
            <?php if (count($patients_with_bills) > 0): ?>
                <?php foreach ($patients_with_bills as $patient): ?>
                    <a href="patient_bills.php?patient_id=<?= $patient['id'] ?>" 
                       class="patient-item hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg p-2 border border-transparent hover:border-success transition">
                        <div>
                            <p class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></p>
                            <p class="patient-id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                        </div>
                        <div class="flex gap-2">
                            <?php if (($patient['pending_bills_count'] ?? 0) > 0): ?>
                                <span class="bill-count pending">⏳ <?= $patient['pending_bills_count'] ?></span>
                            <?php endif; ?>
                            <?php if (($patient['paid_bills_count'] ?? 0) > 0): ?>
                                <span class="bill-count paid">✅ <?= $patient['paid_bills_count'] ?></span>
                            <?php endif; ?>
                            <?php if (($patient['pending_bills_count'] ?? 0) == 0 && ($patient['paid_bills_count'] ?? 0) == 0): ?>
                                <span class="bill-count" style="background:#E2E8F0;color:#64748B;">No bills</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-4 text-gray-400">
                    <i class="fas fa-users text-2xl block mb-2"></i>
                    <p class="text-sm">No patients with bills found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS & PAYMENT METHODS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Recent Payments -->
        <div class="card lg:col-span-2">
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
                                <p class="font-semibold text-sm text-green-600 dark:text-green-400"><?= number_format($payment['amount'] ?? 0) ?></p>
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
        
        <!-- Payment Methods -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie title-green mr-2"></i> Today's Payment Methods
                </h3>
            </div>
            <div class="scroll-container" id="paymentMethods">
                <?php if (count($payment_methods) > 0): ?>
                    <?php 
                        $methodIcons = [
                            'cash' => '💵',
                            'm-pesa' => '📱',
                            'airtel_money' => '📱',
                            'tigo_pesa' => '📱',
                            'halopesa' => '📱',
                            'card' => '💳',
                            'bank' => '🏦',
                            'insurance' => '🏥',
                            'other' => '📦'
                        ];
                    ?>
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm"><?= $methodIcons[$method['payment_method']] ?? '💵' ?> <?= strtoupper($method['payment_method'] ?? 'CASH') ?></span>
                            <span class="text-sm text-gray-500"><?= $method['count'] ?> payments</span>
                            <span class="font-semibold text-sm text-green-600 dark:text-green-400"><?= number_format($method['total'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <p class="text-sm">No payments today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
        <a href="pending_bills.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#DC2626;">⏳</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Pending Bills</span>
        </a>
        
        <a href="paid_bills.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#059669;">✅</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Paid Bills</span>
        </a>
        
        <a href="print_receipt.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#0B5ED7;">🧾</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Print Receipt</span>
        </a>
        
        <a href="../reception/patients.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#7C3AED;">👥</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">View Patients</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Cashier Dashboard
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
            showToast('✅ Refreshed', 'Dashboard data updated manually', 'success');
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

    console.log('%c🟢 Braick - Cashier Dashboard (Green Theme)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👋 Welcome, <?= htmlspecialchars($user_full_name) ?>!', 'font-size:13px; color:#64748B;');
    console.log('%c📊 5 Cards: Pending Bills, Today\'s Payments, Total Bills, Paid Bills, Today\'s Receipts', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⏳ Pending Bills: <?= $pending_bills ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💳 Today\'s Payments: <?= $today_payments ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🧾 Today\'s Receipts: <?= $today_receipts ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update every 3 seconds via cashier_global_stats.js', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Click any stat card to navigate to relevant page', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🟢 Green theme applied to all components', 'font-size:13px; color:#059669;');
</script>

</body>
</html>