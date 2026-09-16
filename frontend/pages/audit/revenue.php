<?php
// ================================================================
// FILE: frontend/pages/audit/revenue.php
// AUDIT - REVENUE REPORT (HOURLY / DAILY / MONTHLY)
// ✅ FIXED: Now includes OTC Sales (from otc_sales table)
// ✅ Deep Blue Theme (#0A2E5C, #071E3D, #0EA5E9)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'audit') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$currency = 'TSh';

// GET SYSTEM SETTINGS
try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {}

// ================================================================
// FILTERS
// ================================================================
$filter_type = $_GET['filter'] ?? 'today';   // all, today, week, month, year, custom
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// BUILD DATE CONDITIONS FOR BILLS
$where_bills = "WHERE b.status = 'paid'";
$params_bills = [];

// BUILD DATE CONDITIONS FOR OTC
$where_otc = "WHERE os.payment_status = 'paid'";
$params_otc = [];

switch ($filter_type) {
    case 'today':
        $where_bills .= " AND DATE(b.updated_at) = CURDATE()";
        $where_otc .= " AND DATE(os.created_at) = CURDATE()";
        break;
    case 'week':
        $where_bills .= " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $where_otc .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $where_bills .= " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $where_otc .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case 'year':
        $where_bills .= " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $where_otc .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        $where_bills .= " AND DATE(b.updated_at) BETWEEN ? AND ?";
        $where_otc .= " AND DATE(os.created_at) BETWEEN ? AND ?";
        $params_bills[] = $date_from;
        $params_bills[] = $date_to;
        $params_otc[] = $date_from;
        $params_otc[] = $date_to;
        break;
}

// ================================================================
// SUMMARY STATS - BILLS
// ================================================================
$summary = [
    'total_revenue' => 0,
    'bills_revenue' => 0,
    'otc_revenue' => 0,
    'total_bills' => 0,
    'total_otc' => 0,
    'avg_bill' => 0,
    'cash_payments' => 0,
    'mobile_payments' => 0,
    'card_payments' => 0
];

try {
    // Bills summary
    $sql = "SELECT 
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COUNT(b.id) as total_bills,
        COALESCE(AVG(b.total_amount), 0) as avg_bill
        FROM bills b $where_bills";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_bills);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['bills_revenue'] = (float)($row['total_revenue'] ?? 0);
    $summary['total_bills'] = (int)($row['total_bills'] ?? 0);
    $summary['avg_bill'] = (float)($row['avg_bill'] ?? 0);
    
    // OTC summary
    $sql_otc = "SELECT 
        COALESCE(SUM(os.total_amount), 0) as total_revenue,
        COUNT(os.id) as total_otc,
        COALESCE(AVG(os.total_amount), 0) as avg_otc
        FROM otc_sales os $where_otc";
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $row_otc = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['otc_revenue'] = (float)($row_otc['total_revenue'] ?? 0);
    $summary['total_otc'] = (int)($row_otc['total_otc'] ?? 0);
    
    // Total revenue = Bills + OTC
    $summary['total_revenue'] = $summary['bills_revenue'] + $summary['otc_revenue'];
    
    // Payment methods - Bills
    $sql_pm = "SELECT b.payment_method, COUNT(*) as cnt, COALESCE(SUM(b.total_amount), 0) as total 
               FROM bills b $where_bills GROUP BY b.payment_method";
    $stmt = $db->prepare($sql_pm);
    $stmt->execute($params_bills);
    $pm_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pm_data as $pm) {
        $method = strtolower($pm['payment_method'] ?? 'cash');
        if (strpos($method, 'cash') !== false) $summary['cash_payments'] += (float)$pm['total'];
        elseif (strpos($method, 'mobile') !== false || strpos($method, 'mpesa') !== false || strpos($method, 'airtel') !== false || strpos($method, 'tigo') !== false || strpos($method, 'halo') !== false) $summary['mobile_payments'] += (float)$pm['total'];
        elseif (strpos($method, 'card') !== false || strpos($method, 'bank') !== false) $summary['card_payments'] += (float)$pm['total'];
    }
    
    // Payment methods - OTC
    $sql_pm_otc = "SELECT os.payment_method, COUNT(*) as cnt, COALESCE(SUM(os.total_amount), 0) as total 
                   FROM otc_sales os $where_otc GROUP BY os.payment_method";
    $stmt = $db->prepare($sql_pm_otc);
    $stmt->execute($params_otc);
    $pm_data_otc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pm_data_otc as $pm) {
        $method = strtolower($pm['payment_method'] ?? 'cash');
        if (strpos($method, 'cash') !== false) $summary['cash_payments'] += (float)$pm['total'];
        elseif (strpos($method, 'mobile') !== false || strpos($method, 'mpesa') !== false || strpos($method, 'airtel') !== false || strpos($method, 'tigo') !== false || strpos($method, 'halo') !== false) $summary['mobile_payments'] += (float)$pm['total'];
        elseif (strpos($method, 'card') !== false || strpos($method, 'bank') !== false) $summary['card_payments'] += (float)$pm['total'];
    }
    
} catch (Exception $e) {
    error_log("Revenue summary error: " . $e->getMessage());
}

// ================================================================
// HOURLY BREAKDOWN (TODAY) - Bills + OTC
// ================================================================
$hourly_data = [];
try {
    for ($h = 0; $h < 24; $h++) {
        $hourly_data[$h] = ['hour' => $h, 'bill_count' => 0, 'total' => 0];
    }
    
    // Bills hourly
    $sql = "SELECT HOUR(b.updated_at) as hour, COUNT(*) as bill_count, COALESCE(SUM(b.total_amount), 0) as total
            FROM bills b 
            WHERE b.status = 'paid' AND DATE(b.updated_at) = CURDATE()
            GROUP BY HOUR(b.updated_at)";
    $stmt = $db->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $row) {
        $hourly_data[$row['hour']]['bill_count'] = (int)$row['bill_count'];
        $hourly_data[$row['hour']]['total'] += (float)$row['total'];
    }
    
    // OTC hourly
    $sql = "SELECT HOUR(os.created_at) as hour, COUNT(*) as bill_count, COALESCE(SUM(os.total_amount), 0) as total
            FROM otc_sales os 
            WHERE os.payment_status = 'paid' AND DATE(os.created_at) = CURDATE()
            GROUP BY HOUR(os.created_at)";
    $stmt = $db->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $row) {
        $hourly_data[$row['hour']]['bill_count'] += (int)$row['bill_count'];
        $hourly_data[$row['hour']]['total'] += (float)$row['total'];
    }
} catch (Exception $e) {}

// ================================================================
// DAILY BREAKDOWN (LAST 30 DAYS) - Bills + OTC
// ================================================================
$daily_data = [];
try {
    // Initialize
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_data[$date] = [
            'day' => $date,
            'day_label' => date('d M', strtotime($date)),
            'bill_count' => 0,
            'total' => 0
        ];
    }
    
    // Bills daily
    $sql = "SELECT DATE(b.updated_at) as day, COUNT(*) as bill_count, COALESCE(SUM(b.total_amount), 0) as total
            FROM bills b 
            WHERE b.status = 'paid' AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(b.updated_at)";
    $stmt = $db->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $row) {
        if (isset($daily_data[$row['day']])) {
            $daily_data[$row['day']]['bill_count'] += (int)$row['bill_count'];
            $daily_data[$row['day']]['total'] += (float)$row['total'];
        }
    }
    
    // OTC daily
    $sql = "SELECT DATE(os.created_at) as day, COUNT(*) as bill_count, COALESCE(SUM(os.total_amount), 0) as total
            FROM otc_sales os 
            WHERE os.payment_status = 'paid' AND os.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(os.created_at)";
    $stmt = $db->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $row) {
        if (isset($daily_data[$row['day']])) {
            $daily_data[$row['day']]['bill_count'] += (int)$row['bill_count'];
            $daily_data[$row['day']]['total'] += (float)$row['total'];
        }
    }
    
    $daily_data = array_values($daily_data);
} catch (Exception $e) {}

// ================================================================
// MONTHLY BREAKDOWN (LAST 12 MONTHS) - Bills + OTC
// ================================================================
$monthly_data = [];
try {
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthly_data[$month] = [
            'month' => $month,
            'month_label' => date('M Y', strtotime("-$i months")),
            'bill_count' => 0,
            'total' => 0
        ];
    }
    
    // Bills monthly
    $sql = "SELECT DATE_FORMAT(b.updated_at, '%Y-%m') as month, COUNT(*) as bill_count, COALESCE(SUM(b.total_amount), 0) as total
            FROM bills b 
            WHERE b.status = 'paid' AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(b.updated_at, '%Y-%m')";
    $stmt = $db->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $row) {
        if (isset($monthly_data[$row['month']])) {
            $monthly_data[$row['month']]['bill_count'] += (int)$row['bill_count'];
            $monthly_data[$row['month']]['total'] += (float)$row['total'];
        }
    }
    
    // OTC monthly
    $sql = "SELECT DATE_FORMAT(os.created_at, '%Y-%m') as month, COUNT(*) as bill_count, COALESCE(SUM(os.total_amount), 0) as total
            FROM otc_sales os 
            WHERE os.payment_status = 'paid' AND os.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(os.created_at, '%Y-%m')";
    $stmt = $db->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $row) {
        if (isset($monthly_data[$row['month']])) {
            $monthly_data[$row['month']]['bill_count'] += (int)$row['bill_count'];
            $monthly_data[$row['month']]['total'] += (float)$row['total'];
        }
    }
    
    $monthly_data = array_values($monthly_data);
} catch (Exception $e) {}

// ================================================================
// TOP CASHIERS - Bills + OTC
// ================================================================
$top_cashiers = [];
try {
    $sql = "SELECT 
        u.id, u.full_name as cashier_name,
        COUNT(b.id) as total_bills,
        COALESCE(SUM(b.total_amount), 0) as total_revenue
        FROM users u
        LEFT JOIN bills b ON b.created_by = u.id AND b.status = 'paid' 
        WHERE u.role IN ('cashier', 'reception') AND u.status = 'active'
        GROUP BY u.id, u.full_name
        ORDER BY total_revenue DESC
        LIMIT 10";
    $stmt = $db->query($sql);
    $top_cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// TOP PHARMACY STAFF (OTC Sales)
// ================================================================
$top_pharmacy = [];
try {
    $sql = "SELECT 
        u.id, u.full_name as pharmacy_name,
        COUNT(os.id) as total_sales,
        COALESCE(SUM(os.total_amount), 0) as total_revenue
        FROM users u
        LEFT JOIN otc_sales os ON os.sold_by = u.id AND os.payment_status = 'paid'
        WHERE u.role = 'pharmacy' AND u.status = 'active'
        GROUP BY u.id, u.full_name
        ORDER BY total_revenue DESC
        LIMIT 10";
    $stmt = $db->query($sql);
    $top_pharmacy = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// RECENT TRANSACTIONS - Bills
// ================================================================
$transactions = [];
try {
    $sql = "SELECT 
        b.id, b.bill_number, b.total_amount, b.payment_method, b.updated_at,
        p.full_name as patient_name, p.patient_id as patient_code,
        u.full_name as cashier_name,
        'bill' as txn_type
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        $where_bills
        ORDER BY b.updated_at DESC
        LIMIT 30";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_bills);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add OTC Sales
    $sql_otc = "SELECT 
        os.id, os.sale_number as bill_number, os.total_amount, os.payment_method, os.created_at as updated_at,
        os.customer_name as patient_name, NULL as patient_code,
        u.full_name as cashier_name,
        'otc' as txn_type
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        $where_otc
        ORDER BY os.created_at DESC
        LIMIT 30";
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $otc_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort
    $transactions = array_merge($transactions, $otc_transactions);
    usort($transactions, function($a, $b) {
        return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    });
    $transactions = array_slice($transactions, 0, 50);
    
} catch (Exception $e) {}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/audit_header.php';
include_once '../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Report - Braick Audit</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --audit-primary: #0A2E5C;
            --audit-primary-dark: #071E3D;
            --audit-primary-light: #1E4B8C;
            --audit-primary-bg: #E5EEF9;
            --audit-accent: #0EA5E9;
            --audit-accent-dark: #0284C7;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --audit-primary: #1E4B8C;
            --audit-primary-bg: #0A2E5C;
            --audit-accent: #38BDF8;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 72px;
            padding: 24px 28px;
            min-height: calc(100vh - 72px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0A2E5C 0%, #071E3D 100%);
            border-radius: 18px;
            padding: 26px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 30px rgba(10, 46, 92, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            color: var(--audit-accent);
            font-size: 2rem;
            filter: drop-shadow(0 2px 8px rgba(14, 165, 233, 0.5));
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 9px 18px;
            border-radius: 11px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* FILTER BAR */
        .filter-bar {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-btn:hover {
            border-color: var(--audit-accent);
            color: var(--audit-accent);
            background: var(--audit-primary-bg);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #0A2E5C, #0EA5E9);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(10, 46, 92, 0.3);
        }
        
        .filter-divider {
            width: 2px;
            height: 28px;
            background: var(--border-color);
            margin: 0 6px;
        }
        
        .date-input {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            height: 34px;
        }
        
        .date-input:focus {
            border-color: var(--audit-accent);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }
        
        .btn-apply {
            padding: 6px 14px;
            background: linear-gradient(135deg, #0A2E5C, #0EA5E9);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.72rem;
            cursor: pointer;
            transition: all 0.3s;
            height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(10, 46, 92, 0.3);
        }
        
        /* STATS GRID - 6 CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card.total::before { background: linear-gradient(90deg, #0A2E5C, #0EA5E9); }
        .stat-card.bills::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
        .stat-card.otc::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
        .stat-card.avg::before { background: linear-gradient(90deg, #059669, #34D399); }
        .stat-card.cash::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
        .stat-card.mobile::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
        
        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: white;
            margin-bottom: 12px;
        }
        
        .stat-card.total .stat-icon { background: linear-gradient(135deg, #0A2E5C, #0EA5E9); }
        .stat-card.bills .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
        .stat-card.otc .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
        .stat-card.avg .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
        .stat-card.cash .stat-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
        .stat-card.mobile .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
        
        .stat-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            line-height: 1.15;
            display: flex;
            align-items: baseline;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .stat-value .currency {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-secondary);
        }
        
        .stat-value.total-val { color: var(--audit-primary); }
        [data-theme="dark"] .stat-value.total-val { color: var(--audit-accent); }
        .stat-value.bills-val { color: #0B5ED7; }
        .stat-value.otc-val { color: #0891B2; }
        .stat-value.avg-val { color: var(--success); }
        .stat-value.cash-val { color: var(--warning); }
        .stat-value.mobile-val { color: var(--purple); }
        
        .stat-sub {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 6px;
        }
        
        /* SECTION CARD */
        .section-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 22px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--audit-accent);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px dashed var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--audit-primary);
            font-size: 1.1rem;
        }
        
        [data-theme="dark"] .section-title i { color: var(--audit-accent); }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .chart-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }
        
        /* DATA TABLE */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: white;
            background: linear-gradient(135deg, #0A2E5C, #071E3D);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child { border-radius: 10px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 10px 0 0; }
        
        .data-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--audit-primary-bg);
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #0A2E5C;
        }
        
        .money-cell {
            font-family: 'Courier New', monospace;
            font-weight: 800;
            color: var(--success);
            text-align: right;
        }
        
        .money-cell .currency {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-right: 3px;
        }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px; height: 28px;
            border-radius: 50%;
            font-weight: 800;
            font-size: 0.72rem;
            background: var(--audit-primary-bg);
            color: var(--audit-primary);
        }
        
        .rank-badge.gold { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; }
        .rank-badge.silver { background: linear-gradient(135deg, #E5E7EB, #9CA3AF); color: #374151; }
        .rank-badge.bronze { background: linear-gradient(135deg, #FBBF24, #D97706); color: #78350F; }
        
        .badge-txn {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .badge-txn.bill {
            background: #DBEAFE;
            color: #0B5ED7;
            border: 1px solid #93C5FD;
        }
        
        .badge-txn.otc {
            background: #CFFAFE;
            color: #0891B2;
            border: 1px solid #67E8F9;
        }
        
        [data-theme="dark"] .badge-txn.bill {
            background: #1E3A5F;
            color: #60A5FA;
            border-color: #3B82F6;
        }
        
        [data-theme="dark"] .badge-txn.otc {
            background: #0A2A3A;
            color: #22D3EE;
            border-color: #0891B2;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .chart-grid-2 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-value { font-size: 1.2rem; }
            .section-card { padding: 16px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .main-content { padding: 10px; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-coins"></i> Financial Analysis & Sales Reports
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar-alt"></i> <?= date('d M Y') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-clock"></i> <span id="liveTime"><?= date('h:i:s A') ?></span>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <span style="font-size:0.72rem;font-weight:700;color:var(--text-secondary);display:flex;align-items:center;gap:5px;">
            <i class="fas fa-filter"></i> Filter:
        </span>
        
        <a href="?filter=all" class="filter-btn <?= $filter_type === 'all' ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?filter=today" class="filter-btn <?= $filter_type === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="?filter=week" class="filter-btn <?= $filter_type === 'week' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> 1 Week
        </a>
        <a href="?filter=month" class="filter-btn <?= $filter_type === 'month' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1 Month
        </a>
        <a href="?filter=year" class="filter-btn <?= $filter_type === 'year' ? 'active' : '' ?>">
            <i class="fas fa-calendar"></i> 1 Year
        </a>
        
        <div class="filter-divider"></div>
        
        <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="filter" value="custom">
            <input type="date" name="date_from" class="date-input" value="<?= $date_from ?>">
            <span style="color:var(--text-secondary);font-size:0.72rem;">to</span>
            <input type="date" name="date_to" class="date-input" value="<?= $date_to ?>">
            <button type="submit" class="btn-apply">
                <i class="fas fa-check"></i> Apply
            </button>
        </form>
    </div>

    <!-- STATS GRID - 6 CARDS -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value total-val">
                <span class="currency"><?= $currency ?></span>
                <?= number_format($summary['total_revenue'], 0) ?>
            </div>
            <div class="stat-sub">Bills + OTC Sales</div>
        </div>
        
        <div class="stat-card bills">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-label">Patient Bills</div>
            <div class="stat-value bills-val">
                <span class="currency"><?= $currency ?></span>
                <?= number_format($summary['bills_revenue'], 0) ?>
            </div>
            <div class="stat-sub"><?= number_format($summary['total_bills']) ?> bills</div>
        </div>
        
        <div class="stat-card otc">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-value otc-val">
                <span class="currency"><?= $currency ?></span>
                <?= number_format($summary['otc_revenue'], 0) ?>
            </div>
            <div class="stat-sub"><?= number_format($summary['total_otc']) ?> transactions</div>
        </div>
        
        <div class="stat-card avg">
            <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
            <div class="stat-label">Average Bill</div>
            <div class="stat-value avg-val">
                <span class="currency"><?= $currency ?></span>
                <?= number_format($summary['avg_bill'], 0) ?>
            </div>
            <div class="stat-sub">Per bill</div>
        </div>
        
        <div class="stat-card cash">
            <div class="stat-icon"><i class="fas fa-hand-holding-dollar"></i></div>
            <div class="stat-label">Cash Payments</div>
            <div class="stat-value cash-val">
                <span class="currency"><?= $currency ?></span>
                <?= number_format($summary['cash_payments'], 0) ?>
            </div>
            <div class="stat-sub">Cash collected</div>
        </div>
        
        <div class="stat-card mobile">
            <div class="stat-icon"><i class="fas fa-mobile-alt"></i></div>
            <div class="stat-label">Mobile Payments</div>
            <div class="stat-value mobile-val">
                <span class="currency"><?= $currency ?></span>
                <?= number_format($summary['mobile_payments'], 0) ?>
            </div>
            <div class="stat-sub">M-Pesa, Airtel, Tigo</div>
        </div>
    </div>

    <!-- HOURLY SALES CHART -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-clock"></i>
                Today's Hourly Sales (24 Hours)
            </div>
            <span style="font-size:0.72rem;color:var(--text-secondary);background:var(--audit-primary-bg);padding:4px 12px;border-radius:10px;font-weight:600;">
                <i class="fas fa-info-circle"></i> Bills + OTC Combined
            </span>
        </div>
        <div class="chart-container">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>

    <!-- DAILY + MONTHLY CHART GRID -->
    <div class="chart-grid-2">
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Daily Sales (Last 30 Days)
                </div>
            </div>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Monthly Sales (Last 12 Months)
                </div>
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- TOP CASHIERS + RECENT TRANSACTIONS -->
    <div class="chart-grid-2">
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-trophy"></i>
                    Top Performing Cashiers
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Rank</th>
                        <th>Cashier</th>
                        <th style="text-align:center;">Bills</th>
                        <th style="text-align:right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($top_cashiers) > 0): ?>
                        <?php $rank = 1; foreach ($top_cashiers as $c): 
                            $rc = '';
                            if ($rank == 1) $rc = 'gold';
                            elseif ($rank == 2) $rc = 'silver';
                            elseif ($rank == 3) $rc = 'bronze';
                        ?>
                            <tr>
                                <td><span class="rank-badge <?= $rc ?>"><?= $rank ?></span></td>
                                <td><strong><?= htmlspecialchars($c['cashier_name']) ?></strong></td>
                                <td style="text-align:center;font-weight:700;color:var(--purple);"><?= number_format($c['total_bills']) ?></td>
                                <td class="money-cell">
                                    <span class="currency"><?= $currency ?></span><?= number_format($c['total_revenue'], 0) ?>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-secondary);">No data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-pills"></i>
                    Top Performing Pharmacy Staff (OTC)
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Rank</th>
                        <th>Pharmacy Staff</th>
                        <th style="text-align:center;">Sales</th>
                        <th style="text-align:right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($top_pharmacy) > 0): ?>
                        <?php $rank = 1; foreach ($top_pharmacy as $p): 
                            $rc = '';
                            if ($rank == 1) $rc = 'gold';
                            elseif ($rank == 2) $rc = 'silver';
                            elseif ($rank == 3) $rc = 'bronze';
                        ?>
                            <tr>
                                <td><span class="rank-badge <?= $rc ?>"><?= $rank ?></span></td>
                                <td><strong><?= htmlspecialchars($p['pharmacy_name']) ?></strong></td>
                                <td style="text-align:center;font-weight:700;color:#0891B2;"><?= number_format($p['total_sales']) ?></td>
                                <td class="money-cell">
                                    <span class="currency"><?= $currency ?></span><?= number_format($p['total_revenue'], 0) ?>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-secondary);">No data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RECENT TRANSACTIONS -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-history"></i>
                Recent Transactions (Bills + OTC)
            </div>
            <span style="font-size:0.72rem;color:var(--text-secondary);">
                Showing <?= count($transactions) ?> transactions
            </span>
        </div>
        <div style="overflow:auto;max-height:500px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Bill/Sale #</th>
                        <th>Customer/Patient</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Method</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td>
                                    <span class="badge-txn <?= $t['txn_type'] ?>">
                                        <?= $t['txn_type'] === 'bill' ? 'Bill' : 'OTC' ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family:monospace;font-size:0.7rem;color:var(--audit-primary);font-weight:800;background:var(--audit-primary-bg);padding:2px 8px;border-radius:6px;">
                                        <?= htmlspecialchars($t['bill_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;font-weight:600;"><?= htmlspecialchars($t['patient_name'] ?? 'Walk-in') ?></div>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);"><?= htmlspecialchars($t['cashier_name'] ?? '') ?></div>
                                </td>
                                <td class="money-cell">
                                    <span class="currency"><?= $currency ?></span><?= number_format($t['total_amount'], 0) ?>
                                </td>
                                <td style="font-size:0.7rem;text-transform:capitalize;"><?= htmlspecialchars($t['payment_method']) ?></td>
                                <td style="font-size:0.7rem;">
                                    <?= date('H:i', strtotime($t['updated_at'])) ?>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);"><?= date('d M', strtotime($t['updated_at'])) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No transactions</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
// LIVE TIME
function updateLiveTime() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var el = document.getElementById('liveTime');
    if (el) el.textContent = timeStr;
}
updateLiveTime();
setInterval(updateLiveTime, 1000);

// HOURLY CHART
var hourlyLabels = [];
var hourlyValues = [];
<?php foreach ($hourly_data as $h => $row): ?>
hourlyLabels.push('<?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00');
hourlyValues.push(<?= (float)$row['total'] ?>);
<?php endforeach; ?>

new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hourlyLabels,
        datasets: [{
            label: 'Revenue',
            data: hourlyValues,
            backgroundColor: function(context) {
                const {ctx, chartArea} = context.chart;
                if (!chartArea) return '#0EA5E9';
                const g = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                g.addColorStop(0, '#0A2E5C');
                g.addColorStop(1, '#0EA5E9');
                return g;
            },
            borderRadius: 6,
            maxBarThickness: 30
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0A2E5C',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: ctx => '💰 <?= $currency ?> ' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => '<?= $currency ?> ' + (v/1000).toFixed(0) + 'K',
                    color: '#94A3B8',
                    font: { size: 10, weight: '600' }
                },
                grid: { color: 'rgba(148, 163, 184, 0.12)' }
            },
            x: {
                ticks: { color: '#94A3B8', font: { size: 9 }, maxRotation: 45 },
                grid: { display: false }
            }
        }
    }
});

// DAILY CHART
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($daily_data, 'day_label')) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode(array_map('floatval', array_column($daily_data, 'total'))) ?>,
            borderColor: '#0EA5E9',
            backgroundColor: 'rgba(14, 165, 233, 0.15)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#0A2E5C',
            pointBorderColor: '#FFFFFF',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0A2E5C',
                padding: 12,
                cornerRadius: 8,
                callbacks: { label: ctx => '💰 <?= $currency ?> ' + ctx.parsed.y.toLocaleString() }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => '<?= $currency ?> ' + (v/1000).toFixed(0) + 'K',
                    color: '#94A3B8',
                    font: { size: 10, weight: '600' }
                },
                grid: { color: 'rgba(148, 163, 184, 0.12)' }
            },
            x: {
                ticks: { color: '#94A3B8', font: { size: 8 }, maxRotation: 45, maxTicksLimit: 15 },
                grid: { display: false }
            }
        }
    }
});

// MONTHLY CHART
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthly_data, 'month_label')) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode(array_map('floatval', array_column($monthly_data, 'total'))) ?>,
            backgroundColor: function(context) {
                const {ctx, chartArea} = context.chart;
                if (!chartArea) return '#0EA5E9';
                const g = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                g.addColorStop(0, '#0A2E5C');
                g.addColorStop(1, '#0EA5E9');
                return g;
            },
            borderRadius: 8,
            maxBarThickness: 40
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0A2E5C',
                padding: 12,
                cornerRadius: 8,
                callbacks: { label: ctx => '💰 <?= $currency ?> ' + ctx.parsed.y.toLocaleString() }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => '<?= $currency ?> ' + (v/1000).toFixed(0) + 'K',
                    color: '#94A3B8',
                    font: { size: 10, weight: '600' }
                },
                grid: { color: 'rgba(148, 163, 184, 0.12)' }
            },
            x: {
                ticks: { color: '#94A3B8', font: { size: 9, weight: '600' }, maxRotation: 45 },
                grid: { display: false }
            }
        }
    }
});

console.log('%c💰 Braick Audit - Revenue Report (FIXED)', 'font-size:16px; font-weight:bold; color:#0A2E5C;');
console.log('%c✅ Now includes OTC Sales', 'font-size:13px; color:#0EA5E9;');
console.log('%c✅ Total Revenue = Bills + OTC', 'font-size:13px; color:#34D399;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>