<?php
// ================================================================
// FILE: frontend/pages/audit/dashboard.php
// AUDIT - DASHBOARD WITH REPORTS & ANALYTICS
// ✅ FIXED: Now includes OTC Sales (from otc_sales table)
// ✅ User Card - SOLID COLOR (#0A2E5C)
// ✅ Money Text - Better display
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
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$user_username = $_SESSION['username'] ?? 'audit';

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
// STATISTICS - BILLS + OTC
// ================================================================
$stats = [
    'total_revenue' => 0,
    'bills_revenue' => 0,
    'otc_revenue' => 0,
    'today_revenue' => 0,
    'total_patients' => 0,
    'today_patients' => 0,
    'total_bills' => 0,
    'total_otc' => 0,
    'today_bills' => 0,
    'total_medicines_sold' => 0,
    'total_employees' => 0,
    'total_doctors' => 0,
    'total_reception' => 0,
    'pending_prescriptions' => 0,
    'pending_lab_tests' => 0,
    'total_audit_logs' => 0,
    'today_audit_logs' => 0
];

try {
    // Regular Bills revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count FROM bills WHERE status = 'paid' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['bills_revenue'] = (float)($row['total'] ?? 0);
    $stats['total_bills'] = (int)($row['count'] ?? 0);
    
    // OTC Revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count FROM otc_sales WHERE payment_status = 'paid'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['otc_revenue'] = (float)($row['total'] ?? 0);
    $stats['total_otc'] = (int)($row['count'] ?? 0);
    
    // Total Revenue = Bills + OTC
    $stats['total_revenue'] = $stats['bills_revenue'] + $stats['otc_revenue'];
    
    // Today's Revenue (Bills + OTC)
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE status = 'paid' AND DATE(updated_at) = CURDATE()");
    $bills_today = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
    $otc_today = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stats['today_revenue'] = $bills_today + $otc_today;
    
    // Patients
    $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    $stats['total_patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
    $stats['today_patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Today's Bills + OTC
    $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE status = 'paid' AND DATE(updated_at) = CURDATE()");
    $bills_today_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM otc_sales WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
    $otc_today_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stats['today_bills'] = $bills_today_count + $otc_today_count;
    
    // Medicines Sold (Bills + OTC)
    $stmt = $db->query("SELECT COALESCE(SUM(quantity), 0) as total FROM bill_items WHERE item_type = 'medication' AND status != 'cancelled'");
    $med_from_bills = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $db->query("SELECT COALESCE(SUM(quantity), 0) as total FROM otc_sale_items");
    $med_from_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stats['total_medicines_sold'] = $med_from_bills + $med_from_otc;
    
    // Employees
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('admin', 'audit') AND status = 'active'");
    $stats['total_employees'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    $stats['total_doctors'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'reception' AND status = 'active'");
    $stats['total_reception'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Pending
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status IN ('pending', 'confirmed')");
    $stats['pending_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', 'in_progress')");
    $stats['pending_lab_tests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Audit Logs
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs");
    $stats['total_audit_logs'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $stats['today_audit_logs'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
}

// ================================================================
// HOURLY SALES (Bills + OTC)
// ================================================================
$hourly_sales = [];
try {
    for ($h = 0; $h < 24; $h++) {
        $hourly_sales[$h] = ['hour' => $h, 'bill_count' => 0, 'total' => 0];
    }
    
    // Bills hourly
    $stmt = $db->query("
        SELECT HOUR(updated_at) as hour, COUNT(*) as bill_count, COALESCE(SUM(total_amount), 0) as total
        FROM bills WHERE status = 'paid' AND DATE(updated_at) = CURDATE()
        GROUP BY HOUR(updated_at)
    ");
    $hourly_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hourly_bills as $row) {
        $hourly_sales[$row['hour']]['bill_count'] += (int)$row['bill_count'];
        $hourly_sales[$row['hour']]['total'] += (float)$row['total'];
    }
    
    // OTC hourly
    $stmt = $db->query("
        SELECT HOUR(created_at) as hour, COUNT(*) as bill_count, COALESCE(SUM(total_amount), 0) as total
        FROM otc_sales WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()
        GROUP BY HOUR(created_at)
    ");
    $hourly_otc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hourly_otc as $row) {
        $hourly_sales[$row['hour']]['bill_count'] += (int)$row['bill_count'];
        $hourly_sales[$row['hour']]['total'] += (float)$row['total'];
    }
} catch (Exception $e) {}

// ================================================================
// MONTHLY SALES (Bills + OTC) - Last 12 Months
// ================================================================
$monthly_sales = [];
$month_labels = [];
try {
    $monthly_map = [];
    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i months"));
        $monthly_map[$key] = [
            'label' => date('M Y', strtotime("-$i months")),
            'total' => 0
        ];
    }
    
    // Bills monthly
    $stmt = $db->query("
        SELECT DATE_FORMAT(updated_at, '%Y-%m') as month, COALESCE(SUM(total_amount), 0) as total
        FROM bills WHERE status = 'paid' AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
    ");
    $bills_monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bills_monthly as $row) {
        if (isset($monthly_map[$row['month']])) {
            $monthly_map[$row['month']]['total'] += (float)$row['total'];
        }
    }
    
    // OTC monthly
    $stmt = $db->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(total_amount), 0) as total
        FROM otc_sales WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");
    $otc_monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($otc_monthly as $row) {
        if (isset($monthly_map[$row['month']])) {
            $monthly_map[$row['month']]['total'] += (float)$row['total'];
        }
    }
    
    foreach ($monthly_map as $data) {
        $monthly_sales[] = $data['total'];
        $month_labels[] = $data['label'];
    }
} catch (Exception $e) {}

// ================================================================
// TOP MEDICINES (Bills + OTC)
// ================================================================
$top_medicines = [];
try {
    $medicines_map = [];
    
    // From Bills (medications)
    $stmt = $db->query("
        SELECT item_name as medication_name, SUM(quantity) as total_qty, COUNT(*) as times_sold,
            COALESCE(SUM(total_price), 0) as total_revenue
        FROM bill_items WHERE item_type = 'medication' AND status != 'cancelled'
        GROUP BY item_name
    ");
    $bill_meds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bill_meds as $row) {
        $name = $row['medication_name'];
        if (!isset($medicines_map[$name])) {
            $medicines_map[$name] = ['medication_name' => $name, 'total_qty' => 0, 'times_sold' => 0, 'total_revenue' => 0];
        }
        $medicines_map[$name]['total_qty'] += (int)$row['total_qty'];
        $medicines_map[$name]['times_sold'] += (int)$row['times_sold'];
        $medicines_map[$name]['total_revenue'] += (float)$row['total_revenue'];
    }
    
    // From OTC
    $stmt = $db->query("
        SELECT item_name as medication_name, SUM(quantity) as total_qty, COUNT(*) as times_sold,
            COALESCE(SUM(total_price), 0) as total_revenue
        FROM otc_sale_items
        GROUP BY item_name
    ");
    $otc_meds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($otc_meds as $row) {
        $name = $row['medication_name'];
        if (!isset($medicines_map[$name])) {
            $medicines_map[$name] = ['medication_name' => $name, 'total_qty' => 0, 'times_sold' => 0, 'total_revenue' => 0];
        }
        $medicines_map[$name]['total_qty'] += (int)$row['total_qty'];
        $medicines_map[$name]['times_sold'] += (int)$row['times_sold'];
        $medicines_map[$name]['total_revenue'] += (float)$row['total_revenue'];
    }
    
    // Sort by total_qty DESC
    usort($medicines_map, function($a, $b) {
        return $b['total_qty'] - $a['total_qty'];
    });
    $top_medicines = array_slice($medicines_map, 0, 10);
} catch (Exception $e) {}

// ================================================================
// DOCTOR PERFORMANCE
// ================================================================
$doctor_performance = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.full_name as doctor_name, COUNT(DISTINCT v.id) as total_visits,
            COUNT(DISTINCT p.id) as total_prescriptions, COALESCE(SUM(b.total_amount), 0) as total_revenue
        FROM users u
        LEFT JOIN visits v ON v.doctor_id = u.id
        LEFT JOIN prescriptions p ON p.doctor_id = u.id
        LEFT JOIN bills b ON b.visit_id = v.id AND b.status = 'paid'
        WHERE u.role = 'doctor' AND u.status = 'active'
        GROUP BY u.id, u.full_name ORDER BY total_visits DESC LIMIT 10
    ");
    $doctor_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// RECEPTION PERFORMANCE
// ================================================================
$reception_performance = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.full_name as reception_name, COUNT(DISTINCT p.id) as total_patients_registered,
            COUNT(DISTINCT v.id) as total_visits_created
        FROM users u
        LEFT JOIN patients p ON p.created_by = u.id
        LEFT JOIN visits v ON v.receptionist_id = u.id
        WHERE u.role = 'reception' AND u.status = 'active'
        GROUP BY u.id, u.full_name ORDER BY total_patients_registered DESC LIMIT 10
    ");
    $reception_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// RECENT TRANSACTIONS (Bills + OTC)
// ================================================================
$recent_transactions = [];
try {
    // Bills
    $stmt = $db->query("
        SELECT b.bill_number, b.total_amount, b.payment_method, b.updated_at,
            p.full_name as patient_name, u.full_name as cashier_name,
            'bill' as txn_type
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.status = 'paid'
        ORDER BY b.updated_at DESC LIMIT 20
    ");
    $bill_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // OTC
    $stmt = $db->query("
        SELECT os.sale_number as bill_number, os.total_amount, os.payment_method, os.created_at as updated_at,
            os.customer_name as patient_name, u.full_name as cashier_name,
            'otc' as txn_type
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.payment_status = 'paid'
        ORDER BY os.created_at DESC LIMIT 20
    ");
    $otc_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort
    $recent_transactions = array_merge($bill_txns, $otc_txns);
    usort($recent_transactions, function($a, $b) {
        return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    });
    $recent_transactions = array_slice($recent_transactions, 0, 10);
} catch (Exception $e) {}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/audit_header.php';
include_once '../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
            --cyan: #0891B2;
            --cyan-bg: #CFFAFE;
            
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
            --audit-primary-dark: #0A2E5C;
            --audit-primary-light: #3B82F6;
            --audit-primary-bg: #0A2E5C;
            --audit-accent: #38BDF8;
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
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
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
            letter-spacing: -0.02em;
        }
        
        .page-header .page-title i { 
            font-size: 2rem; 
            color: var(--audit-accent);
            filter: drop-shadow(0 2px 8px rgba(14, 165, 233, 0.5));
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .role-badge {
            background: linear-gradient(135deg, #0EA5E9, #0284C7);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .page-header .role-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
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
            border: 1px solid rgba(255,255,255,0.1);
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
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
        
        /* USER INFO CARD - SOLID COLOR */
        .user-info-card {
            background: var(--audit-primary);
            border-radius: 18px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 8px 30px rgba(10, 46, 92, 0.35);
            position: relative;
            overflow: hidden;
            color: white;
            border: 2px solid var(--audit-primary-light);
        }
        
        .user-info-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .user-info-card .user-profile-section {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        
        .user-info-card .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--audit-accent);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.2), 0 8px 24px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
            background: white;
        }
        
        .user-info-card .user-avatar-large:hover {
            transform: scale(1.05) rotate(-3deg);
        }
        
        .user-info-card .user-details {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .user-info-card .user-greeting {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .user-info-card .user-name-large {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.02em;
            line-height: 1.1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .user-info-card .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 4px;
        }
        
        .user-info-card .user-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.9);
            background: rgba(255,255,255,0.1);
            padding: 4px 12px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .user-info-card .user-meta-item i {
            color: var(--audit-accent);
            font-size: 0.8rem;
        }
        
        .user-info-card .user-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--audit-accent);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.5);
            margin-top: 4px;
            width: fit-content;
        }
        
        .user-info-card .user-stats-quick {
            display: flex;
            gap: 12px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        
        .user-info-card .quick-stat {
            background: rgba(255,255,255,0.1);
            padding: 12px 20px;
            border-radius: 14px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
            text-align: center;
            min-width: 100px;
            transition: all 0.3s ease;
        }
        
        .user-info-card .quick-stat:hover {
            background: rgba(255,255,255,0.18);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        
        .user-info-card .quick-stat-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: white;
            line-height: 1;
            font-family: 'Courier New', monospace;
        }
        
        .user-info-card .quick-stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            margin-top: 4px;
        }
        
        /* STATS GRID - 5 CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 22px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: height 0.3s ease;
        }
        
        .stat-card:hover::before {
            height: 6px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card.revenue::before { background: linear-gradient(90deg, #0A2E5C, #0EA5E9); }
        .stat-card.bills::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
        .stat-card.otc::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
        .stat-card.patients::before { background: linear-gradient(90deg, #059669, #34D399); }
        .stat-card.medicines::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
        
        .stat-card.revenue:hover { border-color: #0EA5E9; }
        .stat-card.bills:hover { border-color: #3B82F6; }
        .stat-card.otc:hover { border-color: #06B6D4; }
        .stat-card.patients:hover { border-color: #34D399; }
        .stat-card.medicines:hover { border-color: #FBBF24; }
        
        .stat-card .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.08) rotate(-5deg);
        }
        
        .stat-card.revenue .stat-icon { background: linear-gradient(135deg, #0A2E5C, #0EA5E9); }
        .stat-card.bills .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
        .stat-card.otc .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
        .stat-card.patients .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
        .stat-card.medicines .stat-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
        
        .stat-card .stat-label {
            font-size: 0.68rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }
        
        .stat-card .stat-value {
            font-size: 1.65rem;
            font-weight: 900;
            color: var(--text-primary);
            line-height: 1.15;
            font-family: 'Courier New', 'Monaco', monospace;
            letter-spacing: -0.02em;
            display: flex;
            align-items: baseline;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .stat-card .stat-value .currency-symbol {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-secondary);
        }
        
        .stat-card .stat-value .money-number {
            font-size: 1.65rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            text-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }
        
        .stat-card.revenue .stat-value .money-number { color: var(--audit-primary); }
        [data-theme="dark"] .stat-card.revenue .stat-value .money-number { color: var(--audit-accent); }
        .stat-card.bills .stat-value .money-number { color: #0B5ED7; }
        .stat-card.otc .stat-value .money-number { color: #0891B2; }
        .stat-card.patients .stat-value .money-number { color: var(--success); }
        .stat-card.medicines .stat-value .money-number { color: var(--warning); }
        
        .stat-card .stat-sub {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .stat-card .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.62rem;
            font-weight: 700;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .stat-card .stat-badge.warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .stat-card .stat-badge.cyan {
            background: var(--cyan-bg);
            color: var(--cyan);
        }
        
        .stat-card .stat-badge.purple {
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        /* SECTION CARD */
        .section-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 26px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
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
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px dashed var(--border-color);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }
        
        .section-title i {
            color: var(--audit-primary);
            font-size: 1.15rem;
        }
        
        [data-theme="dark"] .section-title i {
            color: var(--audit-accent);
        }
        
        .section-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 7px 16px;
            border-radius: 9px;
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
            gap: 6px;
        }
        
        .filter-btn:hover {
            border-color: var(--audit-accent);
            color: var(--audit-accent);
            background: var(--audit-primary-bg);
            transform: translateY(-1px);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
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
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr { transition: background 0.2s ease; }
        .data-table tbody tr:hover td { background: var(--audit-primary-bg); }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #0A2E5C; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        .money-cell {
            font-family: 'Courier New', 'Monaco', monospace;
            font-weight: 800;
            font-size: 0.82rem;
            color: var(--success);
            letter-spacing: -0.02em;
            text-align: right;
        }
        
        .money-cell .currency-prefix {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 3px;
        }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 800;
            font-size: 0.72rem;
            background: var(--audit-primary-bg);
            color: var(--audit-primary);
            box-shadow: 0 2px 6px rgba(10, 46, 92, 0.15);
        }
        
        .rank-badge.gold { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; }
        .rank-badge.silver { background: linear-gradient(135deg, #E5E7EB, #9CA3AF); color: #374151; }
        .rank-badge.bronze { background: linear-gradient(135deg, #FBBF24, #D97706); color: #78350F; }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 6px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0A2E5C, #0EA5E9);
            border-radius: 3px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* BADGE TXN */
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
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .chart-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.35rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card .stat-value { font-size: 1.3rem; }
            .stat-card .stat-value .money-number { font-size: 1.3rem; }
            .section-card { padding: 18px; }
            .user-info-card { padding: 18px 20px; }
            .user-info-card .user-avatar-large { width: 60px; height: 60px; }
            .user-info-card .user-name-large { font-size: 1.2rem; }
            .user-info-card .user-stats-quick { width: 100%; justify-content: space-between; }
            .user-info-card .quick-stat { flex: 1; min-width: auto; padding: 10px 14px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .main-content { padding: 10px; }
            .user-info-card .user-stats-quick { flex-direction: column; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shield-alt"></i>
                Audit Dashboard
                <span class="role-badge">AUDIT MODE</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-chart-line"></i>
                Sales Reports, Analytics & Performance
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
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="revenue.php" class="btn-outline-light">
                <i class="fas fa-chart-line"></i> Full Reports
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- USER INFO CARD -->
    <div class="user-info-card">
        <div class="user-profile-section">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="user-avatar-large"
                 onerror="this.src='<?= $logo_path ?>'">
            <div class="user-details">
                <div class="user-greeting">
                    <i class="fas fa-hand-sparkles"></i>
                    Welcome back,
                </div>
                <div class="user-name-large"><?= htmlspecialchars($user_full_name) ?></div>
                <div class="user-meta">
                    <span class="user-meta-item">
                        <i class="fas fa-user-tag"></i>
                        @<?= htmlspecialchars($user_username) ?>
                    </span>
                    <?php if (!empty($user_email)): ?>
                    <span class="user-meta-item">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($user_email) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($user_phone)): ?>
                    <span class="user-meta-item">
                        <i class="fas fa-phone"></i>
                        <?= htmlspecialchars($user_phone) ?>
                    </span>
                    <?php endif; ?>
                    <span class="user-meta-item">
                        <i class="fas fa-store-alt"></i>
                        <?= htmlspecialchars($user_branch_name) ?>
                    </span>
                </div>
                <div class="user-role-badge">
                    <i class="fas fa-shield-alt"></i>
                    AUDIT & REPORTS
                </div>
            </div>
        </div>
        
        <div class="user-stats-quick">
            <div class="quick-stat">
                <div class="quick-stat-value"><?= number_format($stats['total_audit_logs']) ?></div>
                <div class="quick-stat-label">Audit Logs</div>
            </div>
            <div class="quick-stat">
                <div class="quick-stat-value"><?= number_format($stats['today_audit_logs']) ?></div>
                <div class="quick-stat-label">Today</div>
            </div>
            <div class="quick-stat">
                <div class="quick-stat-value"><?= number_format($stats['total_patients']) ?></div>
                <div class="quick-stat-label">Patients</div>
            </div>
        </div>
    </div>

    <!-- STATS GRID - 5 CARDS -->
    <div class="stats-grid">
        <!-- Total Revenue (Bills + OTC) -->
        <div class="stat-card revenue">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($stats['total_revenue'], 0) ?></span>
            </div>
            <div class="stat-sub">
                <span class="stat-badge">
                    <i class="fas fa-arrow-up"></i> Today: <?= $currency ?> <?= number_format($stats['today_revenue'], 0) ?>
                </span>
            </div>
        </div>
        
        <!-- Patient Bills -->
        <div class="stat-card bills">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <div class="stat-label">Patient Bills</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($stats['bills_revenue'], 0) ?></span>
            </div>
            <div class="stat-sub">
                <span class="stat-badge purple">
                    <i class="fas fa-check"></i> <?= number_format($stats['total_bills']) ?> bills
                </span>
            </div>
        </div>
        
        <!-- OTC Sales -->
        <div class="stat-card otc">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
            </div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($stats['otc_revenue'], 0) ?></span>
            </div>
            <div class="stat-sub">
                <span class="stat-badge cyan">
                    <i class="fas fa-shopping-cart"></i> <?= number_format($stats['total_otc']) ?> transactions
                </span>
            </div>
        </div>
        
        <!-- Total Patients -->
        <div class="stat-card patients">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-user-injured"></i>
                </div>
            </div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($stats['total_patients']) ?></span>
            </div>
            <div class="stat-sub">
                <span class="stat-badge">
                    <i class="fas fa-plus"></i> Today: <?= $stats['today_patients'] ?>
                </span>
            </div>
        </div>
        
        <!-- Medicines Sold -->
        <div class="stat-card medicines">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-pills"></i>
                </div>
            </div>
            <div class="stat-label">Medicines Sold</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($stats['total_medicines_sold']) ?></span>
            </div>
            <div class="stat-sub">
                <span class="stat-badge warning">
                    <i class="fas fa-box"></i> Total units
                </span>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="chart-grid">
        <!-- MONTHLY SALES CHART -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Monthly Sales (Bills + OTC) - Last 12 Months
                </div>
                <div class="section-actions">
                    <span style="font-size:0.72rem;color:var(--text-secondary);background:var(--audit-primary-bg);padding:5px 12px;border-radius:10px;font-weight:600;">
                        <i class="fas fa-info-circle"></i> Total: <?= $currency ?> <?= number_format(array_sum($monthly_sales), 0) ?>
                    </span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="monthlySalesChart"></canvas>
            </div>
        </div>
        
        <!-- HOURLY SALES (TODAY) -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-clock"></i>
                    Today's Hourly Sales (Bills + OTC)
                </div>
            </div>
            <div class="chart-container">
                <canvas id="hourlySalesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- TOP MEDICINES + RECENT TRANSACTIONS -->
    <div class="chart-grid">
        <!-- TOP SELLING MEDICINES -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-trophy"></i>
                    Top 10 Selling Medicines
                </div>
                <div class="section-actions">
                    <a href="inventory.php" class="filter-btn">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Rank</th>
                            <th>Medicine</th>
                            <th style="text-align:right;">Qty Sold</th>
                            <th style="text-align:right;">Times</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_medicines) > 0): ?>
                            <?php $rank = 1; foreach ($top_medicines as $med): 
                                $rank_class = '';
                                if ($rank == 1) $rank_class = 'gold';
                                elseif ($rank == 2) $rank_class = 'silver';
                                elseif ($rank == 3) $rank_class = 'bronze';
                            ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width:<?= min(100, ($med['total_qty'] / max(1, $top_medicines[0]['total_qty'])) * 100) ?>%"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:right;font-weight:800;color:var(--audit-primary);font-family:'Courier New',monospace;"><?= number_format($med['total_qty'] ?? 0) ?></td>
                                    <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $med['times_sold'] ?? 0 ?>×</td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($med['total_revenue'] ?? 0, 0) ?>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                    <i class="fas fa-chart-bar" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    <p style="font-weight:600;">No medicine sales data yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- RECENT TRANSACTIONS (Bills + OTC) -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Transactions (Bills + OTC)
                </div>
                <div class="section-actions">
                    <a href="revenue.php" class="filter-btn">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
            <div style="overflow-x:auto;max-height:420px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th style="text-align:right;">Amount</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_transactions) > 0): ?>
                            <?php foreach ($recent_transactions as $txn): ?>
                                <tr>
                                    <td>
                                        <span class="badge-txn <?= $txn['txn_type'] ?>">
                                            <?= $txn['txn_type'] === 'bill' ? 'Bill' : 'OTC' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-family:'Courier New',monospace;font-size:0.7rem;color:var(--audit-primary);font-weight:800;background:var(--audit-primary-bg);padding:3px 8px;border-radius:6px;">
                                            <?= htmlspecialchars($txn['bill_number'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;font-size:0.8rem;color:var(--text-primary);"><?= htmlspecialchars($txn['patient_name'] ?? 'Walk-in') ?></div>
                                        <div style="font-size:0.62rem;color:var(--text-secondary);">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($txn['cashier_name'] ?? 'N/A') ?>
                                        </div>
                                    </td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($txn['total_amount'] ?? 0, 0) ?>
                                    </td>
                                    <td>
                                        <div style="font-size:0.72rem;font-weight:600;"><?= date('H:i', strtotime($txn['updated_at'])) ?></div>
                                        <div style="font-size:0.6rem;color:var(--text-secondary);"><?= date('d M', strtotime($txn['updated_at'])) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                    <i class="fas fa-receipt" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    <p style="font-weight:600;">No recent transactions</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PERFORMANCE ROW -->
    <div class="chart-grid">
        <!-- DOCTOR PERFORMANCE -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-user-md"></i>
                    Doctor Performance
                </div>
                <div class="section-actions">
                    <a href="employees.php" class="filter-btn">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Doctor</th>
                            <th style="text-align:center;">Visits</th>
                            <th style="text-align:center;">Rx</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($doctor_performance) > 0): ?>
                            <?php $rank = 1; foreach ($doctor_performance as $doc): 
                                $rank_class = '';
                                if ($rank == 1) $rank_class = 'gold';
                                elseif ($rank == 2) $rank_class = 'silver';
                                elseif ($rank == 3) $rank_class = 'bronze';
                            ?>
                                <tr>
                                    <td><span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span></td>
                                    <td>
                                        <div style="font-weight:700;font-size:0.82rem;color:var(--text-primary);">Dr. <?= htmlspecialchars($doc['doctor_name'] ?? 'N/A') ?></div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width:<?= min(100, ($doc['total_visits'] / max(1, $doctor_performance[0]['total_visits'])) * 100) ?>%"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--audit-primary);font-family:'Courier New',monospace;"><?= number_format($doc['total_visits'] ?? 0) ?></td>
                                    <td style="text-align:center;font-weight:700;color:var(--purple);"><?= number_format($doc['total_prescriptions'] ?? 0) ?></td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($doc['total_revenue'] ?? 0, 0) ?>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                    <i class="fas fa-user-md" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    <p style="font-weight:600;">No doctor performance data yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- RECEPTION PERFORMANCE -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-headset"></i>
                    Reception Performance
                </div>
                <div class="section-actions">
                    <a href="employees.php" class="filter-btn">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Receptionist</th>
                            <th style="text-align:center;">Patients</th>
                            <th style="text-align:center;">Visits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reception_performance) > 0): ?>
                            <?php $rank = 1; foreach ($reception_performance as $rec): 
                                $rank_class = '';
                                if ($rank == 1) $rank_class = 'gold';
                                elseif ($rank == 2) $rank_class = 'silver';
                                elseif ($rank == 3) $rank_class = 'bronze';
                            ?>
                                <tr>
                                    <td><span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span></td>
                                    <td>
                                        <div style="font-weight:700;font-size:0.82rem;color:var(--text-primary);"><?= htmlspecialchars($rec['reception_name'] ?? 'N/A') ?></div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width:<?= min(100, ($rec['total_patients_registered'] / max(1, $reception_performance[0]['total_patients_registered'])) * 100) ?>%"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--audit-primary);font-family:'Courier New',monospace;"><?= number_format($rec['total_patients_registered'] ?? 0) ?></td>
                                    <td style="text-align:center;font-weight:700;color:var(--purple);font-family:'Courier New',monospace;"><?= number_format($rec['total_visits_created'] ?? 0) ?></td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:40px;color:var(--text-secondary);">
                                    <i class="fas fa-headset" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    <p style="font-weight:600;">No reception performance data yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

// MONTHLY SALES CHART
var monthlyLabels = <?= json_encode($month_labels) ?>;
var monthlyData = <?= json_encode($monthly_sales) ?>;

if (monthlyLabels.length === 0) {
    monthlyLabels = ['No Data'];
    monthlyData = [0];
}

var monthlyCtx = document.getElementById('monthlySalesChart');
if (monthlyCtx) {
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Revenue (<?= $currency ?>)',
                data: monthlyData,
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(10, 46, 92, 0.8)';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, '#0A2E5C');
                    gradient.addColorStop(1, '#0EA5E9');
                    return gradient;
                },
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 50
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0A2E5C',
                    titleColor: '#FFFFFF',
                    bodyColor: '#BAE6FD',
                    padding: 14,
                    cornerRadius: 10,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    callbacks: {
                        label: function(context) {
                            return '💰 Revenue: <?= $currency ?> ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?= $currency ?> ' + (value/1000).toFixed(0) + 'K';
                        },
                        color: '#94A3B8',
                        font: { size: 11, weight: '600' }
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.12)' }
                },
                x: {
                    ticks: {
                        color: '#94A3B8',
                        font: { size: 11, weight: '600' }
                    },
                    grid: { display: false }
                }
            }
        }
    });
}

// HOURLY SALES CHART (TODAY)
var hourlyData = <?= json_encode(array_values($hourly_sales)) ?>;
var hourlyLabels = [];
var hourlyValues = [];

hourlyData.forEach(function(item) {
    hourlyLabels.push(String(item.hour).padStart(2, '0') + ':00');
    hourlyValues.push(parseFloat(item.total));
});

var hourlyCtx = document.getElementById('hourlySalesChart');
if (hourlyCtx) {
    new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: hourlyLabels,
            datasets: [{
                label: 'Revenue',
                data: hourlyValues,
                borderColor: '#0EA5E9',
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(14, 165, 233, 0.15)';
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(14, 165, 233, 0.4)');
                    gradient.addColorStop(1, 'rgba(14, 165, 233, 0.02)');
                    return gradient;
                },
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0A2E5C',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#0EA5E9',
                pointHoverBorderColor: '#FFFFFF',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0A2E5C',
                    titleColor: '#FFFFFF',
                    bodyColor: '#BAE6FD',
                    padding: 14,
                    cornerRadius: 10,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    callbacks: {
                        label: function(context) {
                            return '💰 Revenue: <?= $currency ?> ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?= $currency ?> ' + (value/1000).toFixed(0) + 'K';
                        },
                        color: '#94A3B8',
                        font: { size: 10, weight: '600' }
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.12)' }
                },
                x: {
                    ticks: {
                        color: '#94A3B8',
                        font: { size: 9, weight: '600' },
                        maxRotation: 45
                    },
                    grid: { display: false }
                }
            }
        }
    });
}

console.log('%c🔍 Braick Audit Dashboard (FIXED)', 'font-size:20px; font-weight:bold; color:#0A2E5C;');
console.log('%c✅ Now includes OTC Sales', 'font-size:13px; color:#0EA5E9; font-weight:bold;');
console.log('%c✅ Total Revenue = Bills + OTC', 'font-size:13px; color:#34D399;');
console.log('%c✅ 5 Cards: Revenue, Bills, OTC, Patients, Medicines', 'font-size:13px; color:#34D399;');
console.log('%c✅ Top Medicines = Bills + OTC', 'font-size:13px; color:#34D399;');
console.log('%c✅ Recent Transactions = Bills + OTC', 'font-size:13px; color:#34D399;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>