<?php
// ================================================================
// FILE: frontend/pages/admin/cashier_dashboard.php
// ADMIN - CASHIER DASHBOARD WITH 8 CARDS (FULL SIZE)
// BRAICK DISPENSARY - MODERN DESIGN
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
$cashier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

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
            (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'pending') as pending_bills,
            (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'partial') as partial_bills,
            (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'paid') as paid_bills,
            (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'cancelled') as cancelled_bills,
            (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id) as total_bills,
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
// REVENUE QUERIES
// ================================================================

// 1. TOTAL REVENUE - All payments
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_revenue
        FROM payments 
        WHERE branch_id = ?
    ");
    $stmt->execute([$cashier_id]);
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
} catch (Exception $e) {
    $total_revenue = 0;
}

// 2. PHARMACY REVENUE (Prescribe + OTC)
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as prescribe_revenue
        FROM prescription_sales 
        WHERE branch_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $prescribe_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['prescribe_revenue'] ?? 0;
} catch (Exception $e) {
    $prescribe_revenue = 0;
}

try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_amount), 0) as otc_revenue
        FROM otc_sales 
        WHERE branch_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
} catch (Exception $e) {
    $otc_revenue = 0;
}

$pharmacy_total = $prescribe_revenue + $otc_revenue;

// 3. LAB REVENUE - FROM BOTH lab_requests AND lab_tests
$lab_revenue = 0;
$lab_tests_count = 0;

try {
    // Get revenue from lab_requests table
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(lab_total), 0) as lab_revenue_requests
        FROM lab_requests 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$cashier_id]);
    $lab_revenue_requests = $stmt->fetch(PDO::FETCH_ASSOC)['lab_revenue_requests'] ?? 0;
    
    // Get revenue from lab_tests table
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(test_price), 0) as lab_revenue_tests
        FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$cashier_id]);
    $lab_revenue_tests = $stmt->fetch(PDO::FETCH_ASSOC)['lab_revenue_tests'] ?? 0;
    
    // Total lab revenue = sum of both tables
    $lab_revenue = $lab_revenue_requests + $lab_revenue_tests;
    
    // Get count of lab tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$cashier_id]);
    $lab_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $lab_revenue = 0;
    $lab_tests_count = 0;
}

// 4. PROCEDURES REVENUE
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as procedures_revenue
        FROM bill_items bi
        INNER JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.branch_id = ? AND bi.item_type = 'procedure' AND bi.payment_status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $procedures_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['procedures_revenue'] ?? 0;
} catch (Exception $e) {
    $procedures_revenue = 0;
}

// 5. TOOLS REVENUE
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as tools_revenue
        FROM bill_items bi
        INNER JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.branch_id = ? AND bi.item_type = 'tool' AND bi.payment_status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $tools_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['tools_revenue'] ?? 0;
} catch (Exception $e) {
    $tools_revenue = 0;
}

// 6. PROCEDURES + TOOLS TOTAL
$procedures_tools_total = $procedures_revenue + $tools_revenue;

// 7. OTHER SERVICES (Registration + Consultation fees from visits)
try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(registration_fee), 0) as registration_revenue,
            COALESCE(SUM(consultation_fee), 0) as consultation_revenue
        FROM visits 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$cashier_id]);
    $visit_fees = $stmt->fetch(PDO::FETCH_ASSOC);
    $registration_revenue = $visit_fees['registration_revenue'] ?? 0;
    $consultation_revenue = $visit_fees['consultation_revenue'] ?? 0;
} catch (Exception $e) {
    $registration_revenue = 0;
    $consultation_revenue = 0;
}

$other_services_total = $registration_revenue + $consultation_revenue;

// 8. GRAND TOTAL REVENUE (All sources combined)
$grand_total_revenue = $pharmacy_total + $lab_revenue + $procedures_tools_total + $other_services_total;

// ================================================================
// EXPENSES QUERIES
// ================================================================

// 9. TOTAL EXPENSES
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

// 10. PENDING EXPENSES
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as pending_expenses
        FROM expenses 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$cashier_id]);
    $pending_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['pending_expenses'] ?? 0;
} catch (Exception $e) {
    $pending_expenses = 0;
}

// 11. EXPENSE COUNT
$expense_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM expenses 
        WHERE branch_id = ?
    ");
    $stmt->execute([$cashier_id]);
    $expense_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $expense_count = 0;
}

// ================================================================
// PROFIT CALCULATIONS
// ================================================================
$net_profit = $grand_total_revenue - $total_expenses;
$profit_margin = $grand_total_revenue > 0 ? ($net_profit / $grand_total_revenue) * 100 : 0;

// ================================================================
// GET WEEKLY REVENUE (Last 7 days)
// ================================================================
$weekly_revenue = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE(received_at) as date,
            COALESCE(SUM(amount), 0) as total
        FROM payments 
        WHERE branch_id = ? AND received_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(received_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$cashier_id]);
    $weekly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $weekly_revenue = [];
}

// ================================================================
// GET WEEKLY EXPENSES (Last 7 days)
// ================================================================
$weekly_expenses = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE(payment_date) as date,
            COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$cashier_id]);
    $weekly_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $weekly_expenses = [];
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
            pb.bill_number,
            pat.full_name as patient_name,
            u.full_name as received_by_name
        FROM payments p
        LEFT JOIN patient_bills pb ON p.bill_id = pb.id
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
            pb.id,
            pb.bill_number,
            pb.total_amount,
            pb.paid_amount,
            pb.balance,
            pb.status,
            pb.created_at,
            pat.full_name as patient_name
        FROM patient_bills pb
        LEFT JOIN patients pat ON pb.patient_id = pat.id
        WHERE pb.branch_id = ?
        ORDER BY pb.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cashier_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_bills = [];
}

// ================================================================
// GET RECENT EXPENSES
// ================================================================
$recent_expenses = [];
try {
    $stmt = $db->prepare("
        SELECT 
            e.id,
            e.expense_number,
            e.category,
            e.description,
            e.amount,
            e.payment_method,
            e.payment_date,
            e.status,
            e.created_at,
            u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.branch_id = ?
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cashier_id]);
    $recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_expenses = [];
}

// ================================================================
// GET CASHIERS FOR THIS BRANCH
// ================================================================
$cashiers = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, status, created_at 
        FROM users 
        WHERE branch_id = ? AND role = 'cashier'
        ORDER BY full_name
    ");
    $stmt->execute([$cashier_id]);
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cashiers = [];
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
        'cancelled' => 'danger',
        'approved' => 'success'
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
        'cancelled' => 'fa-times-circle',
        'approved' => 'fa-check-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
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
    <title>Cashier Dashboard - <?= htmlspecialchars($cashier['name']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME
           ================================================================ */
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
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
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
           MAIN CARDS GRID - 8 CARDS (FULL SIZE)
           ================================================================ */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        /* ================================================================
           CARD STYLES - MODERN & BEAUTIFUL (FULL SIZE)
           ================================================================ */
        .dashboard-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: var(--text-primary);
            overflow: hidden;
            position: relative;
            min-height: 180px;
        }
        
        .dashboard-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-accent, var(--primary));
            opacity: 0.8;
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--card-accent, var(--primary));
        }
        
        .dashboard-card:hover::after {
            height: 6px;
            opacity: 1;
        }
        
        /* Card Header - Gradient Background */
        .dashboard-card .card-header-bg {
            padding: 18px 24px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            min-height: 72px;
        }
        
        .dashboard-card .card-header-bg .card-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.15);
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover .card-header-bg .card-icon {
            transform: scale(1.08);
            background: rgba(255,255,255,0.3);
        }
        
        .dashboard-card .card-header-bg .card-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0;
            color: rgba(255,255,255,0.9);
            line-height: 1.3;
        }
        
        .dashboard-card .card-header-bg .card-label small {
            display: block;
            font-weight: 400;
            text-transform: none;
            opacity: 0.7;
            font-size: 0.6rem;
            letter-spacing: 0.02em;
            margin-top: 2px;
        }
        
        /* Card Body */
        .dashboard-card .card-body {
            padding: 20px 24px 22px;
            background: var(--bg-card);
        }
        
        .dashboard-card .card-body .card-amount {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
            font-family: 'Inter', monospace;
            letter-spacing: -0.02em;
        }
        
        .dashboard-card .card-body .card-amount .currency {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .dashboard-card .card-body .card-amount.green { color: var(--primary); }
        .dashboard-card .card-body .card-amount.blue { color: #0B5ED7; }
        .dashboard-card .card-body .card-amount.purple { color: #7C3AED; }
        .dashboard-card .card-body .card-amount.teal { color: #0D9488; }
        .dashboard-card .card-body .card-amount.orange { color: #D97706; }
        .dashboard-card .card-body .card-amount.pink { color: #EC4899; }
        .dashboard-card .card-body .card-amount.red { color: #DC2626; }
        .dashboard-card .card-body .card-amount.indigo { color: #4F46E5; }
        
        .dashboard-card .card-body .card-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 8px 0 0 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .dashboard-card .card-body .card-sub .highlight {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .dashboard-card .card-body .card-sub .badge-mini {
            font-size: 0.6rem;
            padding: 3px 10px;
            border-radius: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .dashboard-card .card-body .card-sub .badge-mini.green { background: var(--success-bg); color: var(--success); }
        .dashboard-card .card-body .card-sub .badge-mini.red { background: var(--danger-bg); color: var(--danger); }
        .dashboard-card .card-body .card-sub .badge-mini.orange { background: var(--warning-bg); color: var(--warning); }
        .dashboard-card .card-body .card-sub .badge-mini.purple { background: var(--purple-bg); color: var(--purple); }
        .dashboard-card .card-body .card-sub .badge-mini.teal { background: var(--teal-bg); color: var(--teal); }
        .dashboard-card .card-body .card-sub .badge-mini.blue { background: var(--blue-bg); color: var(--blue); }
        .dashboard-card .card-body .card-sub .badge-mini.pink { background: var(--pink-bg); color: var(--pink); }
        
        /* Card Footer - Arrow */
        .dashboard-card .card-footer {
            padding: 8px 24px 12px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-body);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
        }
        
        .dashboard-card .card-footer .card-nav-arrow {
            font-size: 0.75rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dashboard-card .card-footer .card-nav-arrow .arrow-text {
            font-size: 0.65rem;
            font-weight: 500;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover .card-footer .card-nav-arrow {
            color: var(--card-accent, var(--primary));
        }
        
        .dashboard-card:hover .card-footer .card-nav-arrow .arrow-text {
            opacity: 1;
        }
        
        .dashboard-card:hover .card-footer .card-nav-arrow i {
            transform: translateX(5px);
        }
        
        /* Card Header Colors */
        .card-header-bg.green { background: linear-gradient(135deg, #059669, #047857); --card-accent: #059669; }
        .card-header-bg.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); --card-accent: #0B5ED7; }
        .card-header-bg.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); --card-accent: #7C3AED; }
        .card-header-bg.teal { background: linear-gradient(135deg, #0D9488, #0F766E); --card-accent: #0D9488; }
        .card-header-bg.orange { background: linear-gradient(135deg, #D97706, #B45309); --card-accent: #D97706; }
        .card-header-bg.pink { background: linear-gradient(135deg, #EC4899, #BE185D); --card-accent: #EC4899; }
        .card-header-bg.red { background: linear-gradient(135deg, #DC2626, #B91C1C); --card-accent: #DC2626; }
        .card-header-bg.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); --card-accent: #4F46E5; }
        
        [data-theme="dark"] .dashboard-card .card-body {
            background: var(--bg-card);
        }
        
        [data-theme="dark"] .dashboard-card .card-footer {
            background: var(--bg-body);
        }
        
        /* ================================================================
           CHART CONTAINER
           ================================================================ */
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
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
        
        /* ================================================================
           BADGES
           ================================================================ */
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
        
        /* ================================================================
           FOOTER
           ================================================================ */
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 20px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .cards-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.4rem; }
            .cards-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
            .dashboard-card .card-body .card-amount { font-size: 1.6rem; }
            .dashboard-card .card-header-bg { padding: 14px 16px 12px; min-height: 60px; }
            .dashboard-card .card-header-bg .card-icon { width: 42px; height: 42px; font-size: 1.1rem; }
            .dashboard-card .card-body { padding: 14px 16px 16px; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 8px 10px; }
            .table-container .card-header { padding: 12px 16px; }
            .table-container .card-body { padding: 12px 16px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .cards-grid { grid-template-columns: 1fr; gap: 14px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .dashboard-card .card-body .card-amount { font-size: 1.8rem; }
            .data-table { font-size: 0.6rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }
        
        .cards-grid .dashboard-card {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }
        
        .cards-grid .dashboard-card:nth-child(1) { animation-delay: 0.05s; }
        .cards-grid .dashboard-card:nth-child(2) { animation-delay: 0.10s; }
        .cards-grid .dashboard-card:nth-child(3) { animation-delay: 0.15s; }
        .cards-grid .dashboard-card:nth-child(4) { animation-delay: 0.20s; }
        .cards-grid .dashboard-card:nth-child(5) { animation-delay: 0.25s; }
        .cards-grid .dashboard-card:nth-child(6) { animation-delay: 0.30s; }
        .cards-grid .dashboard-card:nth-child(7) { animation-delay: 0.35s; }
        .cards-grid .dashboard-card:nth-child(8) { animation-delay: 0.40s; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 14px;
            z-index: 999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 14px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
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
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .dashboard-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
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
    <!-- PAGE HEADER - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                Cashier Dashboard
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($cashier['name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= $cashier['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($cashier['status']) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($grand_total_revenue) ?> Revenue
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-file-invoice"></i> <?= $cashier['total_bills'] ?? 0 ?> Bills
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="view_cashier.php?id=<?= $cashier_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="cashier_reports.php?id=<?= $cashier_id ?>" class="btn-outline-light">
                <i class="fas fa-file-alt"></i> Reports
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 CARDS GRID - MODERN & BEAUTIFUL (FULL SIZE) -->
    <!-- ================================================================ -->
    <div class="cards-grid">
        
        <!-- 1. TOTAL REVENUE -->
        <a href="payments.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg green">
                <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                <p class="card-label">Total Revenue <small>All payments combined</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount green"><span class="currency">TSh</span> <?= number_format($grand_total_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini green"><i class="fas fa-arrow-up"></i> +<?= number_format($profit_margin, 1) ?>%</span>
                    <?= $cashier['total_payments'] ?? 0 ?> transactions
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 2. EXPENSES -->
        <a href="expenses.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg red">
                <div class="card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <p class="card-label">Expenses <small>Total paid expenses</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount red"><span class="currency">TSh</span> <?= number_format($total_expenses, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini orange"><i class="fas fa-clock"></i> <?= formatCurrency($pending_expenses) ?> pending</span>
                    <?= $expense_count ?> transactions
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 3. NET PROFIT -->
        <a href="profit.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg <?= $net_profit >= 0 ? 'green' : 'red' ?>">
                <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                <p class="card-label">Net Profit <small>Revenue - Expenses</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount <?= $net_profit >= 0 ? 'green' : 'red' ?>">
                    <span class="currency">TSh</span> <?= number_format($net_profit, 0) ?>
                </p>
                <p class="card-sub">
                    <span class="badge-mini <?= $net_profit >= 0 ? 'green' : 'red' ?>">
                        <i class="fas <?= $net_profit >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                        <?= number_format($profit_margin, 1) ?>% margin
                    </span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 4. PHARMACY REVENUE -->
        <a href="pharmacy_revenue.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg purple">
                <div class="card-icon"><i class="fas fa-prescription-bottle"></i></div>
                <p class="card-label">Pharmacy Revenue <small>Prescribe + OTC</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount purple"><span class="currency">TSh</span> <?= number_format($pharmacy_total, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini purple">💊 <?= formatCurrency($prescribe_revenue) ?></span>
                    <span class="badge-mini orange">🛒 <?= formatCurrency($otc_revenue) ?></span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 5. LAB REVENUE (From both lab_requests AND lab_tests) -->
        <a href="lab_revenue.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg teal">
                <div class="card-icon"><i class="fas fa-flask"></i></div>
                <p class="card-label">Lab Revenue <small>Completed tests</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount teal"><span class="currency">TSh</span> <?= number_format($lab_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini teal"><i class="fas fa-microscope"></i> <?= $lab_tests_count ?> tests</span>
                    <span class="badge-mini blue">📋 lab_requests + lab_tests</span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 6. PROCEDURES & TOOLS REVENUE -->
        <a href="procedures_tools_revenue.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg indigo">
                <div class="card-icon"><i class="fas fa-toolbox"></i></div>
                <p class="card-label">Procedures &amp; Tools <small>Combined revenue</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount indigo"><span class="currency">TSh</span> <?= number_format($procedures_tools_total, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini pink">🔧 <?= formatCurrency($procedures_revenue) ?></span>
                    <span class="badge-mini orange">🛠️ <?= formatCurrency($tools_revenue) ?></span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 7. OTHER SERVICES REVENUE -->
        <a href="other_services_revenue.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg blue">
                <div class="card-icon"><i class="fas fa-file-medical"></i></div>
                <p class="card-label">Other Services <small>Registration + Consultation</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount blue"><span class="currency">TSh</span> <?= number_format($other_services_total, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini blue">📋 <?= formatCurrency($registration_revenue) ?></span>
                    <span class="badge-mini purple">🩺 <?= formatCurrency($consultation_revenue) ?></span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
        <!-- 8. BILLS OVERVIEW -->
        <a href="bills.php?branch=<?= $cashier_id ?>" class="dashboard-card">
            <div class="card-header-bg orange">
                <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
                <p class="card-label">Bills Overview <small>Total bills</small></p>
            </div>
            <div class="card-body">
                <p class="card-amount orange"><span class="currency">TSh</span> <?= number_format($grand_total_revenue, 0) ?></p>
                <p class="card-sub">
                    <span class="badge-mini green">✅ <?= $cashier['paid_bills'] ?? 0 ?> paid</span>
                    <span class="badge-mini orange">⏳ <?= $cashier['pending_bills'] ?? 0 ?> pending</span>
                    <span class="badge-mini red">⚠️ <?= $cashier['partial_bills'] ?? 0 ?> partial</span>
                </p>
            </div>
            <div class="card-footer">
                <span class="card-nav-arrow">
                    <span class="arrow-text">View Details</span>
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS: REVENUE & EXPENSES -->
    <!-- ================================================================ -->
    <div class="chart-grid animate-fade-in-up" style="animation-delay:0.45s;">
        
        <!-- Weekly Revenue Chart -->
        <div class="table-container" style="margin-bottom:0;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Weekly Revenue (Last 7 Days)
                </h3>
                <a href="revenue_reports.php?branch=<?= $cashier_id ?>" class="card-action">View Reports →</a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="weeklyRevenueChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Weekly Expenses Chart -->
        <div class="table-container" style="margin-bottom:0;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Weekly Expenses (Last 7 Days)
                </h3>
                <a href="expense_reports.php?branch=<?= $cashier_id ?>" class="card-action">View Reports →</a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="weeklyExpensesChart"></canvas>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.5s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-credit-card"></i>
                Recent Payments (<?= count($recent_payments) ?>)
            </h3>
            <a href="payments.php?branch=<?= $cashier_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_payments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
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
                                    <span class="badge badge-info" style="font-size:0.6rem;padding:2px 10px;">
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
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-credit-card text-3xl block mb-3"></i>
                <p>No payments found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT EXPENSES -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.55s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice-dollar"></i>
                Recent Expenses (<?= count($recent_expenses) ?>)
            </h3>
            <a href="expenses.php?branch=<?= $cashier_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_expenses) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Expense #</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_expenses as $expense): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-secondary" style="font-size:0.6rem;padding:2px 10px;"><?= htmlspecialchars($expense['category'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars(substr($expense['description'] ?? 'N/A', 0, 40)) ?></td>
                                <td class="font-semibold text-red-600"><?= formatCurrency($expense['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($expense['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($expense['status'] ?? 'pending') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($expense['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($expense['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($expense['payment_date'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_expense.php?id=<?= $expense['id'] ?>&branch=<?= $cashier_id ?>" class="text-green-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-file-invoice-dollar text-3xl block mb-3"></i>
                <p>No expenses found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT BILLS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.6s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice"></i>
                Recent Bills (<?= count($recent_bills) ?>)
            </h3>
            <a href="bills.php?branch=<?= $cashier_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($recent_bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
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
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-file-invoice text-3xl block mb-3"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- CASHIERS LIST -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.65s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie"></i>
                Cashiers (<?= count($cashiers) ?>)
            </h3>
            <a href="add_employee.php?branch=<?= $cashier_id ?>&role=cashier" class="card-action">
                <i class="fas fa-plus"></i> Add Cashier
            </a>
        </div>
        <?php if (count($cashiers) > 0): ?>
            <div class="overflow-x-auto">
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
                        <?php foreach ($cashiers as $cashier_user): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($cashier_user['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($cashier_user['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($cashier_user['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $cashier_user['status'] === 'active' ? 'success' : 'danger' ?>" style="font-size:0.65rem;padding:2px 12px;">
                                        <?= ucfirst($cashier_user['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $cashier_user['id'] ?>&branch=<?= $cashier_id ?>" class="text-green-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-user-tie text-3xl block mb-3"></i>
                <p>No cashiers assigned to this branch</p>
                <a href="add_employee.php?branch=<?= $cashier_id ?>&role=cashier" class="text-green-600 text-sm hover:underline">Add Cashier</a>
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
            Cashier Dashboard - <?= htmlspecialchars($cashier['name']) ?>
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
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.9rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.8rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('branch_id');
        window.location.href = url.toString();
    }

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
    // WEEKLY CHARTS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        
        // Revenue Chart
        var revenueCtx = document.getElementById('weeklyRevenueChart');
        if (revenueCtx) {
            var labels = [];
            var revenueData = [];
            
            <?php 
            $week_dates = [];
            $week_revenue_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $week_dates[] = date('D', strtotime($date));
                $found = false;
                foreach ($weekly_revenue as $w) {
                    if ($w['date'] == $date) {
                        $week_revenue_data[] = (float)$w['total'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) $week_revenue_data[] = 0;
            }
            ?>
            
            labels = <?= json_encode($week_dates) ?>;
            revenueData = <?= json_encode($week_revenue_data) ?>;
            
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (TSh)',
                        data: revenueData,
                        backgroundColor: 'rgba(5, 150, 105, 0.7)',
                        borderColor: 'rgba(5, 150, 105, 1)',
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'TSh ' + context.raw.toLocaleString();
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
        
        // Expenses Chart
        var expensesCtx = document.getElementById('weeklyExpensesChart');
        if (expensesCtx) {
            var labels2 = [];
            var expensesData = [];
            
            <?php 
            $week_expense_dates = [];
            $week_expense_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $week_expense_dates[] = date('D', strtotime($date));
                $found = false;
                foreach ($weekly_expenses as $w) {
                    if ($w['date'] == $date) {
                        $week_expense_data[] = (float)$w['total'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) $week_expense_data[] = 0;
            }
            ?>
            
            labels2 = <?= json_encode($week_expense_dates) ?>;
            expensesData = <?= json_encode($week_expense_data) ?>;
            
            new Chart(expensesCtx, {
                type: 'bar',
                data: {
                    labels: labels2,
                    datasets: [{
                        label: 'Expenses (TSh)',
                        data: expensesData,
                        backgroundColor: 'rgba(220, 38, 38, 0.7)',
                        borderColor: 'rgba(220, 38, 38, 1)',
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'TSh ' + context.raw.toLocaleString();
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

    console.log('%c💰 Braick Dispensary - Cashier Dashboard (FULL SIZE)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($cashier['name']) ?> (ID: <?= $cashier_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c💵 Total Revenue: <?= formatCurrency($grand_total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Total Expenses: <?= formatCurrency($total_expenses) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📈 Net Profit: <?= formatCurrency($net_profit) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Profit Margin: <?= number_format($profit_margin, 1) ?>%', 'font-size:13px; color:#64748B;');
    console.log('%c🔬 Lab Revenue: FROM lab_requests + lab_tests', 'font-size:13px; color:#0D9488;');
    console.log('%c✅ 8 FULL SIZE Beautiful Cards', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Revenue & Expenses Charts', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>