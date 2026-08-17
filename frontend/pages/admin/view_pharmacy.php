<?php
// ================================================================
// FILE: frontend/pages/admin/view_pharmacy.php
// SUPER ADMIN - VIEW PHARMACY BRANCH DETAILS
// BRAICK DISPENSARY - BLUE THEME - BLUE CARDS
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
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID
// ================================================================
$pharmacy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($pharmacy_id <= 0) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH PHARMACY DETAILS WITH ALL STATS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= reorder_level AND quantity > 0) as low_stock_items,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= 0) as out_of_stock_items,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'confirmed') as confirmed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'cancelled') as cancelled_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id) as total_prescriptions,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE branch_id = b.id AND status = 'paid' AND bill_number LIKE 'BILL-PRES-%') as prescription_revenue,
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc_sales,
        (SELECT COALESCE(SUM(net_amount), 0) FROM otc_sales WHERE branch_id = b.id AND payment_status = 'paid') as otc_revenue,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date < CURDATE()) as expired_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date IS NOT NULL) as medicines_with_expiry,
        (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'paid') as total_paid_bills,
        (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'pending') as total_pending_bills,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE branch_id = b.id AND status = 'paid') as total_bill_revenue
    FROM branches b
    WHERE b.id = ?
");
$stmt->execute([$pharmacy_id]);
$pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pharmacy) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// Calculate total revenue (prescription + OTC)
$total_revenue = ($pharmacy['prescription_revenue'] ?? 0) + ($pharmacy['otc_revenue'] ?? 0);
$total_pharmacy_revenue = ($pharmacy['total_bill_revenue'] ?? 0) + ($pharmacy['otc_revenue'] ?? 0);

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PHARMACISTS FOR THIS BRANCH
// ================================================================
$pharmacists = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, status, created_at 
        FROM users 
        WHERE branch_id = ? AND role = 'pharmacy'
        ORDER BY full_name
    ");
    $stmt->execute([$pharmacy_id]);
    $pharmacists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pharmacists = [];
}

// ================================================================
// GET RECENT PRESCRIPTIONS FOR THIS BRANCH
// ================================================================
$recent_prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.prescription_number,
            p.status,
            p.created_at,
            pat.full_name as patient_name,
            u.full_name as doctor_name
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_prescriptions = [];
}

// ================================================================
// GET RECENT INVENTORY ITEMS
// ================================================================
$recent_inventory = [];
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            medication_name,
            category,
            quantity,
            reorder_level,
            selling_price,
            expiry_date,
            status,
            updated_at
        FROM medications_inventory
        WHERE branch_id = ?
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_inventory = [];
}

// ================================================================
// GET RECENT OTC SALES
// ================================================================
$recent_otc_sales = [];
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            sale_number,
            customer_name,
            total_amount,
            net_amount,
            payment_method,
            payment_status,
            created_at
        FROM otc_sales
        WHERE branch_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_otc_sales = [];
}

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE branch_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'dispensed' => 'success',
        'confirmed' => 'info',
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'dispensed' => 'fa-check-circle',
        'confirmed' => 'fa-check-double',
        'cancelled' => 'fa-times-circle',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Pharmacy - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
            
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.5);
            --table-hover: #1E293B;
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
           TOP NAV - SHARED HEADER
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
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
            border-radius: var(--radius);
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
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
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
           PAGE HEADER - BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
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
            background: rgba(255,255,255,0.12);
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
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
           DETAILS CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           STATS CARDS - ALL BLUE THEME (BLUE BACKGROUND)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(11, 94, 215, 0.2);
            text-decoration: none;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.35);
            border-color: rgba(255,255,255,0.2);
        }
        
        .stat-card .stat-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            z-index: 1;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }
        
        .stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.8);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }
        
        .stat-arrow {
            opacity: 0.5;
            transition: all 0.3s ease;
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card:hover .stat-arrow {
            opacity: 1;
            transform: translateX(4px);
            color: rgba(255,255,255,0.9);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           DATA TABLE - IMPROVED DESIGN
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            font-weight: 600;
            padding: 12px 16px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Status badges inside table */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .status-badge.success { background: #D1FAE5; color: #059669; }
        .status-badge.danger { background: #FEE2E2; color: #DC2626; }
        .status-badge.warning { background: #FEF3C7; color: #D97706; }
        .status-badge.info { background: #EFF6FF; color: #0B5ED7; }
        .status-badge.secondary { background: #F1F5F9; color: #64748B; }
        
        [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.warning { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 16px 24px;
            background: var(--primary-gradient);
            border-bottom: 2px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-header .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-header .card-title i {
            margin-right: 8px;
            color: rgba(255,255,255,0.8);
        }
        
        .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .card-header .card-action:hover {
            color: white;
        }
        
        .card-body {
            padding: 0;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .btn-primary:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* ================================================================
           ALERT CARDS
           ================================================================ */
        .alert-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        
        .alert-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            border: 2px solid;
            transition: all 0.3s ease;
            background: var(--bg-card);
            text-decoration: none;
            color: inherit;
        }
        
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .alert-card .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .alert-card .alert-number {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .alert-card .alert-label {
            font-size: 0.65rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .alert-card .alert-arrow {
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }
        
        .alert-card:hover .alert-arrow {
            opacity: 1;
            transform: translateX(4px);
        }
        
        /* Alert card variants */
        .alert-danger {
            border-color: #FCA5A5;
            background: #FEF2F2;
        }
        .alert-danger .alert-icon { background: #FEE2E2; color: #DC2626; }
        .alert-danger .alert-number { color: #DC2626; }
        .alert-danger .alert-label { color: #991B1B; }
        
        .alert-warning {
            border-color: #FCD34D;
            background: #FFFBEB;
        }
        .alert-warning .alert-icon { background: #FEF3C7; color: #D97706; }
        .alert-warning .alert-number { color: #D97706; }
        .alert-warning .alert-label { color: #92400E; }
        
        .alert-orange {
            border-color: #FDBA74;
            background: #FFF7ED;
        }
        .alert-orange .alert-icon { background: #FFEDD5; color: #EA580C; }
        .alert-orange .alert-number { color: #EA580C; }
        .alert-orange .alert-label { color: #9A3412; }
        
        .alert-neutral {
            border-color: var(--border-color);
            background: var(--bg-body);
        }
        .alert-neutral .alert-icon { background: var(--border-color); color: var(--text-secondary); }
        .alert-neutral .alert-number { color: var(--text-secondary); }
        .alert-neutral .alert-label { color: var(--text-secondary); }
        
        /* Dark mode alert cards */
        [data-theme="dark"] .alert-danger {
            border-color: #7F1D1D;
            background: #1A1A2E;
        }
        [data-theme="dark"] .alert-danger .alert-icon { background: #2D1A1A; color: #F87171; }
        [data-theme="dark"] .alert-danger .alert-number { color: #F87171; }
        [data-theme="dark"] .alert-danger .alert-label { color: #FCA5A5; }
        
        [data-theme="dark"] .alert-warning {
            border-color: #713F12;
            background: #1A1A2E;
        }
        [data-theme="dark"] .alert-warning .alert-icon { background: #2D2A1A; color: #FBBF24; }
        [data-theme="dark"] .alert-warning .alert-number { color: #FBBF24; }
        [data-theme="dark"] .alert-warning .alert-label { color: #FCD34D; }
        
        [data-theme="dark"] .alert-orange {
            border-color: #7C2D12;
            background: #1A1A2E;
        }
        [data-theme="dark"] .alert-orange .alert-icon { background: #2D1A1A; color: #FB923C; }
        [data-theme="dark"] .alert-orange .alert-number { color: #FB923C; }
        [data-theme="dark"] .alert-orange .alert-label { color: #FDBA74; }
        
        [data-theme="dark"] .alert-neutral {
            border-color: #334155;
            background: #1E293B;
        }
        [data-theme="dark"] .alert-neutral .alert-icon { background: #334155; color: #94A3B8; }
        [data-theme="dark"] .alert-neutral .alert-number { color: #94A3B8; }
        [data-theme="dark"] .alert-neutral .alert-label { color: #94A3B8; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
            .alert-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
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
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .detail-card { padding: 16px; }
            .alert-grid { grid-template-columns: 1fr 1fr !important; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .alert-grid { grid-template-columns: 1fr !important; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
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
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stat-card:hover .stat-icon {
            animation: pulse 0.5s ease;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .detail-card { box-shadow: none !important; border: 1px solid #ddd; }
            .data-table thead th {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; background: #0B5ED7 !important; color: white !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
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
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacy Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($pharmacy['name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= $pharmacy['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($pharmacy['status']) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-pills"></i> <?= $pharmacy['total_medicines'] ?? 0 ?> Medicines
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="pharmacies.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PHARMACY INFO CARD -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> Location</p>
                <p class="detail-value"><?= htmlspecialchars($pharmacy['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($pharmacy['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($pharmacy['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Created</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($pharmacy['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Last Updated</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($pharmacy['updated_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Pharmacists</p>
                <p class="detail-value"><?= $pharmacy['active_pharmacists'] ?? 0 ?> Active / <?= $pharmacy['total_pharmacists'] ?? 0 ?> Total</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 STATISTICS CARDS - ALL BLUE BACKGROUND -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- Card 1: Total Medicines -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-pills"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Medicines</p>
                <p class="stat-value"><?= number_format($pharmacy['total_medicines'] ?? 0) ?></p>
                <p class="stat-sub">Active inventory items</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 2: Total Prescriptions -->
        <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Prescriptions</p>
                <p class="stat-value"><?= number_format($pharmacy['total_prescriptions'] ?? 0) ?></p>
                <p class="stat-sub">
                    <?= $pharmacy['pending_prescriptions'] ?? 0 ?> pending · 
                    <?= $pharmacy['dispensed_prescriptions'] ?? 0 ?> dispensed
                </p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 3: OTC Sales -->
        <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">OTC Sales</p>
                <p class="stat-value"><?= number_format($pharmacy['total_otc_sales'] ?? 0) ?></p>
                <p class="stat-sub">Over-the-counter sales</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 4: Total Revenue -->
        <a href="reports.php?branch=<?= $pharmacy['id'] ?>&type=pharmacy" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">
                    Rx: TSh <?= number_format($pharmacy['prescription_revenue'] ?? 0, 0) ?> · 
                    OTC: TSh <?= number_format($pharmacy['otc_revenue'] ?? 0, 0) ?>
                </p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 5: Out of Stock -->
        <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=outofstock" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Out of Stock</p>
                <p class="stat-value"><?= number_format($pharmacy['out_of_stock_items'] ?? 0) ?></p>
                <p class="stat-sub">Items with zero quantity</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 6: Low Stock -->
        <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=lowstock" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Low Stock</p>
                <p class="stat-value"><?= number_format($pharmacy['low_stock_items'] ?? 0) ?></p>
                <p class="stat-sub">Below reorder level</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 7: Expired Medicines -->
        <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=expired" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-skull"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Expired</p>
                <p class="stat-value"><?= number_format($pharmacy['expired_medicines'] ?? 0) ?></p>
                <p class="stat-sub">Past expiry date</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 8: Expiring Soon -->
        <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=expiring" class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-value"><?= number_format($pharmacy['expiring_soon_medicines'] ?? 0) ?></p>
                <p class="stat-sub">Next 30 days</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- STOCK ALERTS SUMMARY -->
    <!-- ================================================================ -->
    <?php 
        $has_alerts = ($pharmacy['out_of_stock_items'] ?? 0) > 0 || 
                      ($pharmacy['low_stock_items'] ?? 0) > 0 || 
                      ($pharmacy['expired_medicines'] ?? 0) > 0 || 
                      ($pharmacy['expiring_soon_medicines'] ?? 0) > 0;
    ?>
    
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-exclamation-triangle text-orange-500"></i>
                Stock Alerts Summary
            </h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-white/60">
                    <i class="far fa-clock mr-1"></i> <?= date('Y-m-d H:i') ?>
                </span>
                <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-boxes"></i> Manage Inventory
                </a>
            </div>
        </div>
        
        <div class="card-body p-4">
            <div class="alert-grid">
                
                <!-- 1. Out of Stock -->
                <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=outofstock" 
                   class="alert-card <?= ($pharmacy['out_of_stock_items'] ?? 0) > 0 ? 'alert-danger' : 'alert-neutral' ?>">
                    <div class="alert-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-number"><?= number_format($pharmacy['out_of_stock_items'] ?? 0) ?></div>
                        <div class="alert-label">Out of Stock</div>
                    </div>
                    <i class="fas fa-chevron-right alert-arrow"></i>
                </a>
                
                <!-- 2. Low Stock -->
                <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=lowstock" 
                   class="alert-card <?= ($pharmacy['low_stock_items'] ?? 0) > 0 ? 'alert-warning' : 'alert-neutral' ?>">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-number"><?= number_format($pharmacy['low_stock_items'] ?? 0) ?></div>
                        <div class="alert-label">Low Stock</div>
                    </div>
                    <i class="fas fa-chevron-right alert-arrow"></i>
                </a>
                
                <!-- 3. Expired -->
                <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=expired" 
                   class="alert-card <?= ($pharmacy['expired_medicines'] ?? 0) > 0 ? 'alert-danger' : 'alert-neutral' ?>">
                    <div class="alert-icon">
                        <i class="fas fa-skull"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-number"><?= number_format($pharmacy['expired_medicines'] ?? 0) ?></div>
                        <div class="alert-label">Expired</div>
                    </div>
                    <i class="fas fa-chevron-right alert-arrow"></i>
                </a>
                
                <!-- 4. Expiring Soon -->
                <a href="pharmacy_inventory.php?branch=<?= $pharmacy['id'] ?>&filter=expiring" 
                   class="alert-card <?= ($pharmacy['expiring_soon_medicines'] ?? 0) > 0 ? 'alert-orange' : 'alert-neutral' ?>">
                    <div class="alert-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-number"><?= number_format($pharmacy['expiring_soon_medicines'] ?? 0) ?></div>
                        <div class="alert-label">Expiring Soon (30 days)</div>
                    </div>
                    <i class="fas fa-chevron-right alert-arrow"></i>
                </a>
                
            </div>
            
            <!-- No alerts message -->
            <?php if (!$has_alerts): ?>
                <div class="text-center py-3 mt-2 text-green-600 dark:text-green-400">
                    <i class="fas fa-check-circle text-xl block mb-1"></i>
                    <p class="font-medium text-sm">All stock levels are healthy! No alerts to display.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions
            </h3>
            <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body overflow-x-auto">
            <?php if (count($recent_prescriptions) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_prescriptions as $rx): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($rx['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($rx['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($rx['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadge($rx['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($rx['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($rx['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($rx['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $rx['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="text-white/80 hover:text-white text-xs transition">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-prescription text-2xl block mb-2"></i>
                    <p>No prescriptions found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT INVENTORY ITEMS - IMPROVED TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-boxes"></i>
                Recent Inventory Updates
            </h3>
            <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body overflow-x-auto">
            <?php if (count($recent_inventory) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Reorder Level</th>
                            <th>Price</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_inventory as $item): 
                            $is_expired = !empty($item['expiry_date']) && strtotime($item['expiry_date']) < time();
                            $is_expiring_soon = !empty($item['expiry_date']) && strtotime($item['expiry_date']) > time() && strtotime($item['expiry_date']) < strtotime('+30 days');
                            $is_low_stock = ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) && ($item['quantity'] ?? 0) > 0;
                            $is_out_of_stock = ($item['quantity'] ?? 0) <= 0;
                            
                            if ($is_out_of_stock) $status_class = 'danger';
                            elseif ($is_expired) $status_class = 'danger';
                            elseif ($is_expiring_soon) $status_class = 'warning';
                            elseif ($is_low_stock) $status_class = 'warning';
                            else $status_class = 'success';
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td class="font-semibold <?= $is_out_of_stock ? 'text-red-600 dark:text-red-400' : ($is_low_stock ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400') ?>">
                                    <?= number_format($item['quantity'] ?? 0) ?>
                                </td>
                                <td><?= number_format($item['reorder_level'] ?? 0) ?></td>
                                <td>TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></td>
                                <td class="<?= $is_expired ? 'text-red-600 dark:text-red-400 font-bold' : ($is_expiring_soon ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400') ?>">
                                    <?= !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                                    <?php if ($is_expired): ?>
                                        <span class="text-red-600 dark:text-red-400 text-xs block">(Expired)</span>
                                    <?php elseif ($is_expiring_soon): ?>
                                        <span class="text-yellow-600 dark:text-yellow-400 text-xs block">(Soon)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?php if ($is_out_of_stock): ?>
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        <?php elseif ($is_expired): ?>
                                            <i class="fas fa-skull"></i> Expired
                                        <?php elseif ($is_expiring_soon): ?>
                                            <i class="fas fa-clock"></i> Expiring Soon
                                        <?php elseif ($is_low_stock): ?>
                                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                                        <?php else: ?>
                                            <i class="fas fa-check-circle"></i> In Stock
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="text-white/80 hover:text-white text-xs transition">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-boxes text-2xl block mb-2"></i>
                    <p>No inventory items found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT OTC SALES -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-shopping-cart"></i>
                Recent OTC Sales
            </h3>
            <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body overflow-x-auto">
            <?php if (count($recent_otc_sales) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Net Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td>TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                                <td class="font-medium">TSh <?= number_format($sale['net_amount'] ?? 0, 0) ?></td>
                                <td class="text-xs"><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadge($sale['payment_status'] ?? 'pending') ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="text-white/80 hover:text-white text-xs transition">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-shopping-cart text-2xl block mb-2"></i>
                    <p>No OTC sales found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PHARMACISTS LIST -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-md"></i>
                Pharmacists (<?= count($pharmacists) ?>)
            </h3>
            <a href="add_employee.php?branch=<?= $pharmacy['id'] ?>&role=pharmacy" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Pharmacist
            </a>
        </div>
        <div class="card-body overflow-x-auto">
            <?php if (count($pharmacists) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pharmacists as $pharmacist): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($pharmacist['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pharmacist['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pharmacist['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $pharmacist['status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($pharmacist['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $pharmacist['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="text-white/80 hover:text-white text-xs transition">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-user-md text-2xl block mb-2"></i>
                    <p>No pharmacists assigned to this branch</p>
                    <a href="add_employee.php?branch=<?= $pharmacy['id'] ?>&role=pharmacy" class="btn btn-primary mt-2">
                        <i class="fas fa-plus"></i> Add Pharmacist
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.4s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock"></i>
                Recent Activities
            </h3>
            <a href="system_logs.php?branch=<?= $pharmacy['id'] ?>" class="card-action">View All →</a>
        </div>
        <div class="card-body max-h-60 overflow-y-auto">
            <?php if (count($recent_activities) > 0): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-3 p-3 border-b border-white/5 hover:bg-white/5 transition">
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0 text-white">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-sm text-white"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p class="text-xs text-white/60"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p class="text-[10px] text-white/40 mt-0.5">
                                <?= isset($activity['created_at']) ? date('M d, Y h:i A', strtotime($activity['created_at'])) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-clock text-2xl block mb-2"></i>
                    <p>No activities found</p>
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
            Pharmacy Details - <?= htmlspecialchars($pharmacy['name']) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
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

    console.log('%c💊 Braick Dispensary - View Pharmacy (ALL BLUE CARDS)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Pharmacy: <?= htmlspecialchars($pharmacy['name']) ?> (ID: <?= $pharmacy['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c💊 Total Medicines: <?= number_format($pharmacy['total_medicines'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Total Prescriptions: <?= number_format($pharmacy['total_prescriptions'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Sales: <?= number_format($pharmacy['total_otc_sales'] ?? 0) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c✅ ALL 8 STAT CARDS HAVE BLUE BACKGROUND', 'font-size:13px; color:#34D399;');
    console.log('%c✅ IMPROVED TABLE DESIGN', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>