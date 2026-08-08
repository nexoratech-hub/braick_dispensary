<?php
// ================================================================
// FILE: frontend/pages/admin/cashier_dashboard.php
// ADMIN - CASHIER DASHBOARD
// BRAICK DISPENSARY - GREEN THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
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
    // Prescription Revenue
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
    // OTC Revenue
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

// 3. LAB REVENUE - All lab tests
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(test_price), 0) as lab_revenue
        FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$cashier_id]);
    $lab_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['lab_revenue'] ?? 0;
} catch (Exception $e) {
    $lab_revenue = 0;
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

// ================================================================
// FORMAT CURRENCY
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// LOGO PATH
// ================================================================
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
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient-strong);
            border-radius: 0 3px 3px 0;
            opacity: 0.8;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }
        
        .stat-card .stat-number.green { color: var(--primary); }
        .stat-card .stat-number.orange { color: #F59E0B; }
        .stat-card .stat-number.purple { color: #7C3AED; }
        .stat-card .stat-number.teal { color: #0D9488; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.blue { color: #0B5ED7; }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-icon-small {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .stat-card .stat-icon-small.green { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon-small.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-card .stat-icon-small.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-card .stat-icon-small.teal { background: #ECFDF5; color: #0D9488; }
        .stat-card .stat-icon-small.red { background: #FEF2F2; color: #DC2626; }
        .stat-card .stat-icon-small.blue { background: #EFF6FF; color: #0B5ED7; }
        
        [data-theme="dark"] .stat-card .stat-icon-small.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon-small.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon-small.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-card .stat-icon-small.teal { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-card .stat-icon-small.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-card .stat-icon-small.blue { background: #1E3A5F; color: #3B82F6; }
        
        /* ================================================================
           REVENUE CARDS - GREEN THEME
           ================================================================ */
        .revenue-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .revenue-card {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
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
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
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
        
        .revenue-card .card-sub .highlight { 
            font-weight: 700; 
            color: rgba(255,255,255,0.9);
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
           CHART CONTAINER
           ================================================================ */
        .chart-container {
            position: relative;
            height: 220px;
            width: 100%;
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
        
        /* ================================================================
           BADGES
           ================================================================ */
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
            .stats-row { grid-template-columns: 1fr 1fr; }
            .revenue-grid { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: 1fr; }
            .revenue-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.55rem; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
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
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .stat-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .revenue-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
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
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($grand_total_revenue, 0) ?> Revenue
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
    <!-- STATISTICS ROW - 8 CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <a href="payments.php?branch=<?= $cashier_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small green"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-number green">TSh <?= number_format($grand_total_revenue, 0) ?></p>
                </div>
            </div>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small blue"><i class="fas fa-file-invoice"></i></div>
                <div>
                    <p class="stat-label">Total Bills</p>
                    <p class="stat-number blue"><?= number_format($cashier['total_bills'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=paid" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small purple"><i class="fas fa-check-circle"></i></div>
                <div>
                    <p class="stat-label">Paid Bills</p>
                    <p class="stat-number purple"><?= number_format($cashier['paid_bills'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=pending" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small orange"><i class="fas fa-clock"></i></div>
                <div>
                    <p class="stat-label">Pending Bills</p>
                    <p class="stat-number orange"><?= number_format($cashier['pending_bills'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=partial" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small red"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <p class="stat-label">Partial Bills</p>
                    <p class="stat-number red"><?= number_format($cashier['partial_bills'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="payments.php?branch=<?= $cashier_id ?>&date=today" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small teal"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <p class="stat-label">Today's Payments</p>
                    <p class="stat-number teal"><?= number_format($cashier['today_payments'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="receipts.php?branch=<?= $cashier_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small purple"><i class="fas fa-receipt"></i></div>
                <div>
                    <p class="stat-label">Total Receipts</p>
                    <p class="stat-number purple"><?= number_format($cashier['total_payments'] ?? 0) ?></p>
                </div>
            </div>
        </a>
        <a href="cashiers.php?branch=<?= $cashier_id ?>" class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small green"><i class="fas fa-user-tie"></i></div>
                <div>
                    <p class="stat-label">Cashiers</p>
                    <p class="stat-number green"><?= number_format($cashier['active_cashiers'] ?? 0) ?> / <?= number_format($cashier['total_cashiers'] ?? 0) ?></p>
                </div>
            </div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- 8 REVENUE CARDS WITH NAVIGATION - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="revenue-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- 1. TOTAL REVENUE -->
        <a href="payments.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="card-amount"><?= formatCurrency($grand_total_revenue) ?></p>
            <p class="card-label">Total Revenue</p>
            <p class="card-sub">All payments combined</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 2. PHARMACY REVENUE -->
        <a href="pharmacy_revenue.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-prescription-bottle"></i></div>
            <p class="card-amount"><?= formatCurrency($pharmacy_total) ?></p>
            <p class="card-label">Pharmacy Revenue</p>
            <p class="card-sub">
                Prescribe: <span class="highlight"><?= formatCurrency($prescribe_revenue) ?></span> | 
                OTC: <span class="highlight"><?= formatCurrency($otc_revenue) ?></span>
            </p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 3. LAB REVENUE -->
        <a href="lab_revenue.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-flask"></i></div>
            <p class="card-amount"><?= formatCurrency($lab_revenue) ?></p>
            <p class="card-label">Lab Revenue</p>
            <p class="card-sub">All completed lab tests</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 4. PROCEDURES REVENUE -->
        <a href="procedures_revenue.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-syringe"></i></div>
            <p class="card-amount"><?= formatCurrency($procedures_revenue) ?></p>
            <p class="card-label">Procedures Revenue</p>
            <p class="card-sub">All procedure charges</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 5. TOOLS REVENUE -->
        <a href="tools_revenue.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-tools"></i></div>
            <p class="card-amount"><?= formatCurrency($tools_revenue) ?></p>
            <p class="card-label">Tools Revenue</p>
            <p class="card-sub">All tool/supply charges</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 6. PROCEDURES + TOOLS TOTAL -->
        <a href="procedures_tools_revenue.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-toolbox"></i></div>
            <p class="card-amount"><?= formatCurrency($procedures_tools_total) ?></p>
            <p class="card-label">Procedures & Tools Total</p>
            <p class="card-sub">
                Procedures: <span class="highlight"><?= formatCurrency($procedures_revenue) ?></span> | 
                Tools: <span class="highlight"><?= formatCurrency($tools_revenue) ?></span>
            </p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 7. OTHER SERVICES (Registration + Consultation) -->
        <a href="other_services_revenue.php?branch=<?= $cashier_id ?>" class="revenue-card">
            <div class="card-icon"><i class="fas fa-file-medical"></i></div>
            <p class="card-amount"><?= formatCurrency($other_services_total) ?></p>
            <p class="card-label">Other Services</p>
            <p class="card-sub">
                Registration: <span class="highlight"><?= formatCurrency($registration_revenue) ?></span> | 
                Consultation: <span class="highlight"><?= formatCurrency($consultation_revenue) ?></span>
            </p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- 8. GRAND TOTAL REVENUE (Breakdown) -->
        <a href="revenue_breakdown.php?branch=<?= $cashier_id ?>" class="revenue-card" style="border-color: rgba(255,255,255,0.2);">
            <div class="card-icon"><i class="fas fa-chart-pie"></i></div>
            <p class="card-amount"><?= formatCurrency($grand_total_revenue) ?></p>
            <p class="card-label">Grand Total Revenue</p>
            <p class="card-sub">
                Pharmacy: <span class="highlight"><?= formatCurrency($pharmacy_total) ?></span> | 
                Lab: <span class="highlight"><?= formatCurrency($lab_revenue) ?></span>
                <br>
                Proc/Tools: <span class="highlight"><?= formatCurrency($procedures_tools_total) ?></span> | 
                Other: <span class="highlight"><?= formatCurrency($other_services_total) ?></span>
            </p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- WEEKLY REVENUE CHART -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-line"></i>
                Weekly Revenue (Last 7 Days)
            </h3>
            <a href="revenue_reports.php?branch=<?= $cashier_id ?>" class="card-action">View Reports →</a>
        </div>
        <div class="card-body" style="padding:16px 20px;">
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
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
                                <td class="font-semibold text-green-600">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></td>
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
                                <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td class="text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="text-red-600 font-semibold">TSh <?= number_format($balance, 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600">TSh 0</span>
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
    <!-- CASHIERS LIST -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie text-teal-600"></i>
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
                                    <span class="badge badge-<?= $cashier_user['status'] === 'active' ? 'success' : 'danger' ?>" style="font-size:0.6rem;padding:2px 10px;">
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
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-user-tie text-2xl block mb-2"></i>
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
    // WEEKLY CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('weeklyChart');
        if (ctx) {
            var labels = [];
            var data = [];
            
            <?php 
            $week_dates = [];
            $week_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $week_dates[] = date('D', strtotime($date));
                $found = false;
                foreach ($weekly_revenue as $w) {
                    if ($w['date'] == $date) {
                        $week_data[] = (float)$w['total'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) $week_data[] = 0;
            }
            ?>
            
            labels = <?= json_encode($week_dates) ?>;
            data = <?= json_encode($week_data) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (TSh)',
                        data: data,
                        backgroundColor: 'rgba(5, 150, 105, 0.7)',
                        borderColor: 'rgba(5, 150, 105, 1)',
                        borderWidth: 2,
                        borderRadius: 4
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

    console.log('%c💰 Braick Dispensary - Cashier Dashboard (GREEN THEME)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($cashier['name']) ?> (ID: <?= $cashier_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c💵 Total Revenue: <?= formatCurrency($grand_total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Total Bills: <?= number_format($cashier['total_bills'] ?? 0) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Paid Bills: <?= number_format($cashier['paid_bills'] ?? 0) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c⏳ Pending Bills: <?= number_format($cashier['pending_bills'] ?? 0) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c🔄 Partial Bills: <?= number_format($cashier['partial_bills'] ?? 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c🟢 Green Theme Applied', 'font-size:13px; color:#059669;');
    console.log('%c📊 8 Revenue Cards with Navigation', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📈 Weekly Revenue Chart included', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>