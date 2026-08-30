<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacy_revenue.php
// ADMIN - PHARMACY REVENUE DETAILS
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// GREEN THEME - WITH WHITE TEXT
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
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

// If no branch ID provided, use the user's branch
if ($branch_id <= 0) {
    $branch_id = $user_branch_id;
}

// ================================================================
// FETCH BRANCH DETAILS
// ================================================================
$branch = null;

try {
    $stmt = $db->prepare("
        SELECT 
            b.id,
            b.name,
            b.location,
            b.phone,
            b.email,
            b.logo,
            b.status,
            b.created_at,
            b.updated_at,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If branch not found, try to get from user session
    if (!$branch) {
        $stmt = $db->prepare("
            SELECT 
                b.id,
                b.name,
                b.location,
                b.phone,
                b.email,
                b.logo,
                b.status,
                b.created_at,
                b.updated_at,
                (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
                (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists
            FROM branches b
            WHERE b.id = ?
        ");
        $stmt->execute([$user_branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        $branch_id = $user_branch_id;
    }
    
    // If still no branch, create fallback
    if (!$branch) {
        $branch = [
            'id' => $user_branch_id,
            'name' => $user_branch_name ?? 'Dodoma',
            'location' => 'Dodoma City, Tanzania',
            'phone' => '+255 700 000 001',
            'email' => 'dodoma@braick.com',
            'status' => 'active',
            'active_pharmacists' => 0,
            'total_pharmacists' => 0
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching branch: " . $e->getMessage());
    $branch = [
        'id' => $user_branch_id,
        'name' => $user_branch_name ?? 'Dodoma',
        'location' => 'Dodoma City, Tanzania',
        'phone' => '+255 700 000 001',
        'email' => 'dodoma@braick.com',
        'status' => 'active',
        'active_pharmacists' => 0,
        'total_pharmacists' => 0
    ];
}

// ================================================================
// PHARMACY REVENUE QUERIES - USING EXISTING TABLES
// ================================================================

// 1. PRESCRIPTION REVENUE (from bills with medication items)
try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(bi.total_price), 0) as total_revenue,
            COUNT(DISTINCT bi.bill_id) as total_prescriptions,
            COALESCE(AVG(bi.total_price), 0) as avg_prescription,
            COALESCE(MAX(bi.total_price), 0) as max_prescription,
            COALESCE(MIN(bi.total_price), 0) as min_prescription
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication' 
        AND b.branch_id = ?
        AND b.status = 'paid'
        AND bi.status != 'cancelled'
    ");
    $stmt->execute([$branch_id]);
    $prescription_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescription_stats = ['total_revenue' => 0, 'total_prescriptions' => 0, 'avg_prescription' => 0, 'max_prescription' => 0, 'min_prescription' => 0];
}

// 2. OTC REVENUE (from otc_sales)
try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(*) as total_otc_sales,
            COALESCE(AVG(total_amount), 0) as avg_otc,
            COALESCE(MAX(total_amount), 0) as max_otc,
            COALESCE(MIN(total_amount), 0) as min_otc
        FROM otc_sales 
        WHERE branch_id = ? 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$branch_id]);
    $otc_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $otc_stats = ['total_revenue' => 0, 'total_otc_sales' => 0, 'avg_otc' => 0, 'max_otc' => 0, 'min_otc' => 0];
}

// 3. TOTAL PHARMACY REVENUE
$pharmacy_total = ($prescription_stats['total_revenue'] ?? 0) + ($otc_stats['total_revenue'] ?? 0);

// 4. MONTHLY PHARMACY REVENUE (Last 12 months) - from bills and otc_sales
$monthly_revenue = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%b %Y') as month_name,
            COALESCE(SUM(CASE WHEN source = 'prescription' THEN amount ELSE 0 END), 0) as prescribe_revenue,
            COALESCE(SUM(CASE WHEN source = 'otc' THEN amount ELSE 0 END), 0) as otc_revenue,
            COALESCE(SUM(amount), 0) as total_revenue
        FROM (
            SELECT 'prescription' as source, b.created_at, bi.total_price as amount
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.item_type = 'medication' 
            AND b.branch_id = ?
            AND b.status = 'paid'
            AND bi.status != 'cancelled'
            
            UNION ALL
            
            SELECT 'otc' as source, os.created_at, os.total_amount as amount
            FROM otc_sales os
            WHERE os.branch_id = ? 
            AND os.payment_status = 'paid'
        ) as combined
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
        ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
    ");
    $stmt->execute([$branch_id, $branch_id]);
    $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthly_revenue = [];
}

// 5. RECENT PRESCRIPTION SALES (from bills with medication items)
$recent_prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            b.bill_number as sale_number,
            b.total_amount,
            b.discount_amount,
            b.paid_amount as net_amount,
            b.payment_method,
            b.created_at,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as pharmacist_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND item_type = 'medication') as item_count
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.branch_id = ?
        AND b.status = 'paid'
        AND EXISTS (SELECT 1 FROM bill_items WHERE bill_id = b.id AND item_type = 'medication')
        ORDER BY b.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$branch_id]);
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_prescriptions = [];
}

// 6. RECENT OTC SALES
$recent_otc_sales = [];
try {
    $stmt = $db->prepare("
        SELECT 
            os.id,
            os.sale_number,
            os.customer_name,
            os.total_amount,
            os.discount_amount,
            os.total_amount as net_amount,
            os.payment_method,
            os.created_at,
            u.full_name as sold_by_name,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id) as item_count
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.branch_id = ?
        AND os.payment_status = 'paid'
        ORDER BY os.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$branch_id]);
    $recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_otc_sales = [];
}

// 7. TOP MEDICATIONS SOLD (from prescription_items)
$top_medications = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pi.medication_name,
            SUM(pi.quantity) as total_quantity,
            COUNT(pi.id) as total_prescriptions,
            COALESCE(AVG(pi.unit_price), 0) as avg_price,
            SUM(pi.total_price) as total_revenue
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.branch_id = ?
        AND p.status = 'dispensed'
        GROUP BY pi.medication_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $top_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_medications = [];
}

// 8. OTC TOP ITEMS
$top_otc_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            oi.item_name as medication_name,
            SUM(oi.quantity) as total_quantity,
            COUNT(oi.id) as total_sales,
            COALESCE(AVG(oi.unit_price), 0) as avg_price,
            SUM(oi.total_price) as total_revenue
        FROM otc_sale_items oi
        INNER JOIN otc_sales os ON oi.sale_id = os.id
        WHERE os.branch_id = ?
        AND os.payment_status = 'paid'
        GROUP BY oi.item_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $top_otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_otc_items = [];
}

// ================================================================
// FORMAT CURRENCY
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getPaymentMethodBadge($method) {
    $classes = [
        'cash' => 'success',
        'm-pesa' => 'info',
        'airtel_money' => 'info',
        'tigo_pesa' => 'info',
        'halopesa' => 'info',
        'bank' => 'purple',
        'card' => 'purple',
        'insurance' => 'teal',
        'other' => 'secondary'
    ];
    return $classes[$method] ?? 'secondary';
}

function getPaymentMethodIcon($method) {
    $icons = [
        'cash' => 'fa-money-bill-wave',
        'm-pesa' => 'fa-mobile-alt',
        'airtel_money' => 'fa-mobile-alt',
        'tigo_pesa' => 'fa-mobile-alt',
        'halopesa' => 'fa-mobile-alt',
        'bank' => 'fa-university',
        'card' => 'fa-credit-card',
        'insurance' => 'fa-shield-alt',
        'other' => 'fa-circle'
    ];
    return $icons[$method] ?? 'fa-circle';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Revenue - <?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            
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
            --teal-bg: #ECFDF5;
            
            --orange: #F59E0B;
            --orange-bg: #FFFBEB;
            
            --pink: #EC4899;
            --pink-bg: #FDF2F8;
            
            --indigo: #4F46E5;
            --indigo-bg: #EEF2FF;
            
            --blue: #0B5ED7;
            --blue-bg: #E8F0FE;
            
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
            --shadow-md: 0 8px 30px rgba(0,0,0,0.12);
            --shadow-lg: 0 15px 50px rgba(0,0,0,0.15);
            
            --bg-body: #F0FDF4;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #D1FAE5;
            --radius: 16px;
            --radius-lg: 24px;
            --table-hover: #ECFDF5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #34D399;
            --primary-dark: #059669;
            --primary-light: #6EE7B7;
            --primary-bg: #1A3A2A;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 8px 30px rgba(0,0,0,0.3);
            --shadow-lg: 0 15px 50px rgba(0,0,0,0.4);
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
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 32px 40px;
            margin-bottom: 32px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
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
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2.2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 1rem;
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
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
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
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.85rem;
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
           STATS CARDS - WITH WHITE TEXT
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 24px 28px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            color: white !important;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .stat-card .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .stat-card .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.1;
            color: white !important;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 4px 0 0 0;
            color: rgba(255,255,255,0.85) !important;
        }
        
        .stat-card .stat-sub {
            font-size: 0.7rem;
            margin: 4px 0 0 0;
            color: rgba(255,255,255,0.7) !important;
        }
        
        /* Card Colors with White Text */
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        
        .stat-card.purple .stat-icon { background: rgba(255,255,255,0.15) !important; }
        .stat-card.orange .stat-icon { background: rgba(255,255,255,0.15) !important; }
        .stat-card.green .stat-icon { background: rgba(255,255,255,0.15) !important; }
        .stat-card.teal .stat-icon { background: rgba(255,255,255,0.15) !important; }
        
        [data-theme="dark"] .stat-card.purple { background: linear-gradient(135deg, #6D28D9, #5B21B6); }
        [data-theme="dark"] .stat-card.orange { background: linear-gradient(135deg, #B45309, #92400E); }
        [data-theme="dark"] .stat-card.green { background: linear-gradient(135deg, #047857, #065F46); }
        [data-theme="dark"] .stat-card.teal { background: linear-gradient(135deg, #0F766E, #0D5E56); }
        
        /* ================================================================
           TABLE CONTAINER
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 28px;
        }
        
        .table-container .card-header {
            padding: 14px 24px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .card-header .card-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-container .card-header .card-title i {
            color: rgba(255,255,255,0.8);
        }
        
        .table-container .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .table-container .card-header .card-action:hover {
            color: white;
        }
        
        .table-container .card-body {
            padding: 20px 24px;
        }
        
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 12px 16px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        .badge-pink { background: #EC4899; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 28px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 20px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.4rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card .stat-number { font-size: 1.6rem; }
            .stat-card { padding: 16px 18px; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 8px 10px; }
            .table-container .card-header { padding: 12px 16px; }
            .table-container .card-body { padding: 12px 16px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .stat-card .stat-number { font-size: 1.8rem; }
            .data-table { font-size: 0.6rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }
        
        .stats-grid .stat-card {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }
        
        .stats-grid .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stats-grid .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stats-grid .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stats-grid .stat-card:nth-child(4) { animation-delay: 0.20s; }
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
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacy Revenue
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= ($branch['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($branch['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($pharmacy_total) ?> Total Revenue
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-prescription"></i> <?= $prescription_stats['total_prescriptions'] ?? 0 ?> Prescriptions
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-shopping-cart"></i> <?= $otc_stats['total_otc_sales'] ?? 0 ?> OTC Sales
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="cashier_dashboard.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="pharmacy_revenue_report.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-pdf"></i> Export Report
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - WITH WHITE TEXT -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <p class="stat-number"><?= formatCurrency($prescription_stats['total_revenue'] ?? 0) ?></p>
            <p class="stat-label">Prescription Revenue</p>
            <p class="stat-sub"><?= $prescription_stats['total_prescriptions'] ?? 0 ?> prescriptions • Avg: <?= formatCurrency($prescription_stats['avg_prescription'] ?? 0) ?></p>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <p class="stat-number"><?= formatCurrency($otc_stats['total_revenue'] ?? 0) ?></p>
            <p class="stat-label">OTC Revenue</p>
            <p class="stat-sub"><?= $otc_stats['total_otc_sales'] ?? 0 ?> OTC sales • Avg: <?= formatCurrency($otc_stats['avg_otc'] ?? 0) ?></p>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-number"><?= formatCurrency($pharmacy_total) ?></p>
            <p class="stat-label">Total Pharmacy Revenue</p>
            <p class="stat-sub"><?= ($prescription_stats['total_prescriptions'] ?? 0) + ($otc_stats['total_otc_sales'] ?? 0) ?> total transactions</p>
        </div>
        
        <div class="stat-card teal">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <p class="stat-number"><?= $branch['active_pharmacists'] ?? 0 ?>/<?= $branch['total_pharmacists'] ?? 0 ?></p>
            <p class="stat-label">Pharmacists</p>
            <p class="stat-sub">Active / Total</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- MONTHLY REVENUE CHART -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar"></i>
                Monthly Pharmacy Revenue (Last 12 Months)
            </h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TOP MEDICATIONS (from prescriptions) -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-pills"></i>
                Top Prescription Medications (<?= count($top_medications) ?>)
            </h3>
        </div>
        <?php if (count($top_medications) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication Name</th>
                            <th style="text-align:center;">Prescriptions</th>
                            <th style="text-align:center;">Total Qty</th>
                            <th style="text-align:right;">Avg Price</th>
                            <th style="text-align:right;">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($top_medications as $med): ?>
                            <tr>
                                <td class="font-semibold text-gray-400">#<?= $rank++ ?></td>
                                <td class="font-medium"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $med['total_prescriptions'] ?? 0 ?></td>
                                <td style="text-align:center;"><?= $med['total_quantity'] ?? 0 ?></td>
                                <td style="text-align:right;font-family:monospace;"><?= formatCurrency($med['avg_price'] ?? 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:700;color:#059669;"><?= formatCurrency($med['total_revenue'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-pills text-3xl block mb-3"></i>
                <p>No prescription medication data found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TOP OTC ITEMS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-capsules"></i>
                Top OTC Items (<?= count($top_otc_items) ?>)
            </h3>
        </div>
        <?php if (count($top_otc_items) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th style="text-align:center;">Sales</th>
                            <th style="text-align:center;">Total Qty</th>
                            <th style="text-align:right;">Avg Price</th>
                            <th style="text-align:right;">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($top_otc_items as $item): ?>
                            <tr>
                                <td class="font-semibold text-gray-400">#<?= $rank++ ?></td>
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $item['total_sales'] ?? 0 ?></td>
                                <td style="text-align:center;"><?= $item['total_quantity'] ?? 0 ?></td>
                                <td style="text-align:right;font-family:monospace;"><?= formatCurrency($item['avg_price'] ?? 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:700;color:#D97706;"><?= formatCurrency($item['total_revenue'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-capsules text-3xl block mb-3"></i>
                <p>No OTC item data found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.4s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions (<?= count($recent_prescriptions) ?>)
            </h3>
            <a href="prescriptions.php?branch=<?= $branch_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_prescriptions) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Net</th>
                            <th>Method</th>
                            <th>Pharmacist</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_prescriptions as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['patient_name'] ?? 'Walk-in') ?></td>
                                <td style="text-align:center;"><?= $sale['item_count'] ?? 0 ?></td>
                                <td class="font-semibold"><?= formatCurrency($sale['total_amount'] ?? 0) ?></td>
                                <td class="text-orange-600"><?= formatCurrency($sale['discount_amount'] ?? 0) ?></td>
                                <td class="font-semibold text-green-600"><?= formatCurrency($sale['net_amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getPaymentMethodBadge($sale['payment_method'] ?? 'cash') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getPaymentMethodIcon($sale['payment_method'] ?? 'cash') ?>"></i>
                                        <?= ucfirst($sale['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['pharmacist_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-prescription text-3xl block mb-3"></i>
                <p>No prescription sales found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT OTC SALES -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.45s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-shopping-cart"></i>
                Recent OTC Sales (<?= count($recent_otc_sales) ?>)
            </h3>
            <a href="otc_sales.php?branch=<?= $branch_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_otc_sales) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Net</th>
                            <th>Method</th>
                            <th>Sold By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td style="text-align:center;"><?= $sale['item_count'] ?? 0 ?></td>
                                <td class="font-semibold"><?= formatCurrency($sale['total_amount'] ?? 0) ?></td>
                                <td class="text-orange-600"><?= formatCurrency($sale['discount_amount'] ?? 0) ?></td>
                                <td class="font-semibold text-green-600"><?= formatCurrency($sale['net_amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getPaymentMethodBadge($sale['payment_method'] ?? 'cash') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getPaymentMethodIcon($sale['payment_method'] ?? 'cash') ?>"></i>
                                        <?= ucfirst($sale['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-shopping-cart text-3xl block mb-3"></i>
                <p>No OTC sales found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacy Revenue - <?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // MONTHLY REVENUE CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('monthlyRevenueChart');
        if (ctx) {
            var labels = [];
            var prescribeData = [];
            var otcData = [];
            
            <?php 
            $month_labels = [];
            $prescribe_values = [];
            $otc_values = [];
            foreach ($monthly_revenue as $month) {
                $month_labels[] = $month['month_name'] ?? 'N/A';
                $prescribe_values[] = (float)$month['prescribe_revenue'] ?? 0;
                $otc_values[] = (float)$month['otc_revenue'] ?? 0;
            }
            ?>
            
            labels = <?= json_encode($month_labels) ?>;
            prescribeData = <?= json_encode($prescribe_values) ?>;
            otcData = <?= json_encode($otc_values) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Prescription Revenue',
                            data: prescribeData,
                            backgroundColor: 'rgba(124, 58, 237, 0.7)',
                            borderColor: 'rgba(124, 58, 237, 1)',
                            borderWidth: 2,
                            borderRadius: 6,
                            barPercentage: 0.4
                        },
                        {
                            label: 'OTC Revenue',
                            data: otcData,
                            backgroundColor: 'rgba(217, 119, 6, 0.7)',
                            borderColor: 'rgba(217, 119, 6, 1)',
                            borderWidth: 2,
                            borderRadius: 6,
                            barPercentage: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 12,
                                    weight: '600'
                                },
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'rectRounded'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    });

    console.log('%c💊 Braick Dispensary - Pharmacy Revenue', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💵 Total Revenue: <?= formatCurrency($pharmacy_total) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💊 Prescriptions: <?= formatCurrency($prescription_stats['total_revenue'] ?? 0) ?> (<?= $prescription_stats['total_prescriptions'] ?? 0 ?> sales)', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Sales: <?= formatCurrency($otc_stats['total_revenue'] ?? 0) ?> (<?= $otc_stats['total_otc_sales'] ?? 0 ?> sales)', 'font-size:13px; color:#D97706;');
    console.log('%c📊 Tables: bills, bill_items, otc_sales, otc_sale_items, prescriptions, prescription_items', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Fixed: WHITE TEXT on all stat cards', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Fixed: Branch name shows correctly', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>