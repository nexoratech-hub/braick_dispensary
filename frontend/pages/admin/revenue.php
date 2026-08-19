<?php
// ================================================================
// FILE: frontend/pages/admin/revenue.php
// ADMIN - REVENUE DASHBOARD
// View all revenue reports with charts and filters
// FIXED: Database structure match
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

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
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'monthly';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

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
// FUNCTION: Get Revenue Summary
// ================================================================
function getRevenueSummary($db, $branch_id = 'all', $date_from = null, $date_to = null) {
    $results = [];
    
    $branch_condition = "";
    $date_condition = "";
    $params = [];
    
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition = " AND branch_id = ?";
        $params[] = (int)$branch_id;
    }
    
    if ($date_from && $date_to) {
        $date_condition = " AND DATE(updated_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    }
    
    // Clone params for different queries
    $params_patient = $params;
    $params_otc = $params;
    $params_prescription = $params;
    $params_expenses = $params;
    
    // 1. PATIENT BILLS REVENUE (paid bills only)
    $sql_patient = "
        SELECT COALESCE(SUM(total_amount), 0) as patient_revenue,
               COUNT(*) as patient_bills_count
        FROM patient_bills
        WHERE status = 'paid'
    ";
    $branch_cond_patient = str_replace('branch_id', 'branch_id', $branch_condition);
    $date_cond_patient = str_replace('updated_at', 'updated_at', $date_condition);
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql_patient .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql_patient .= " AND DATE(updated_at) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql_patient);
    $stmt->execute($params_patient);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['patient_revenue'] = $data['patient_revenue'] ?? 0;
    $results['patient_bills_count'] = $data['patient_bills_count'] ?? 0;
    
    // 2. OTC REVENUE (paid otc sales only)
    $sql_otc = "
        SELECT COALESCE(SUM(net_amount), 0) as otc_revenue,
               COUNT(*) as otc_sales_count
        FROM otc_sales
        WHERE payment_status = 'paid'
    ";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql_otc .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql_otc .= " AND DATE(created_at) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['otc_revenue'] = $data['otc_revenue'] ?? 0;
    $results['otc_sales_count'] = $data['otc_sales_count'] ?? 0;
    
    // 3. PRESCRIPTION REVENUE (paid prescription sales only)
    $sql_prescription = "
        SELECT COALESCE(SUM(net_amount), 0) as prescription_revenue,
               COUNT(*) as prescription_sales_count
        FROM prescription_sales
        WHERE payment_status = 'paid'
    ";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql_prescription .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql_prescription .= " AND DATE(created_at) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql_prescription);
    $stmt->execute($params_prescription);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['prescription_revenue'] = $data['prescription_revenue'] ?? 0;
    $results['prescription_sales_count'] = $data['prescription_sales_count'] ?? 0;
    
    // 4. TOTAL REVENUE
    $results['total_revenue'] = $results['patient_revenue'] + $results['otc_revenue'] + $results['prescription_revenue'];
    
    // 5. EXPENSES (paid expenses only)
    $sql_expenses = "
        SELECT COALESCE(SUM(amount), 0) as total_expenses,
               COUNT(*) as expenses_count
        FROM expenses
        WHERE status = 'paid'
    ";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql_expenses .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql_expenses .= " AND DATE(payment_date) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql_expenses);
    $stmt->execute($params_expenses);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['total_expenses'] = $data['total_expenses'] ?? 0;
    $results['expenses_count'] = $data['expenses_count'] ?? 0;
    
    // 6. NET PROFIT
    $results['net_profit'] = $results['total_revenue'] - $results['total_expenses'];
    
    return $results;
}

// ================================================================
// FUNCTION: Get Monthly Revenue Data
// ================================================================
function getMonthlyRevenue($db, $branch_id = 'all', $months = 12) {
    $results = [];
    
    $branch_condition = "";
    $params = [];
    
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition = " AND branch_id = ?";
        $params[] = (int)$branch_id;
    }
    
    // Get last 12 months
    for ($i = $months - 1; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        $params_month = $params;
        
        // Patient Bills Revenue
        $sql = "
            SELECT COALESCE(SUM(total_amount), 0) as revenue
            FROM patient_bills
            WHERE status = 'paid'
            AND DATE(updated_at) BETWEEN ? AND ?
        ";
        if ($branch_id !== 'all' && is_numeric($branch_id)) {
            $sql .= " AND branch_id = ?";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$month_start, $month_end], $params_month));
        $patient_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
        
        // OTC Revenue
        $sql = "
            SELECT COALESCE(SUM(net_amount), 0) as revenue
            FROM otc_sales
            WHERE payment_status = 'paid'
            AND DATE(created_at) BETWEEN ? AND ?
        ";
        if ($branch_id !== 'all' && is_numeric($branch_id)) {
            $sql .= " AND branch_id = ?";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$month_start, $month_end], $params_month));
        $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
        
        // Prescription Revenue
        $sql = "
            SELECT COALESCE(SUM(net_amount), 0) as revenue
            FROM prescription_sales
            WHERE payment_status = 'paid'
            AND DATE(created_at) BETWEEN ? AND ?
        ";
        if ($branch_id !== 'all' && is_numeric($branch_id)) {
            $sql .= " AND branch_id = ?";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$month_start, $month_end], $params_month));
        $prescription_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
        
        // Expenses
        $sql = "
            SELECT COALESCE(SUM(amount), 0) as expenses
            FROM expenses
            WHERE status = 'paid'
            AND DATE(payment_date) BETWEEN ? AND ?
        ";
        if ($branch_id !== 'all' && is_numeric($branch_id)) {
            $sql .= " AND branch_id = ?";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$month_start, $month_end], $params_month));
        $expenses = $stmt->fetch(PDO::FETCH_ASSOC)['expenses'] ?? 0;
        
        $total = $patient_revenue + $otc_revenue + $prescription_revenue;
        
        $results[] = [
            'month' => $month_name,
            'month_key' => $month,
            'patient_revenue' => $patient_revenue,
            'otc_revenue' => $otc_revenue,
            'prescription_revenue' => $prescription_revenue,
            'total_revenue' => $total,
            'expenses' => $expenses,
            'net_profit' => $total - $expenses
        ];
    }
    
    return $results;
}

// ================================================================
// FUNCTION: Get Revenue by Category
// ================================================================
function getRevenueByCategory($db, $branch_id = 'all', $date_from = null, $date_to = null) {
    $results = [];
    
    $params = [];
    $branch_condition = "";
    $date_condition = "";
    
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition = " AND branch_id = ?";
        $params[] = (int)$branch_id;
    }
    
    if ($date_from && $date_to) {
        $date_condition = " AND DATE(updated_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    }
    
    // 1. Patient Bills
    $sql = "
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM patient_bills
        WHERE status = 'paid'
    ";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql .= " AND DATE(updated_at) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $results['Patient Bills'] = $patient;
    
    // 2. OTC Sales - reset params
    $params = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $params[] = (int)$branch_id;
    }
    if ($date_from && $date_to) {
        $params[] = $date_from;
        $params[] = $date_to;
    }
    $sql = "
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM otc_sales
        WHERE payment_status = 'paid'
    ";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $otc = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $results['OTC Sales'] = $otc;
    
    // 3. Prescription Sales - reset params
    $params = [];
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $params[] = (int)$branch_id;
    }
    if ($date_from && $date_to) {
        $params[] = $date_from;
        $params[] = $date_to;
    }
    $sql = "
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM prescription_sales
        WHERE payment_status = 'paid'
    ";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $sql .= " AND branch_id = ?";
    }
    if ($date_from && $date_to) {
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $results['Prescription Sales'] = $prescription;
    
    return $results;
}

// ================================================================
// FUNCTION: Get Recent Transactions
// ================================================================
function getRecentTransactions($db, $branch_id = 'all', $limit = 10) {
    $results = [];
    
    $branch_condition = "";
    $params = [];
    
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition = " AND branch_id = ?";
        $params[] = (int)$branch_id;
    }
    
    // Patient Bills
    $sql = "
        SELECT 
            'patient_bill' as type,
            pb.id,
            pb.patient_id,
            pb.total_amount as amount,
            pb.status,
            pb.updated_at as date,
            p.full_name as customer_name
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        WHERE pb.status = 'paid'
        $branch_condition
        ORDER BY pb.updated_at DESC
        LIMIT ?
    ";
    $params_limit = array_merge($params, [$limit]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params_limit);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['source'] = 'Patient Bill';
        $row['icon'] = 'fa-file-invoice';
        $row['color'] = '#0B5ED7';
        $results[] = $row;
    }
    
    // OTC Sales
    $sql = "
        SELECT 
            'otc_sale' as type,
            os.id,
            os.patient_id,
            os.net_amount as amount,
            os.payment_status as status,
            os.created_at as date,
            CASE 
                WHEN os.customer_name IS NOT NULL AND os.customer_name != '' THEN os.customer_name
                WHEN p.full_name IS NOT NULL THEN p.full_name
                ELSE 'Walk-in Customer'
            END as customer_name
        FROM otc_sales os
        LEFT JOIN patients p ON os.patient_id = p.id
        WHERE os.payment_status = 'paid'
        $branch_condition
        ORDER BY os.created_at DESC
        LIMIT ?
    ";
    $params_limit = array_merge($params, [$limit]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params_limit);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['source'] = 'OTC Sale';
        $row['icon'] = 'fa-cash-register';
        $row['color'] = '#059669';
        $results[] = $row;
    }
    
    // Prescription Sales
    $sql = "
        SELECT 
            'prescription_sale' as type,
            ps.id,
            ps.patient_id,
            ps.net_amount as amount,
            ps.payment_status as status,
            ps.created_at as date,
            p.full_name as customer_name
        FROM prescription_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        WHERE ps.payment_status = 'paid'
        $branch_condition
        ORDER BY ps.created_at DESC
        LIMIT ?
    ";
    $params_limit = array_merge($params, [$limit]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params_limit);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['source'] = 'Prescription Sale';
        $row['icon'] = 'fa-prescription';
        $row['color'] = '#7C3AED';
        $results[] = $row;
    }
    
    // Sort by date
    usort($results, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Return only latest
    return array_slice($results, 0, $limit);
}

// ================================================================
// GET DATA
// ================================================================
$summary = getRevenueSummary($db, $selected_branch_id, $date_from, $date_to);
$monthly_data = getMonthlyRevenue($db, $selected_branch_id, 12);
$category_data = getRevenueByCategory($db, $selected_branch_id, $date_from, $date_to);
$recent_transactions = getRecentTransactions($db, $selected_branch_id, 10);

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
// FUNCTION FORMAT CURRENCY
// ================================================================
function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 0);
}

function get_status_badge($status) {
    $map = [
        'paid' => 'badge-success',
        'pending' => 'badge-warning',
        'cancelled' => 'badge-danger',
        'partial' => 'badge-info'
    ];
    return $map[$status] ?? 'badge-secondary';
}

function get_status_label($status) {
    $map = [
        'paid' => '✅ Paid',
        'pending' => '⏳ Pending',
        'cancelled' => '❌ Cancelled',
        'partial' => '🔶 Partial'
    ];
    return $map[$status] ?? ucfirst($status);
}

function format_date($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

function format_date_short($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y', strtotime($datetime));
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Report - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #083C8A);
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius: 12px;
            --radius-lg: 18px;
            --transition: all 0.3s ease;
            
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
        }
        
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.4rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 5px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.7rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
        }
        
        .stat-card .stat-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .stat-card .stat-sub {
            font-size: 0.55rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .stat-card .stat-icon.blue { background: #DBEAFE; color: #0B5ED7; }
        .stat-card .stat-icon.green { background: #D1FAE5; color: #059669; }
        .stat-card .stat-icon.purple { background: #EDE9FE; color: #7C3AED; }
        .stat-card .stat-icon.orange { background: #FEF3C7; color: #D97706; }
        .stat-card .stat-icon.red { background: #FEE2E2; color: #DC2626; }
        .stat-card .stat-icon.teal { background: #CCFBF1; color: #0D9488; }
        
        [data-theme="dark"] .stat-card .stat-icon.blue { background: #1E3A5F; color: #60A5FA; }
        [data-theme="dark"] .stat-card .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-card .stat-icon.orange { background: #3A2A1A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-card .stat-icon.teal { background: #1A3A3A; color: #34D399; }
        
        /* Filter Section */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .filter-section .filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .filter-section .filter-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .filter-section select, 
        .filter-section input {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-section select:focus,
        .filter-section input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .filter-section .btn-apply {
            padding: 6px 16px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .filter-section .btn-apply:hover {
            background: var(--primary-gradient-strong);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .filter-section .btn-reset {
            padding: 6px 14px;
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .filter-section .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* Chart Grid */
        .chart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .chart-card .chart-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-card .chart-title i {
            color: var(--primary);
        }
        
        .chart-card .chart-container {
            position: relative;
            height: 250px;
        }
        
        /* Transaction Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 4px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        
        .table-footer {
            padding: 8px 14px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 992px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-section .filter-group { flex-wrap: wrap; }
            .filter-section select, .filter-section input { flex: 1; min-width: 100px; }
            .page-header .page-title { font-size: 1rem; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .main-content { padding: 8px; }
            .page-header { padding: 12px 16px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar" style="position:fixed;top:0;left:0;bottom:0;width:270px;background:#0B4EA8;color:white;z-index:50;overflow-y:auto;overflow-x:hidden;transition:transform 0.3s ease-in-out;transform:translateX(0);box-shadow:4px 0 20px rgba(0,0,0,0.15);">
    <div class="sidebar-brand" style="padding:18px 16px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;position:sticky;top:0;z-index:5;">
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= $logo_url ?>" alt="Braick Logo" style="width:42px;height:42px;border-radius:10px;object-fit:cover;background:white;padding:4px;border:2px solid rgba(255,255,255,0.1);" 
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p style="color:white;font-weight:700;font-size:0.95rem;line-height:1.2;">Braick Dispensary</p>
                <p style="color:#9EC5FE;font-size:0.65rem;font-weight:500;">Super Admin</p>
            </div>
        </div>
    </div>
    
    <nav style="padding:10px 8px 20px;">
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Main Menu</div>
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-home"></i> Dashboard</a>
        <a href="/dispensary_system/frontend/pages/admin/employees.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-users"></i> Employees</a>
        <a href="/dispensary_system/frontend/pages/admin/patients.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-user-injured"></i> Patients</a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Modules</div>
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-user-md"></i> Doctors</a>
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-prescription"></i> Pharmacy</a>
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-headset"></i> Reception</a>
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-flask"></i> Laboratory</a>
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-cash-register"></i> Cashier</a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Management</div>
        <a href="/dispensary_system/frontend/pages/admin/branches.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-store-alt"></i> Branches</a>
        <a href="/dispensary_system/frontend/pages/admin/departments.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-building"></i> Departments</a>
        <a href="/dispensary_system/frontend/pages/admin/reports.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="/dispensary_system/frontend/pages/admin/revenue.php?branch=<?= $selected_branch_id ?>" class="sidebar-link active" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:white;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:#0AA84F;cursor:pointer;border:none;width:100%;text-align:left;position:relative;box-shadow:0 4px 12px rgba(10,168,79,0.35);"><i class="fas fa-chart-line"></i> Revenue</a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Account</div>
        <a href="/dispensary_system/frontend/pages/admin/profile.php" class="sidebar-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#FCA5A5;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;border-top:2px solid rgba(255,255,255,0.08);padding-top:10px;margin-top:6px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav" style="position:fixed;top:0;left:270px;right:0;height:68px;background:var(--bg-nav);z-index:40;display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:2px solid var(--border-color);transition:background 0.3s ease,border-color 0.3s ease;">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="icon-btn lg:hidden" style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);transition:all 0.3s;background:transparent;border:none;cursor:pointer;position:relative;">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <span style="font-size:0.9rem;font-weight:600;color:var(--text-primary);">
            <i class="fas fa-chart-line text-primary" style="color:var(--primary);"></i> Revenue Report
        </span>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime" style="font-size:0.78rem;color:var(--text-secondary);font-weight:500;"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode" style="background:var(--bg-body);border:2px solid var(--border-color);border-radius:10px;padding:6px 12px;cursor:pointer;font-size:0.82rem;color:var(--text-primary);transition:all 0.3s;display:flex;align-items:center;gap:6px;">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);transition:all 0.3s;background:transparent;border:none;cursor:pointer;position:relative;">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>" style="position:absolute;top:6px;right:6px;width:8px;height:8px;background:#059669;border-radius:50%;border:2px solid var(--bg-nav);<?= $unread_notifications > 0 ? 'background:#EF4444;' : 'background:#94A3B8;animation:none;' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);cursor:pointer;transition:all 0.3s;"
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
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report
                <span class="role-badge-display">ADMIN</span>
                <span class="header-badge">
                    <i class="fas fa-calendar"></i> <?= format_date_short($date_from) ?> - <?= format_date_short($date_to) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                View revenue performance across all branches
                <?php if ($selected_branch_id !== 'all'): ?>
                    <?php 
                        $branch_name = 'All Branches';
                        foreach ($branches as $b) {
                            if ($b['id'] == $selected_branch_id) {
                                $branch_name = $b['name'];
                                break;
                            }
                        }
                    ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                        <i class="fas fa-store"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php else: ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                        <i class="fas fa-globe"></i> All Branches
                    </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);">
                    <i class="fas fa-money-bill-wave"></i> Total: <?= format_currency($summary['total_revenue']) ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="reports.php" class="btn-outline-light">
                <i class="fas fa-file-pdf"></i> Export Report
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-filter"></i> Filters</span>
        </div>
        
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;flex:1;">
            <select name="branch" class="filter-select" style="padding:6px 12px;border:2px solid var(--border-color);border-radius:var(--radius);font-size:0.75rem;background:var(--bg-card);color:var(--text-primary);outline:none;transition:var(--transition);">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" class="date-input" value="<?= htmlspecialchars($date_from) ?>" style="padding:6px 12px;border:2px solid var(--border-color);border-radius:var(--radius);font-size:0.75rem;background:var(--bg-card);color:var(--text-primary);outline:none;transition:var(--transition);">
            <span style="font-size:0.7rem;color:var(--text-secondary);">to</span>
            <input type="date" name="date_to" class="date-input" value="<?= htmlspecialchars($date_to) ?>" style="padding:6px 12px;border:2px solid var(--border-color);border-radius:var(--radius);font-size:0.75rem;background:var(--bg-card);color:var(--text-primary);outline:none;transition:var(--transition);">
            
            <button type="submit" class="btn-apply">
                <i class="fas fa-search"></i> Apply
            </button>
            <a href="revenue.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.1s;">
        <!-- Total Revenue -->
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-number"><?= format_currency($summary['total_revenue']) ?></div>
            <div class="stat-sub">All sources combined</div>
        </div>
        
        <!-- Patient Bills -->
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-label">Patient Bills</div>
            <div class="stat-number"><?= format_currency($summary['patient_revenue']) ?></div>
            <div class="stat-sub"><?= $summary['patient_bills_count'] ?> paid bills</div>
        </div>
        
        <!-- OTC Sales -->
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-cash-register"></i></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-number"><?= format_currency($summary['otc_revenue']) ?></div>
            <div class="stat-sub"><?= $summary['otc_sales_count'] ?> transactions</div>
        </div>
        
        <!-- Prescription Sales -->
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-prescription"></i></div>
            <div class="stat-label">Prescription Sales</div>
            <div class="stat-number"><?= format_currency($summary['prescription_revenue']) ?></div>
            <div class="stat-sub"><?= $summary['prescription_sales_count'] ?> sales</div>
        </div>
        
        <!-- Expenses -->
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-label">Expenses</div>
            <div class="stat-number"><?= format_currency($summary['total_expenses']) ?></div>
            <div class="stat-sub"><?= $summary['expenses_count'] ?> paid expenses</div>
        </div>
        
        <!-- Net Profit -->
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Net Profit</div>
            <div class="stat-number" style="color:<?= $summary['net_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                <?= format_currency($summary['net_profit']) ?>
            </div>
            <div class="stat-sub"><?= $summary['net_profit'] >= 0 ? '📈 Profit' : '📉 Loss' ?></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="chart-grid animate-fade-in-up" style="animation-delay:0.15s;">
        <!-- Monthly Revenue Chart -->
        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-chart-bar"></i> Monthly Revenue (Last 12 Months)
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <!-- Revenue by Category Chart -->
        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-chart-pie"></i> Revenue by Category
            </div>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT TRANSACTIONS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div style="padding:10px 16px;border-bottom:2px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <span style="font-weight:700;font-size:0.85rem;color:var(--text-primary);">
                <i class="fas fa-clock" style="color:var(--primary);"></i> Recent Transactions
            </span>
            <span style="font-size:0.65rem;color:var(--text-secondary);">
                <i class="fas fa-info-circle"></i> Latest <?= count($recent_transactions) ?> paid transactions
            </span>
        </div>
        
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-tag"></i> Type</th>
                        <th><i class="fas fa-user"></i> Customer</th>
                        <th style="text-align:right;"><i class="fas fa-money-bill"></i> Amount</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_transactions) > 0): ?>
                        <?php $i = 1; foreach ($recent_transactions as $txn): ?>
                            <tr>
                                <td style="text-align:center;font-weight:600;"><?= $i++ ?></td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:12px;background:<?= $txn['color'] ?>20;color:<?= $txn['color'] ?>;font-weight:600;font-size:0.55rem;">
                                        <i class="fas <?= $txn['icon'] ?>"></i>
                                        <?= $txn['source'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($txn['customer_name'] ?? 'N/A') ?></td>
                                <td style="text-align:right;font-weight:700;color:var(--primary);">
                                    <?= format_currency($txn['amount']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= get_status_badge($txn['status']) ?>">
                                        <?= get_status_label($txn['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.65rem;"><?= format_date($txn['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                                    <p style="font-size:0.9rem;">No transactions found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($recent_transactions) ?></strong> recent transactions
            </span>
            <span>
                <i class="fas fa-sync"></i> Last update: <span id="updateTimeDisplay"><?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Revenue Report
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
    // DARK MODE TOGGLE
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
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
        var ftEl = document.getElementById('updateTimeDisplay');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // CHARTS
    // ================================================================
    function getChartColors() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        return {
            text: isDark ? '#94A3B8' : '#64748B',
            grid: isDark ? '#334155' : '#E2E8F0',
            blue: '#3B82F6',
            green: '#059669',
            purple: '#7C3AED',
            orange: '#D97706',
            red: '#DC2626',
            teal: '#0D9488'
        };
    }
    
    // Monthly Revenue Chart
    (function() {
        var ctx = document.getElementById('monthlyChart');
        if (!ctx) return;
        
        var monthlyData = <?= json_encode($monthly_data) ?>;
        var colors = getChartColors();
        
        var labels = monthlyData.map(function(m) { return m.month; });
        var patientData = monthlyData.map(function(m) { return m.patient_revenue; });
        var otcData = monthlyData.map(function(m) { return m.otc_revenue; });
        var prescriptionData = monthlyData.map(function(m) { return m.prescription_revenue; });
        var expenseData = monthlyData.map(function(m) { return m.expenses; });
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Patient Bills',
                        data: patientData,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: '#3B82F6',
                        borderWidth: 1,
                        borderRadius: 3
                    },
                    {
                        label: 'OTC Sales',
                        data: otcData,
                        backgroundColor: 'rgba(5, 150, 105, 0.7)',
                        borderColor: '#059669',
                        borderWidth: 1,
                        borderRadius: 3
                    },
                    {
                        label: 'Prescription Sales',
                        data: prescriptionData,
                        backgroundColor: 'rgba(124, 58, 237, 0.7)',
                        borderColor: '#7C3AED',
                        borderWidth: 1,
                        borderRadius: 3
                    },
                    {
                        label: 'Expenses',
                        data: expenseData,
                        backgroundColor: 'rgba(220, 38, 38, 0.6)',
                        borderColor: '#DC2626',
                        borderWidth: 1,
                        borderRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: colors.text,
                            font: { size: 10 },
                            boxWidth: 12,
                            padding: 8
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { size: 8 } }
                    },
                    y: {
                        grid: { color: colors.grid },
                        ticks: {
                            color: colors.text,
                            font: { size: 8 },
                            callback: function(value) {
                                if (value >= 1000000) return 'TSh ' + (value / 1000000) + 'M';
                                if (value >= 1000) return 'TSh ' + (value / 1000) + 'K';
                                return 'TSh ' + value;
                            }
                        }
                    }
                }
            }
        });
    })();

    // Revenue by Category Chart (Pie Chart)
    (function() {
        var ctx = document.getElementById('categoryChart');
        if (!ctx) return;
        
        var categoryData = <?= json_encode($category_data) ?>;
        var colors = getChartColors();
        
        var labels = Object.keys(categoryData);
        var values = Object.values(categoryData);
        var bgColors = [colors.blue, colors.green, colors.purple];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors.map(function(c) { return c + 'CC'; }),
                    borderColor: bgColors,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: colors.text,
                            font: { size: 10 },
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                },
                cutout: '55%'
            }
        });
    })();

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c📊 Braick Dispensary - Revenue Report', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: <?= format_currency($summary['total_revenue']) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📈 Net Profit: <?= format_currency($summary['net_profit']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Branch: <?= $selected_branch_id !== 'all' ? 'Branch ID ' . $selected_branch_id : 'All Branches' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Period: <?= format_date_short($date_from) ?> - <?= format_date_short($date_to) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>