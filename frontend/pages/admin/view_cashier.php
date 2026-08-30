<?php
// ================================================================
// FILE: frontend/pages/admin/view_cashier.php
// ADMIN - VIEW CASHIER BRANCH DETAILS WITH REVENUE CARDS
// FIXED: Shows ALL cashiers + receptionists (since they handle cash)
// FIXED: Uses paid_amount from bills (includes discounts)
// FIXED: Excludes OTC bills (bill_number NOT LIKE 'BILL-OTC-%')
// FIXED: Only counts paid bills and paid bill_items
// FIXED: Prescription revenue from prescription_items table
// 6 CARDS ONLY: Total Revenue, Expenses, Net Profit, Patient Bills, OTC, Prescriptions
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
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID
// ================================================================
$cashier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

// If no ID provided, redirect with error
if ($cashier_id <= 0) {
    header('Location: cashiers.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH CASHIER BRANCH DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active') as active_cashiers,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier') as total_cashiers,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception' AND status = 'active') as active_receptions,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception') as total_receptions,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'pending' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as pending_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'partial' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as partial_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'paid' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as paid_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'cancelled' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as cancelled_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as total_bills,
            (SELECT COUNT(*) FROM payments WHERE branch_id = b.id) as total_payments,
            (SELECT COUNT(*) FROM payments WHERE branch_id = b.id AND DATE(received_at) = CURDATE()) as today_payments
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$cashier_id]);
    $cashier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cashier) {
        header('Location: cashiers.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching cashier: " . $e->getMessage());
    header('Location: cashiers.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// REVENUE QUERIES - FIXED: Using paid_amount, excludes OTC, only paid bills
// ================================================================

// 1. PATIENT BILLS REVENUE (From bills - paid, excludes OTC)
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(paid_amount), 0) as bills_revenue
        FROM bills 
        WHERE branch_id = ? 
        AND status = 'paid'
        AND patient_id IS NOT NULL
        AND visit_id IS NOT NULL
        AND bill_number NOT LIKE 'BILL-OTC-%'
    ");
    $stmt->execute([$cashier_id]);
    $bills_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['bills_revenue'] ?? 0;
} catch (Exception $e) {
    $bills_revenue = 0;
}

// 2. OTC REVENUE (from otc_sales table)
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as otc_revenue
        FROM otc_sales 
        WHERE branch_id = ? 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
} catch (Exception $e) {
    $otc_revenue = 0;
}

// 3. PRESCRIPTION REVENUE (from prescription_items table)
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(pi.total_price), 0) as prescription_revenue
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.branch_id = ? 
        AND p.status = 'dispensed'
    ");
    $stmt->execute([$cashier_id]);
    $prescription_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['prescription_revenue'] ?? 0;
} catch (Exception $e) {
    $prescription_revenue = 0;
}

// 4. TOTAL REVENUE = Patient Bills + OTC + Prescriptions
$total_revenue = $bills_revenue + $otc_revenue + $prescription_revenue;

// 5. EXPENSES (ONLY SELECTED BRANCH)
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
} catch (Exception $e) {
    $total_expenses = 0;
}

// 6. NET PROFIT = TOTAL REVENUE - EXPENSES
$net_profit = $total_revenue - $total_expenses;

// ================================================================
// GET STAFF FOR THIS BRANCH - CASHIERS + RECEPTIONISTS
// ================================================================
$staff_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, role, status, created_at 
        FROM users 
        WHERE branch_id = ? 
        AND role IN ('cashier', 'reception')
        ORDER BY role, full_name
    ");
    $stmt->execute([$cashier_id]);
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff_list = [];
}

// Separate counts for display
$cashier_count = 0;
$reception_count = 0;
foreach ($staff_list as $staff) {
    if ($staff['role'] === 'cashier') $cashier_count++;
    if ($staff['role'] === 'reception') $reception_count++;
}

// ================================================================
// GET RECENT PAYMENTS
// ================================================================
$recent_payments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.receipt_number,
            p.amount,
            p.payment_method,
            p.received_at,
            b.bill_number,
            pat.full_name as patient_name,
            u.full_name as received_by_name
        FROM payments p
        LEFT JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.received_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cashier_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_payments = [];
}

// ================================================================
// GET RECENT BILLS
// ================================================================
$recent_bills = [];
try {
    $stmt = $db->prepare("
        SELECT 
            b.id,
            b.bill_number,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.status,
            b.created_at,
            pat.full_name as patient_name
        FROM bills b
        LEFT JOIN patients pat ON b.patient_id = pat.id
        WHERE b.branch_id = ?
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cashier_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_bills = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getRoleBadge($role) {
    $badges = [
        'cashier' => '<span class="badge badge-info" style="font-size:0.55rem;padding:1px 10px;"><i class="fas fa-cash-register"></i> Cashier</span>',
        'reception' => '<span class="badge badge-teal" style="font-size:0.55rem;padding:1px 10px;"><i class="fas fa-headset"></i> Reception</span>'
    ];
    return $badges[$role] ?? '<span class="badge badge-secondary">' . ucfirst($role) . '</span>';
}

// ================================================================
// FORMAT CURRENCY
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';

// Sidebar stats
$total_employees_sidebar = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$total_doctors_sidebar = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$total_branches_sidebar = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Cashier - <?= htmlspecialchars($cashier['name'] ?? 'Branch') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
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
            
            --bg-body: #F0FDF4;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #D1FAE5;
            --radius: 12px;
            --radius-lg: 18px;
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
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
           TOP NAV - SHARED HEADER STYLES
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
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
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
           DETAIL CARD
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
           6 REVENUE CARDS - 3 per row
           ================================================================ */
        .revenue-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .revenue-card {
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: white;
            display: block;
        }
        
        .revenue-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .revenue-card::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .revenue-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.3);
        }
        
        .revenue-card .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-bottom: 8px;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .revenue-card .card-amount {
            font-size: 1.4rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }
        
        .revenue-card .card-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.7);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0;
        }
        
        .revenue-card .card-sub {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.6);
            margin: 0;
            opacity: 0.8;
        }
        
        .revenue-card .card-nav-arrow {
            position: absolute;
            bottom: 10px;
            right: 14px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            opacity: 0.5;
            transition: all 0.3s ease;
        }
        
        .revenue-card:hover .card-nav-arrow {
            opacity: 1;
            transform: translateX(4px);
            color: rgba(255,255,255,0.9);
        }
        
        /* ================================================================
           CARD COLOR CLASSES
           ================================================================ */
        .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-blue:hover { box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4); }
        
        .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .card-red:hover { box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4); }
        
        .card-green { background: linear-gradient(135deg, #059669, #047857); }
        .card-green:hover { box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4); }
        
        .card-teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .card-teal:hover { box-shadow: 0 8px 25px rgba(13, 148, 136, 0.4); }
        
        .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .card-purple:hover { box-shadow: 0 8px 25px rgba(124, 58, 237, 0.4); }
        
        .card-cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
        .card-cyan:hover { box-shadow: 0 8px 25px rgba(8, 145, 178, 0.4); }
        
        [data-theme="dark"] .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
        [data-theme="dark"] .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        [data-theme="dark"] .card-green { background: linear-gradient(135deg, #059669, #047857); }
        [data-theme="dark"] .card-teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        [data-theme="dark"] .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        [data-theme="dark"] .card-cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .table-container .card-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .card-header .card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-container .card-header .card-title i {
            color: rgba(255,255,255,0.8);
        }
        
        .table-container .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.65rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .table-container .card-header .card-action:hover {
            color: white;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 10px 14px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 8px 14px;
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
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
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
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .stat-mini {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
            display: block;
        }
        
        .stat-mini:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-mini .stat-label-mini {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }
        
        .stat-mini .stat-number-mini {
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .stat-mini .stat-number-mini.text-green-600 { color: #059669; }
        .stat-mini .stat-number-mini.text-yellow-600 { color: #D97706; }
        .stat-mini .stat-number-mini.text-purple-600 { color: #7C3AED; }
        .stat-mini .stat-number-mini.text-teal-600 { color: #0D9488; }
        .stat-mini .stat-number-mini.text-red-600 { color: #DC2626; }
        
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
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .revenue-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .revenue-grid { grid-template-columns: 1fr 1fr; }
            .detail-card { padding: 16px; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .revenue-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.55rem; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
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
        
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.info { background: #0B5ED7; }
        .toast-custom.warning { background: #D97706; }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .detail-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #059669 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
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
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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
    <!-- PAGE HEADER - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                Cashier Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($cashier['name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= ($cashier['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($cashier['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($total_revenue) ?> Revenue
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-file-invoice"></i> <?= $cashier['total_bills'] ?? 0 ?> Bills
                </span>
                <span class="header-badge" style="background:rgba(239,68,68,0.2);border-color:rgba(239,68,68,0.3);color:#F87171;">
                    <i class="fas fa-arrow-up"></i> Expenses: <?= formatCurrency($total_expenses) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-chart-line"></i> Profit: <?= formatCurrency($net_profit) ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="cashiers.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CASHIER INFO CARD -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> Location</p>
                <p class="detail-value"><?= htmlspecialchars($cashier['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($cashier['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($cashier['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-tie mr-1"></i> Staff</p>
                <p class="detail-value">
                    <span class="badge badge-info" style="font-size:0.6rem;padding:2px 10px;">
                        <i class="fas fa-cash-register"></i> <?= $cashier['active_cashiers'] ?? 0 ?> Cashiers
                    </span>
                    <span class="badge badge-teal" style="font-size:0.6rem;padding:2px 10px;background:#0D9488;">
                        <i class="fas fa-headset"></i> <?= $cashier['active_receptions'] ?? 0 ?> Reception
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 6 REVENUE CARDS - ONLY CARDS WITH VALUES -->
    <!-- ================================================================ -->
    <div class="revenue-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- 1. TOTAL REVENUE - BLUE -->
        <a href="revenue.php?branch=<?= $cashier_id ?>" class="revenue-card card-blue">
            <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="card-amount"><?= formatCurrency($total_revenue) ?></p>
            <p class="card-label">Total Revenue</p>
            <p class="card-sub">Bills + OTC + Prescriptions</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 2. EXPENSES - RED -->
        <a href="expenses.php?branch=<?= $cashier_id ?>" class="revenue-card card-red">
            <div class="card-icon"><i class="fas fa-arrow-up"></i></div>
            <p class="card-amount"><?= formatCurrency($total_expenses) ?></p>
            <p class="card-label">Total Expenses</p>
            <p class="card-sub">From expenses (paid)</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 3. NET PROFIT - GREEN -->
        <a href="profit.php?branch=<?= $cashier_id ?>" class="revenue-card card-green">
            <div class="card-icon"><i class="fas fa-chart-line"></i></div>
            <p class="card-amount"><?= formatCurrency($net_profit) ?></p>
            <p class="card-label">Net Profit</p>
            <p class="card-sub">Revenue - Expenses</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 4. BILLS REVENUE - BLUE -->
        <a href="bills.php?branch=<?= $cashier_id ?>&status=paid" class="revenue-card card-blue">
            <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
            <p class="card-amount"><?= formatCurrency($bills_revenue) ?></p>
            <p class="card-label">Patient Bills</p>
            <p class="card-sub">All paid patient bills</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 5. OTC REVENUE - TEAL -->
        <a href="otc_sales.php?branch=<?= $cashier_id ?>" class="revenue-card card-teal">
            <div class="card-icon"><i class="fas fa-cash-register"></i></div>
            <p class="card-amount"><?= formatCurrency($otc_revenue) ?></p>
            <p class="card-label">OTC Sales</p>
            <p class="card-sub">Over-the-counter sales</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 6. PRESCRIPTION REVENUE - PURPLE -->
        <a href="prescriptions.php?branch=<?= $cashier_id ?>" class="revenue-card card-purple">
            <div class="card-icon"><i class="fas fa-prescription"></i></div>
            <p class="card-amount"><?= formatCurrency($prescription_revenue) ?></p>
            <p class="card-label">Prescriptions</p>
            <p class="card-sub">From prescription_items</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- BILLS SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6 animate-fade-in-up" style="animation-delay:0.1s;">
        <a href="bills.php?branch=<?= $cashier_id ?>" class="stat-mini">
            <p class="stat-label-mini"><i class="fas fa-file-invoice mr-1"></i> Total Bills</p>
            <p class="stat-number-mini text-green-600"><?= number_format($cashier['total_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=pending" class="stat-mini">
            <p class="stat-label-mini"><i class="fas fa-clock mr-1"></i> Pending Bills</p>
            <p class="stat-number-mini text-yellow-600"><?= number_format($cashier['pending_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=partial" class="stat-mini">
            <p class="stat-label-mini"><i class="fas fa-hourglass-half mr-1"></i> Partial Bills</p>
            <p class="stat-number-mini text-purple-600"><?= number_format($cashier['partial_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=paid" class="stat-mini">
            <p class="stat-label-mini"><i class="fas fa-check-circle mr-1"></i> Paid Bills</p>
            <p class="stat-number-mini text-green-600"><?= number_format($cashier['paid_bills'] ?? 0) ?></p>
        </a>
        <a href="receipts.php?branch=<?= $cashier_id ?>" class="stat-mini">
            <p class="stat-label-mini"><i class="fas fa-receipt mr-1"></i> Receipts</p>
            <p class="stat-number-mini text-teal-600"><?= number_format($cashier['total_payments'] ?? 0) ?></p>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-credit-card"></i>
                Recent Payments (<?= count($recent_payments) ?>)
            </h3>
            <a href="payments.php?branch=<?= $cashier_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_payments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table" id="paymentsTable">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['patient_name'] ?? 'N/A') ?></td>
                                <td class="font-semibold text-green-600"><?= formatCurrency($payment['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:1px 8px;">
                                        <?= ucfirst($payment['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_payment.php?id=<?= $payment['id'] ?>&branch=<?= $cashier_id ?>" class="text-green-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-credit-card text-2xl block mb-2"></i>
                <p>No payments found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT BILLS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice"></i>
                Recent Bills (<?= count($recent_bills) ?>)
            </h3>
            <a href="bills.php?branch=<?= $cashier_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table" id="billsTable">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bills as $bill): 
                            $balance = (float)$bill['balance'];
                        ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-green-600">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                                <td class="font-semibold"><?= formatCurrency($bill['total_amount'] ?? 0) ?></td>
                                <td class="text-green-600"><?= formatCurrency($bill['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="text-red-600 font-semibold"><?= formatCurrency($balance) ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600"><?= formatCurrency(0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($bill['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $cashier_id ?>" class="text-green-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-file-invoice text-2xl block mb-2"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- STAFF LIST - CASHIERS + RECEPTIONISTS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users"></i>
                Staff (<?= count($staff_list) ?>)
            </h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                    <i class="fas fa-cash-register"></i> Cashiers: <?= $cashier_count ?>
                </span>
                <span class="badge badge-teal" style="font-size:0.55rem;padding:2px 10px;background:#0D9488;">
                    <i class="fas fa-headset"></i> Reception: <?= $reception_count ?>
                </span>
                <a href="add_employee.php?branch=<?= $cashier_id ?>" class="card-action" style="background:rgba(255,255,255,0.15);padding:2px 12px;border-radius:12px;">
                    <i class="fas fa-plus"></i> Add Staff
                </a>
            </div>
        </div>
        <?php if (count($staff_list) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table" id="staffTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_list as $staff): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($staff['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($staff['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></td>
                                <td><?= getRoleBadge($staff['role'] ?? 'other') ?></td>
                                <td>
                                    <span class="badge badge-<?= $staff['status'] === 'active' ? 'success' : 'danger' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($staff['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $staff['id'] ?>&branch=<?= $cashier_id ?>" class="text-green-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-users text-2xl block mb-2"></i>
                <p>No staff assigned to this branch</p>
                <a href="add_employee.php?branch=<?= $cashier_id ?>" class="text-green-600 text-sm hover:underline">Add Staff</a>
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
            Cashier Details - <?= htmlspecialchars($cashier['name'] ?? 'N/A') ?>
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
    // DARK MODE TOGGLE - FIXED
    // ================================================================
    (function() {
        var darkModeToggle = document.getElementById('darkModeToggle');
        var darkIcon = document.getElementById('darkIcon');
        var darkText = document.getElementById('darkText');
        var htmlElement = document.documentElement;
        
        var savedDarkMode = localStorage.getItem('darkMode');
        var cookieDarkMode = document.cookie.split('; ').find(function(row) {
            return row.startsWith('dark_mode=');
        });
        
        var isDark = false;
        if (savedDarkMode === 'true') {
            isDark = true;
        } else if (cookieDarkMode) {
            isDark = cookieDarkMode.split('=')[1] === 'true';
        }
        
        if (isDark) {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
        } else {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
        }
        
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                var isDarkNow = htmlElement.getAttribute('data-theme') === 'dark';
                
                if (isDarkNow) {
                    htmlElement.removeAttribute('data-theme');
                    if (darkIcon) darkIcon.className = 'fas fa-moon';
                    if (darkText) darkText.textContent = 'Dark';
                    localStorage.setItem('darkMode', 'false');
                    document.cookie = "dark_mode=false; path=/";
                } else {
                    htmlElement.setAttribute('data-theme', 'dark');
                    if (darkIcon) darkIcon.className = 'fas fa-sun';
                    if (darkText) darkText.textContent = 'Light';
                    localStorage.setItem('darkMode', 'true');
                    document.cookie = "dark_mode=true; path=/";
                }
            });
        }
    })();

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
    // SEARCH FUNCTIONALITY
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    function performSearch() {
        var query = searchInput.value.trim().toLowerCase();
        var tables = ['paymentsTable', 'billsTable', 'staffTable'];
        
        tables.forEach(function(tableId) {
            var table = document.getElementById(tableId);
            if (!table) return;
            
            var rows = table.getElementsByTagName('tbody')[0]?.getElementsByTagName('tr');
            if (!rows) return;
            
            var visibleCount = 0;
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var text = row.textContent.toLowerCase();
                if (query === '' || text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }
            
            var noResults = table.parentElement.querySelector('.no-results');
            if (visibleCount === 0 && query !== '') {
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.className = 'no-results text-center py-4 text-gray-400';
                    noResults.innerHTML = '<i class="fas fa-search text-2xl block mb-2"></i><p>No results found for "<strong>' + query + '</strong>"</p>';
                    table.parentElement.appendChild(noResults);
                } else {
                    noResults.style.display = 'block';
                    noResults.innerHTML = '<i class="fas fa-search text-2xl block mb-2"></i><p>No results found for "<strong>' + query + '</strong>"</p>';
                }
            } else if (noResults) {
                noResults.style.display = 'none';
            }
        });
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });
    
    searchInput?.addEventListener('input', performSearch);

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // CLOCK - UPDATE EVERY SECOND
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

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
    // TOAST NOTIFICATION
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 4000);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            performSearch();
            searchInput.blur();
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c💰 Braick Dispensary - View Cashier (6 Cards)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($cashier['name'] ?? 'N/A') ?> (ID: <?= $cashier_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📊 6 Revenue Cards:', 'font-size:13px; font-weight:bold; color:#0B5ED7;');
    console.log('%c   ├─ Total Revenue: <?= formatCurrency($total_revenue) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c   ├─ Expenses: <?= formatCurrency($total_expenses) ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c   ├─ Net Profit: <?= formatCurrency($net_profit) ?>', 'font-size:12px; color:#059669;');
    console.log('%c   ├─ Patient Bills: <?= formatCurrency($bills_revenue) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c   ├─ OTC Sales: <?= formatCurrency($otc_revenue) ?>', 'font-size:12px; color:#0D9488;');
    console.log('%c   └─ Prescriptions: <?= formatCurrency($prescription_revenue) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c📄 Bills: <?= $cashier['paid_bills'] ?? 0 ?> Paid, <?= $cashier['pending_bills'] ?? 0 ?> Pending', 'font-size:13px; color:#D97706;');
    console.log('%c👥 Staff: <?= count($staff_list) ?> total (Cashiers: <?= $cashier_count ?>, Reception: <?= $reception_count ?>)', 'font-size:13px; color:#4F46E5;');
    console.log('%c✅ 6 CARDS ONLY - Removed: Procedures, Lab Tests, Consultation', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark Mode: WORKING | 🕐 Clock: WORKING', 'font-size:13px; color:#3B82F6;');
</script>

</body>
</html>