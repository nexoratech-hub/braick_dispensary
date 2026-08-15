<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dashboard.php
// PHARMACY DASHBOARD - WITH ALL STATS CARDS
// ================================================================
// FIXED:
// 1. Total Stock - EXCLUDES expired medicines (active only)
// 2. Expired Card - INCLUDES all medicines (active + inactive) that are expired
// 3. Branch filter - Only selected branch
// 4. Panadol (inactive + expired) now shows in Expired card
// 5. WITH LOGIN PROTECTION
// 6. USES PHARMACY HEADER (with date & time)
// 7. NO AMOUNTS ON CARDS - Clean display
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
// CHECK IF USER HAS ACCESS (Pharmacy or Admin)
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$branch_name = $user_branch_name;
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();
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
    // 1. TOTAL STOCK ITEMS - EXCLUDES EXPIRED (active only)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, SUM(quantity) as total_quantity
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_stock_items = $stock_data['count'] ?? 0;
    $total_quantity = $stock_data['total_quantity'] ?? 0;
    
    // ================================================================
    // 2. EXPIRED MEDICINES - INCLUDES ALL (active + inactive)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, SUM(quantity) as total_quantity
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $expired_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $expired_count = $expired_data['count'] ?? 0;
    $expired_quantity = $expired_data['total_quantity'] ?? 0;
    
    // Expired list - includes all (active + inactive)
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, expiry_date, batch_number, status
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expired_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 3. EXPIRE SOON (expiry_date between today and 30 days from now)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Expire soon list
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, expiry_date, batch_number
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 4. TOTAL PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 5. OTC SALES
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM otc_sales 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $otc_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_sales_count = $otc_data['count'] ?? 0;
    
    // Today's OTC Sales
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM otc_sales 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $otc_today_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_today_count = $otc_today_data['count'] ?? 0;
    
    // ================================================================
    // 6. DISPENSED PRESCRIPTIONS
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
    // 7. LOW STOCK
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity > 0 
        AND quantity <= reorder_level
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Low stock list
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, reorder_level, unit
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity > 0 
        AND quantity <= reorder_level
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY quantity ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 8. PENDING PRESCRIPTIONS
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
    // 9. OUT OF STOCK
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity = 0
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Out of stock list
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, reorder_level, unit
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity = 0
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY medication_name ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 10. RECENT PRESCRIPTIONS
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
    // 11. RECENT OTC SALES
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
    // Set default values on error
    $total_stock_items = 0;
    $total_quantity = 0;
    $expired_count = 0;
    $expired_quantity = 0;
    $expired_list = [];
    $expire_soon_count = 0;
    $expire_soon_list = [];
    $total_prescriptions = 0;
    $otc_sales_count = 0;
    $otc_today_count = 0;
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
           MAIN CONTENT OFFSET FOR HEADER
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: all 0.3s ease;
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
           STATS CARDS - 9 CARDS - NO AMOUNTS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            margin-bottom: 2px;
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
        .stat-card.gray { background: linear-gradient(135deg, #6B7280, #4B5563); }
        
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
           QUICK ACTIONS
           ================================================================ */
        .quick-action {
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
        }
        
        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .quick-action .icon {
            font-size: 1.6rem;
            display: block;
            margin-bottom: 6px;
        }
        
        .quick-action .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           EXPIRE ITEMS
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
        .expire-item .expire-date.expired { color: #DC2626; font-weight: 600; }
        
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
        
        .expired-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .expired-row {
            background: var(--danger-bg) !important;
            border-radius: 4px;
            margin-bottom: 2px;
        }
        
        [data-theme="dark"] .expired-row {
            background: #3A1A1A !important;
        }
        
        /* ================================================================
           GRID
           ================================================================ */
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .grid-cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .gap-5 { gap: 20px; }
        .mt-5 { margin-top: 20px; }
        .mb-2 { margin-bottom: 8px; }
        
        .text-center { text-align: center; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .font-normal { font-weight: 400; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .text-gray-400 { color: var(--gray-400); }
        .text-primary { color: var(--primary); }
        .text-green-500 { color: var(--success); }
        .text-red-500 { color: var(--danger); }
        .text-orange-500 { color: var(--warning); }
        .block { display: block; }
        .inline-block { display: inline-block; }
        .py-4 { padding-top: 16px; padding-bottom: 16px; }
        .py-6 { padding-top: 24px; padding-bottom: 24px; }
        .text-2xl { font-size: 1.5rem; }
        .text-3xl { font-size: 1.875rem; }
        .hover\:underline:hover { text-decoration: underline; }
        .ml-1 { margin-left: 4px; }
        .ml-2 { margin-left: 8px; }
        .mr-1 { margin-right: 4px; }
        .mr-2 { margin-right: 8px; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .stat-card { padding: 14px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .grid-cols-3 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .stat-card { padding: 10px 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .grid-cols-3 { grid-template-columns: 1fr 1fr; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .btn-sm {
            padding: 2px 10px;
            font-size: 0.65rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>

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
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
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
    <!-- STATS CARDS - 9 CARDS - NO AMOUNTS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        
        <!-- 1. Total Stock Items - Blue (EXCLUDES EXPIRED) -->
        <a href="inventory.php" class="stat-card blue">
            <span class="stat-icon">📦</span>
            <div class="stat-number" id="totalStock"><?= $total_stock_items ?></div>
            <div class="stat-label">Total Stock Items</div>
            <div class="stat-sub"><?= $total_quantity ?> units total</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 2. Expired - Red (INCLUDES ALL - active + inactive) -->
        <a href="expired.php" class="stat-card red">
            <span class="stat-icon">🚫</span>
            <div class="stat-number" id="expiredCount"><?= $expired_count ?></div>
            <div class="stat-label">Expired</div>
            <div class="stat-sub"><?= $expired_quantity ?> units expired</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 3. Expire Soon - Orange -->
        <a href="expiring_soon.php" class="stat-card orange">
            <span class="stat-icon">⏰</span>
            <div class="stat-number" id="expireSoon"><?= $expire_soon_count ?></div>
            <div class="stat-label">Expire Soon</div>
            <div class="stat-sub">Within 30 days</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 4. Total Prescriptions - Purple -->
        <a href="prescription_history.php" class="stat-card purple">
            <span class="stat-icon">📋</span>
            <div class="stat-number" id="totalPrescriptions"><?= $total_prescriptions ?></div>
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-sub">All time</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 5. OTC Sales - Teal -->
        <a href="otc_history.php" class="stat-card teal">
            <span class="stat-icon">🛒</span>
            <div class="stat-number" id="otcSales"><?= $otc_sales_count ?></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-sub"><?= $otc_today_count ?> today</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 6. Dispensed - Green -->
        <a href="dispensed_prescriptions.php" class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="dispensed"><?= $dispensed_count ?></div>
            <div class="stat-label">Dispensed</div>
            <div class="stat-sub"><?= $dispensed_today ?> today</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 7. Low Stock - Yellow -->
        <a href="low_stock.php" class="stat-card yellow">
            <span class="stat-icon">⚠️</span>
            <div class="stat-number" id="lowStock"><?= $low_stock_count ?></div>
            <div class="stat-label">Low Stock</div>
            <div class="stat-sub">Below reorder level</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 8. Pending - Pink -->
        <a href="pending_prescriptions.php" class="stat-card pink">
            <span class="stat-icon">⏳</span>
            <div class="stat-number" id="pending"><?= $pending_count ?></div>
            <div class="stat-label">Pending</div>
            <div class="stat-sub">Awaiting dispensing</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 9. Out of Stock - Gray -->
        <a href="out_of_stock.php" class="stat-card gray">
            <span class="stat-icon">🚫</span>
            <div class="stat-number" id="outOfStock"><?= $out_of_stock_count ?></div>
            <div class="stat-label">Out of Stock</div>
            <div class="stat-sub">Quantity = 0</div>
            <div class="stat-update"><span class="live-dot"></span> Live</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- LISTS: Expired, Expire Soon, Low Stock, Out of Stock, Pending -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Expired List - INCLUDES INACTIVE TOO -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-skull title-red mr-2"></i> Expired
                    <span class="text-sm font-normal text-red-500">(<?= $expired_count ?> items)</span>
                    <?php if ($expired_count > 0): ?>
                        <span class="text-xs text-red-500 font-normal">⚠️ Includes inactive medicines</span>
                    <?php endif; ?>
                </h3>
                <a href="expired.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="expiredList">
                <?php if (count($expired_list) > 0): ?>
                    <?php foreach ($expired_list as $item): ?>
                        <div class="expire-item expired-row">
                            <span class="med-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                            <span class="qty"><?= $item['quantity'] ?> units</span>
                            <span class="expire-date expired">
                                <i class="fas fa-calendar-times mr-1"></i>
                                <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                <span class="expired-badge ml-1">EXPIRED</span>
                                <?php if ($item['status'] === 'inactive'): ?>
                                    <span class="text-xs text-gray-400 ml-1">(inactive)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No expired medicines</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
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
                            <a href="reorder.php?id=<?= $item['id'] ?>" class="btn-sm" style="background:var(--primary);color:white;">
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
                            <a href="dispense.php?id=<?= $pres['id'] ?>" class="btn-sm" style="background:var(--success);color:white;">
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
        <a href="new_prescription.php" class="quick-action">
            <span class="icon" style="color:#0B5ED7;">📝</span>
            <span class="label">New Prescription</span>
        </a>
        
        <a href="new_otc_sale.php" class="quick-action">
            <span class="icon" style="color:#059669;">🛒</span>
            <span class="label">OTC Sale</span>
        </a>
        
        <a href="inventory.php" class="quick-action">
            <span class="icon" style="color:#7C3AED;">📦</span>
            <span class="label">Inventory</span>
        </a>
        
        <a href="reports.php" class="quick-action">
            <span class="icon" style="color:#D97706;">📊</span>
            <span class="label">Reports</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Pharmacy Dashboard
            <span class="text-gray-400 mx-2">|</span>
            <span id="footerTimestamp">● Live</span>
            <span class="text-gray-400 mx-2">|</span>
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
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    // Listen for dark mode changes from header
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
        
        console.log('🌙 Dashboard dark mode synced: ' + (isDark ? 'ON ✅' : 'OFF'));
    });

    console.log('%c💊 Braick - Pharmacy Dashboard (NO AMOUNTS)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📦 Total Stock (excl. expired): <?= $total_stock_items ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🚫 Expired (all): <?= $expired_count ?> (units: <?= $expired_quantity ?>)', 'font-size:13px; color:#DC2626;');
    console.log('%c⏰ Expire Soon: <?= $expire_soon_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📋 Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Sales: <?= $otc_sales_count ?> (<?= $otc_today_count ?> today)', 'font-size:13px; color:#0D9488;');
    console.log('%c✅ Dispensed: <?= $dispensed_count ?> (<?= $dispensed_today ?> today)', 'font-size:13px; color:#059669;');
    console.log('%c⚠️ Low Stock: <?= $low_stock_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c⏳ Pending: <?= $pending_count ?>', 'font-size:13px; color:#DB2777;');
    console.log('%c🚫 Out of Stock: <?= $out_of_stock_count ?>', 'font-size:13px; color:#6B7280;');
    console.log('%c✅ NO amounts displayed on cards - Clean view', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>