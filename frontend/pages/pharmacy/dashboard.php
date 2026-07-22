<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dashboard.php
// PHARMACY DASHBOARD - WITH ALL STATS CARDS
// Total Stock, Expire Soon, Total Prescriptions, OTC Sales,
// Dispensed, Low Stock, Pending, Out of Stock
// WITH AUTO-UPDATE (3 SECONDS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Pharmacy Dodoma
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy';
$unread_notifications = 0;

try {
    $db = getDB();
    $today = date('Y-m-d');
    $thirty_days_later = date('Y-m-d', strtotime('+30 days'));
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS
    // ================================================================
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
    
    // ================================================================
    // 1. TOTAL STOCK ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, SUM(quantity) as total_quantity
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_stock_items = $stock_data['count'] ?? 0;
    $total_quantity = $stock_data['total_quantity'] ?? 0;
    
    // ================================================================
    // 2. EXPIRE SOON & EXPIRED
    // ================================================================
    // Expired (expiry_date < today)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' 
        AND expiry_date IS NOT NULL AND expiry_date < CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $expired_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Expire Soon (expiry_date between today and 30 days from now)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' 
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Expire soon list (for display)
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, expiry_date, batch_number
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' 
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 3. TOTAL PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 4. OTC SALES
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total_revenue
        FROM otc_sales 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $otc_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_sales_count = $otc_data['count'] ?? 0;
    $otc_revenue = $otc_data['total_revenue'] ?? 0;
    
    // Today's OTC Sales
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total_revenue
        FROM otc_sales 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $otc_today_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_today_count = $otc_today_data['count'] ?? 0;
    $otc_today_revenue = $otc_today_data['total_revenue'] ?? 0;
    
    // ================================================================
    // 5. DISPENSED PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? AND status = 'dispensed'
    ");
    $stmt->execute([$user_branch_id]);
    $dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's Dispensed
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? AND status = 'dispensed' AND DATE(dispensed_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $dispensed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 6. LOW STOCK
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' 
        AND quantity > 0 AND quantity <= reorder_level
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Low stock list
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, reorder_level, unit
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' 
        AND quantity > 0 AND quantity <= reorder_level
        ORDER BY quantity ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 7. PENDING PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_branch_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Pending prescriptions list
    $stmt = $db->prepare("
        SELECT p.*, pat.full_name as patient_name, pat.patient_id as patient_code
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        WHERE p.branch_id = ? AND p.status = 'pending'
        ORDER BY p.created_at ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 8. OUT OF STOCK
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' AND quantity = 0
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Out of stock list
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, reorder_level, unit
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' AND quantity = 0
        ORDER BY medication_name ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 9. RECENT PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, pat.full_name as patient_name, pat.patient_id as patient_code,
               u.full_name as doctor_name
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 10. RECENT OTC SALES
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM otc_sales 
        WHERE branch_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    
    // Set default values
    $total_stock_items = 0;
    $total_quantity = 0;
    $expired_count = 0;
    $expire_soon_count = 0;
    $expire_soon_list = [];
    $total_prescriptions = 0;
    $otc_sales_count = 0;
    $otc_revenue = 0;
    $otc_today_count = 0;
    $otc_today_revenue = 0;
    $dispensed_count = 0;
    $dispensed_today = 0;
    $low_stock_count = 0;
    $low_stock_list = [];
    $pending_count = 0;
    $pending_list = [];
    $out_of_stock_count = 0;
    $out_of_stock_list = [];
    $recent_prescriptions = [];
    $recent_otc_sales = [];
}

// ================================================================
// INCLUDE PHARMACY HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - PHARMACY THEME
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
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
            
            --pink: #DB2777;
            --pink-bg: #FCE7F3;
            
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
           STATS CARDS - 8 CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
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
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            opacity: 0.9;
            margin-bottom: 4px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
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
        
        .stat-card .stat-sub {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.5);
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
        
        /* Card Colors */
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
        .stat-card.yellow { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* ================================================================
           CARDS
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
        .card-title .title-purple { color: var(--purple); }
        .card-title .title-orange { color: #D97706; }
        .card-title .title-red { color: var(--danger); }
        
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
            max-height: 300px;
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
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.dispensed { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
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
        .toast-custom.warning { background: #D97706; }
        
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .stat-card { padding: 14px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .stat-card { padding: 10px 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
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
           EXPIRE SOON ITEM
           ================================================================ */
        .expire-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
        }
        .expire-item:last-child { border-bottom: none; }
        .expire-item .med-name { font-weight: 500; }
        .expire-item .expire-date { font-size: 0.7rem; }
        .expire-item .expire-date.warning { color: #D97706; }
        .expire-item .expire-date.danger { color: #DC2626; }
        .expire-item .expire-date.success { color: #059669; }
        
        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
        }
        .stock-item:last-child { border-bottom: none; }
        .stock-item .qty { font-weight: 600; }
        .stock-item .qty.low { color: #D97706; }
        .stock-item .qty.out { color: #DC2626; }
        
        .prescription-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
        }
        .prescription-item:last-child { border-bottom: none; }
        .prescription-item .patient-name { font-weight: 500; }
        .prescription-item .medication { font-size: 0.7rem; color: var(--text-secondary); }
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
            <input type="text" id="searchInput" placeholder="Search medications...">
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
                <i class="fas fa-prescription"></i>
                Pharmacy Dashboard
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">PHARMACY</span>
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
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending (<?= $pending_count ?>)
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 8 CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        
        <!-- 1. Total Stock Items - Blue -->
        <a href="inventory.php" class="stat-card blue">
            <span class="stat-icon">📦</span>
            <div class="stat-number" id="totalStock"><?= $total_stock_items ?></div>
            <div class="stat-label">Total Stock Items</div>
            <div class="stat-sub"><?= $total_quantity ?> units total</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 2. Expire Soon - Orange -->
        <a href="expiring_soon.php" class="stat-card orange">
            <span class="stat-icon">⏰</span>
            <div class="stat-number" id="expireSoon"><?= $expire_soon_count ?></div>
            <div class="stat-label">Expire Soon</div>
            <div class="stat-sub"><?= $expired_count ?> expired</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 3. Total Prescriptions - Purple -->
        <a href="prescription_history.php" class="stat-card purple">
            <span class="stat-icon">📋</span>
            <div class="stat-number" id="totalPrescriptions"><?= $total_prescriptions ?></div>
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-sub">All time</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 4. OTC Sales - Teal -->
        <a href="otc_history.php" class="stat-card teal">
            <span class="stat-icon">🛒</span>
            <div class="stat-number" id="otcSales"><?= $otc_sales_count ?></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-sub">TSh <?= number_format($otc_revenue, 0) ?> total</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 5. Dispensed - Green -->
        <a href="dispensed_prescriptions.php" class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="dispensed"><?= $dispensed_count ?></div>
            <div class="stat-label">Dispensed</div>
            <div class="stat-sub"><?= $dispensed_today ?> today</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 6. Low Stock - Yellow -->
        <a href="low_stock.php" class="stat-card yellow">
            <span class="stat-icon">⚠️</span>
            <div class="stat-number" id="lowStock"><?= $low_stock_count ?></div>
            <div class="stat-label">Low Stock</div>
            <div class="stat-sub">Below reorder level</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 7. Pending - Red -->
        <a href="pending_prescriptions.php" class="stat-card red">
            <span class="stat-icon">⏳</span>
            <div class="stat-number" id="pending"><?= $pending_count ?></div>
            <div class="stat-label">Pending</div>
            <div class="stat-sub">Awaiting dispensing</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 8. Out of Stock - Pink -->
        <a href="out_of_stock.php" class="stat-card pink">
            <span class="stat-icon">🚫</span>
            <div class="stat-number" id="outOfStock"><?= $out_of_stock_count ?></div>
            <div class="stat-label">Out of Stock</div>
            <div class="stat-sub">Quantity = 0</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- LISTS: Expire Soon, Low Stock, Out of Stock, Pending -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Expire Soon List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-orange mr-2"></i> Expire Soon
                    <span class="text-sm font-normal text-gray-400">(<?= $expire_soon_count ?> items)</span>
                </h3>
                <a href="expiring_soon.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="expireSoonList">
                <?php if (count($expire_soon_list) > 0): ?>
                    <?php foreach ($expire_soon_list as $item): ?>
                        <div class="expire-item">
                            <span class="med-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                            <span class="qty"><?= $item['quantity'] ?> units</span>
                            <span class="expire-date warning">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No items expiring soon</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Low Stock List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle title-orange mr-2"></i> Low Stock
                    <span class="text-sm font-normal text-gray-400">(<?= $low_stock_count ?> items)</span>
                </h3>
                <a href="low_stock.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="lowStockList">
                <?php if (count($low_stock_list) > 0): ?>
                    <?php foreach ($low_stock_list as $item): ?>
                        <div class="stock-item">
                            <span class="med-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                            <span class="qty low"><?= $item['quantity'] ?> / <?= $item['reorder_level'] ?></span>
                            <span class="text-xs text-gray-400">Reorder at <?= $item['reorder_level'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No low stock items</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Out of Stock List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-times-circle title-red mr-2"></i> Out of Stock
                    <span class="text-sm font-normal text-gray-400">(<?= $out_of_stock_count ?> items)</span>
                </h3>
                <a href="out_of_stock.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="outOfStockList">
                <?php if (count($out_of_stock_list) > 0): ?>
                    <?php foreach ($out_of_stock_list as $item): ?>
                        <div class="stock-item">
                            <span class="med-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                            <span class="qty out">0 units</span>
                            <a href="reorder.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm" style="padding:2px 10px;font-size:0.65rem;background:var(--primary);color:white;border-radius:4px;text-decoration:none;">
                                <i class="fas fa-shopping-cart"></i> Reorder
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>All items in stock</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Prescriptions List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-red mr-2"></i> Pending Prescriptions
                    <span class="text-sm font-normal text-gray-400">(<?= $pending_count ?> items)</span>
                </h3>
                <a href="pending_prescriptions.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="pendingList">
                <?php if (count($pending_list) > 0): ?>
                    <?php foreach ($pending_list as $pres): ?>
                        <div class="prescription-item">
                            <div>
                                <span class="patient-name"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></span>
                                <span class="medication block"><?= htmlspecialchars($pres['medication'] ?? 'N/A') ?></span>
                            </div>
                            <span class="status-badge pending">Pending</span>
                            <a href="dispense.php?id=<?= $pres['id'] ?>" class="btn btn-success btn-sm" style="padding:2px 10px;font-size:0.65rem;background:var(--success);color:white;border-radius:4px;text-decoration:none;">
                                <i class="fas fa-prescription"></i> Dispense
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No pending prescriptions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
        <a href="new_prescription.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#0B5ED7;">📝</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">New Prescription</span>
        </a>
        
        <a href="new_otc_sale.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#059669;">🛒</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">OTC Sale</span>
        </a>
        
        <a href="inventory.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#7C3AED;">📦</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Inventory</span>
        </a>
        
        <a href="reports.php" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#D97706;">📊</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Reports</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacy Dashboard
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
<!-- PHARMACY GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/pharmacy_global_stats.js"></script>

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

    console.log('%c💊 Braick - Pharmacy Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📦 Total Stock: <?= $total_stock_items ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⏰ Expire Soon: <?= $expire_soon_count ?> | Expired: <?= $expired_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📋 Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Sales: <?= $otc_sales_count ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c✅ Dispensed: <?= $dispensed_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c⚠️ Low Stock: <?= $low_stock_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c⏳ Pending: <?= $pending_count ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c🚫 Out of Stock: <?= $out_of_stock_count ?>', 'font-size:13px; color:#DB2777;');
    console.log('%c🔄 Auto-update every 3 seconds via pharmacy_global_stats.js', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>