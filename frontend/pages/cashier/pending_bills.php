<?php
// ================================================================
// FILE: frontend/pages/cashier/pending_bills.php
// CASHIER - PENDING BILLS LIST (GREEN THEME)
// FIXED: Supports both string and integer status values
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Default to reception.rose (ID: 11)
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 11;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['email'] = 'rose@braick.com';
    $_SESSION['phone'] = '+255 700 000 005';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

// ================================================================
// ALLOW RECEPTION TO ACCESS CASHIER PAGES
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
$is_admin = ($_SESSION['role'] === 'admin' || $_SESSION['is_admin'] === true);

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';

// Initialize variables
$pending_bills = [];
$total_pending_amount = 0;
$total_bills_count = 0;
$currency = 'TSh';

try {
    $db = getDB();
    
    // ================================================================
    // BUILD DATE FILTER
    // ================================================================
    $date_condition = "";
    $params = [];
    
    switch ($filter) {
        case 'today':
            $date_condition = "AND DATE(pb.created_at) = CURDATE()";
            break;
        case 'week':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case '3months':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case '6months':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case 'year':
            $date_condition = "AND pb.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_condition = "AND DATE(pb.created_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            } else {
                $date_condition = "";
            }
            break;
        case 'all':
        default:
            $date_condition = "";
            break;
    }
    
    // ================================================================
    // BUILD SEARCH CONDITION
    // ================================================================
    $search_condition = "";
    if (!empty($search)) {
        $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // ================================================================
    // GET PENDING BILLS - FIXED: Supports both string and integer status
    // ================================================================
    $sql = "
        SELECT 
            pb.*, 
            p.full_name as patient_name, 
            p.patient_id as patient_id_number,
            p.phone,
            p.gender,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id) as item_count,
            (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as payment_count,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = pb.id) as total_paid
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.branch_id = ? 
        AND pb.status IN ('pending', 'partial', '0', '1', 0, 1)
        $date_condition
        $search_condition
        ORDER BY pb.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    
    // Build parameters
    $exec_params = [$selected_branch_id];
    foreach ($params as $param) {
        $exec_params[] = $param;
    }
    
    $stmt->execute($exec_params);
    $pending_bills = $stmt->fetchAll();
    
    // ================================================================
    // DEBUG - Log results
    // ================================================================
    error_log("Pending bills found: " . count($pending_bills));
    if (count($pending_bills) > 0) {
        error_log("First bill status: " . $pending_bills[0]['status']);
        error_log("First bill number: " . $pending_bills[0]['bill_number']);
    }
    
    // ================================================================
    // GROUP BILLS BY PATIENT
    // ================================================================
    $patient_bills = [];
    foreach ($pending_bills as $bill) {
        $patient_id = $bill['patient_id'];
        if (!isset($patient_bills[$patient_id])) {
            $patient_bills[$patient_id] = [
                'patient_id' => $patient_id,
                'patient_name' => $bill['patient_name'] ?? 'Unknown Patient',
                'patient_id_number' => $bill['patient_id_number'] ?? 'N/A',
                'phone' => $bill['phone'] ?? 'N/A',
                'gender' => $bill['gender'] ?? 'N/A',
                'bills' => [],
                'total_amount' => 0,
                'total_balance' => 0,
                'total_paid' => 0,
                'bill_count' => 0
            ];
        }
        
        $patient_bills[$patient_id]['bills'][] = $bill;
        $patient_bills[$patient_id]['total_amount'] += $bill['total_amount'];
        $patient_bills[$patient_id]['total_balance'] += $bill['balance'];
        $patient_bills[$patient_id]['total_paid'] += $bill['paid_amount'];
        $patient_bills[$patient_id]['bill_count']++;
    }
    
    // Calculate totals
    $total_bills_count = count($pending_bills);
    $total_pending_amount = 0;
    foreach ($patient_bills as $patient) {
        $total_pending_amount += $patient['total_balance'];
    }
    
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $pending_bills = [];
    $patient_bills = [];
    $total_pending_amount = 0;
    $total_bills_count = 0;
    error_log("Pending bills error: " . $e->getMessage());
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bills - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
            --patient-card-border: #059669;
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
            --patient-card-border: #34D399;
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
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        /* TOP NAV */
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
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
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
            background: var(--success);
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
            background: var(--success-dark);
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
            border-color: var(--success);
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
            color: var(--success);
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
            border-color: var(--success);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, var(--warning), #B45309);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(217, 119, 6, 0.25);
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
        
        /* FILTER SECTION */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }
        
        .filter-btn.active {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .filter-btn.active:hover {
            background: var(--success-dark);
            border-color: var(--success-dark);
        }
        
        .filter-btn i {
            margin-right: 4px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .date-picker-group .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            width: auto;
        }
        
        .date-picker-group .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .date-picker-group .btn-apply {
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--success);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-picker-group .btn-apply:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            max-width: 1400px;
            margin: 0 auto 20px;
        }
        
        .stat-card-box {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card-box:hover {
            border-color: var(--success);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card-box .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-card-box .stat-number.orange {
            color: #D97706;
        }
        
        .stat-card-box .stat-number.green {
            color: var(--success);
        }
        
        .stat-card-box .stat-number.red {
            color: var(--danger);
        }
        
        .stat-card-box .stat-number.blue {
            color: var(--primary);
        }
        
        .stat-card-box .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card-box .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            max-width: 1400px;
            margin: 0 auto 16px;
        }
        
        .patient-card:hover {
            border-color: var(--patient-card-border);
            box-shadow: var(--shadow-md);
        }
        
        .patient-card-header {
            background: var(--primary-bg);
            padding: 14px 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s ease;
            user-select: none;
        }
        
        .patient-card-header:hover {
            background: var(--success-bg);
        }
        
        [data-theme="dark"] .patient-card-header {
            background: #1E3A5F;
        }
        
        [data-theme="dark"] .patient-card-header:hover {
            background: #1A3A2A;
        }
        
        .patient-card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
        }
        
        .patient-card-header .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            background: var(--success);
            flex-shrink: 0;
        }
        
        .patient-card-header .patient-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .patient-card-header .patient-id {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .patient-card-header .patient-totals {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .patient-card-header .total-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .patient-card-header .total-badge.orange {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .patient-card-header .total-badge.green {
            background: #D1FAE5;
            color: #059669;
        }
        
        .patient-card-header .total-badge.red {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .patient-card-header .total-amount {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--danger);
        }
        
        .chevron-icon {
            transition: transform 0.3s ease;
            font-size: 1rem;
            color: var(--text-secondary);
            display: inline-block;
        }
        
        .chevron-icon.rotated {
            transform: rotate(180deg);
        }
        
        [data-theme="dark"] .patient-card-header .total-badge.orange {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .patient-card-header .total-badge.green {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .patient-card-header .total-badge.red {
            background: #3A1A1A;
            color: #F87171;
        }
        
        .patient-card-body {
            overflow: hidden;
            transition: max-height 0.4s ease-in-out, padding 0.3s ease;
            max-height: 0;
            padding: 0 20px;
            background: var(--bg-card);
        }
        
        .patient-card-body.open {
            max-height: 3000px;
            padding: 16px 20px;
        }
        
        /* TABLE */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: <?= $is_admin ? '1000px' : '900px' ?>;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table .bill-number {
            font-weight: 600;
            font-size: 0.75rem;
            font-family: monospace;
        }
        
        .data-table .bill-number.pending {
            color: #D97706;
        }
        
        .data-table .bill-number.partial {
            color: var(--primary);
        }
        
        <?php if (!$is_admin): ?>
        .data-table .balance-col {
            display: none !important;
        }
        .data-table .balance-col-header {
            display: none !important;
        }
        <?php endif; ?>
        
        /* STATUS BADGE */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge.partial {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        [data-theme="dark"] .status-badge.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge.partial {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.72rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-process {
            background: var(--success);
            color: white;
        }
        
        .btn-process:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.65rem; 
            border-radius: 6px; 
        }
        
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
        
        .admin-badge {
            display: <?= $is_admin ? 'inline-block' : 'none' ?>;
            background: #7C3AED;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* TOAST */
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
        .toast-custom.warning { background: var(--warning); }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state .sub {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
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
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-group { gap: 4px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .patient-card-header { flex-direction: column; align-items: flex-start; }
            .patient-card-header .patient-totals { width: 100%; justify-content: flex-start; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .filter-section { padding: 10px 12px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card-box { padding: 12px 14px; }
            .stat-card-box .stat-number { font-size: 1.4rem; }
            .btn { padding: 4px 8px; font-size: 0.6rem; }
            .data-table { font-size: 0.65rem; min-width: 600px; }
            .patient-card-header .patient-info { width: 100%; }
            .patient-card-header .patient-totals { width: 100%; flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients or bills..." value="<?= htmlspecialchars($search) ?>">
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
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Pending Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <?php if ($is_admin): ?>
                    <span class="admin-badge"><i class="fas fa-user-shield"></i> ADMIN VIEW</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                Manage pending bills in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills_count ?> Bills
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <?= count($patient_bills) ?> Patients
                </span>
                
                <?php if ($filter !== 'all' && $filter !== 'custom'): ?>
                <span class="header-badge">
                    <i class="fas fa-filter"></i>
                    <?= ucfirst(str_replace('months', ' Months', $filter)) ?>
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>" style="max-width:1400px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Filter:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1 Week
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Month
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3 Months
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6 Months
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Year
            </a>
        </div>
        
        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $start_date ?>" placeholder="Start Date">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= $end_date ?>" placeholder="End Date">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card-box">
            <div class="stat-icon">📋</div>
            <p class="stat-number orange"><?= $total_bills_count ?></p>
            <p class="stat-label">Total Pending Bills</p>
        </div>
        <div class="stat-card-box">
            <div class="stat-icon">👤</div>
            <p class="stat-number blue"><?= count($patient_bills) ?></p>
            <p class="stat-label">Patients with Bills</p>
        </div>
        <?php if ($is_admin): ?>
        <div class="stat-card-box">
            <div class="stat-icon">💰</div>
            <p class="stat-number red"><?= $currency ?> <?= number_format($total_pending_amount, 0) ?></p>
            <p class="stat-label">Total Balance</p>
        </div>
        <?php endif; ?>
        <div class="stat-card-box">
            <div class="stat-icon">📅</div>
            <p class="stat-number green">
                <?php 
                    if ($filter === 'today') echo 'Today';
                    elseif ($filter === 'week') echo '7 Days';
                    elseif ($filter === 'month') echo '30 Days';
                    elseif ($filter === '3months') echo '90 Days';
                    elseif ($filter === '6months') echo '180 Days';
                    elseif ($filter === 'year') echo '365 Days';
                    elseif ($filter === 'custom') echo 'Custom';
                    else echo 'All Time';
                ?>
            </p>
            <p class="stat-label">Date Range</p>
        </div>
    </div>

    <!-- PATIENT BILLS LIST -->
    <?php if (count($patient_bills) > 0): ?>
        <?php foreach ($patient_bills as $patient): ?>
            <div class="patient-card animate-fade-in-up">
                <!-- Patient Header - Click to toggle -->
                <div class="patient-card-header" onclick="togglePatient(<?= $patient['patient_id'] ?>)">
                    <div class="patient-info">
                        <div class="patient-avatar">
                            <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="patient-name">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                                <span class="patient-id ml-2">
                                    <?= htmlspecialchars($patient['patient_id_number'] ?? 'N/A') ?>
                                </span>
                            </div>
                            <div style="font-size:0.75rem;color:var(--text-secondary);">
                                <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                <?php if ($patient['gender']): ?>
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-venus-mars mr-1"></i> <?= htmlspecialchars($patient['gender']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="patient-totals">
                        <span class="total-badge orange">
                            <i class="fas fa-file-invoice mr-1"></i> <?= $patient['bill_count'] ?> Bills
                        </span>
                        <?php if ($is_admin): ?>
                            <span class="total-badge red">
                                <i class="fas fa-money-bill mr-1"></i> Balance: <?= $currency ?> <?= number_format($patient['total_balance'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient['total_paid'] > 0): ?>
                            <span class="total-badge green">
                                <i class="fas fa-check-circle mr-1"></i> Paid: <?= $currency ?> <?= number_format($patient['total_paid'], 0) ?>
                            </span>
                        <?php endif; ?>
                        <span class="total-amount">
                            <?= $currency ?> <?= number_format($patient['total_amount'], 0) ?>
                        </span>
                        <i class="fas fa-chevron-down chevron-icon" id="chevron_<?= $patient['patient_id'] ?>"></i>
                    </div>
                </div>
                
                <!-- Patient Bills Table - Collapsible -->
                <div class="patient-card-body" id="patient_<?= $patient['patient_id'] ?>">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Bill #</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <?php if ($is_admin): ?>
                                        <th class="balance-col-header">Balance</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Created</th>
                                    <th colspan="2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($patient['bills'] as $bill): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <span class="bill-number <?= $bill['status'] ?>">
                                                <?= htmlspecialchars($bill['bill_number']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-semibold">
                                                <?= $currency ?> <?= number_format($bill['total_amount'], 0) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-green-600 dark:text-green-400">
                                                <?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?>
                                            </span>
                                        </td>
                                        <?php if ($is_admin): ?>
                                            <td class="balance-col">
                                                <span class="font-semibold text-red-600 dark:text-red-400">
                                                    <?= $currency ?> <?= number_format($bill['balance'], 0) ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="status-badge <?= $bill['status'] ?>">
                                                <?= ucfirst($bill['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-sm font-semibold">
                                                <?= $bill['item_count'] ?? 0 ?>
                                            </span>
                                        </td>
                                        <td class="text-xs">
                                            <?= isset($bill['created_at']) ? date('d/m/Y', strtotime($bill['created_at'])) : 'N/A' ?>
                                            <br>
                                            <span class="text-gray-400 text-[0.6rem]">
                                                <?= isset($bill['created_at']) ? date('h:i A', strtotime($bill['created_at'])) : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- View Button -->
                                            <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-view btn-sm" title="View Bill Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                        <td>
                                            <!-- Process Payment Button -->
                                            <?php if (!$is_admin): ?>
                                                <a href="process_payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-process btn-sm" title="Process Payment">
                                                    <i class="fas fa-money-bill-wave"></i> Pay
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">(Read Only)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <!-- Patient Total Row -->
                                <tr style="background: var(--primary-bg); font-weight: 700;">
                                    <td colspan="2" style="text-align:right;font-size:0.8rem;">
                                        <i class="fas fa-calculator mr-1"></i> Patient Total:
                                    </td>
                                    <td>
                                        <?= $currency ?> <?= number_format($patient['total_amount'], 0) ?>
                                    </td>
                                    <td>
                                        <?= $currency ?> <?= number_format($patient['total_paid'], 0) ?>
                                    </td>
                                    <?php if ($is_admin): ?>
                                        <td style="color:var(--danger);">
                                            <?= $currency ?> <?= number_format($patient['total_balance'], 0) ?>
                                        </td>
                                    <?php endif; ?>
                                    <td colspan="<?= $is_admin ? 5 : 4 ?>"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Patient Action Buttons -->
                    <div style="padding: 12px 0 0; display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px solid var(--border-color); margin-top: 8px;">
                        <a href="patient_bills.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-view">
                            <i class="fas fa-file-invoice"></i> View All Bills
                        </a>
                        <?php if (!$is_admin): ?>
                            <a href="process_payment.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-process">
                                <i class="fas fa-money-bill-wave"></i> Pay All Bills
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state" style="max-width:1400px;margin:0 auto;">
            <i class="fas fa-exclamation-circle text-yellow-500 text-3xl block mb-3"></i>
            <p class="text-lg font-semibold text-gray-500">No Pending Bills Found</p>
            <p class="sub">Check if there are any bills with status 'pending' or 'partial'</p>
            <p class="text-xs text-gray-400 mt-2">Debug: Total bills in query: <?= $total_bills_count ?></p>
            <p class="text-xs text-gray-400">Branch ID: <?= $selected_branch_id ?></p>
            <?php if ($filter !== 'all'): ?>
                <p class="text-xs text-gray-400">Filter: <?= $filter ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pending Bills
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- GLOBAL STATS AUTO-UPDATE -->
<script src="/dispensary_system/frontend/assets/js/cashier_global_stats.js"></script>

<!-- PAGE-SPECIFIC JAVASCRIPT -->
<script>
    // ================================================================
    // TOGGLE PATIENT BILLS
    // ================================================================
    function togglePatient(patientId) {
        var body = document.getElementById('patient_' + patientId);
        var chevron = document.getElementById('chevron_' + patientId);
        
        if (body) {
            body.classList.toggle('open');
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }

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
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
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
        var filter = '<?= $filter ?>';
        var start_date = '<?= $start_date ?>';
        var end_date = '<?= $end_date ?>';
        if (query.length > 0) {
            window.location.href = 'pending_bills.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&start_date=' + start_date + '&end_date=' + end_date;
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

    console.log('%c⏳ Braick - Pending Bills (Fixed - Supports Integer Status)', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Bills Found: <?= $total_bills_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patients: <?= count($patient_bills) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Balance: <?= $currency ?> <?= number_format($total_pending_amount, 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Status check includes: pending, partial, 0, 1', 'font-size:13px; color:#34D399;');
    <?php if ($total_bills_count > 0): ?>
    console.log('%c✅ Bills found!', 'font-size:13px; color:#34D399;');
    <?php else: ?>
    console.log('%c❌ No bills found. Check database.', 'font-size:13px; color:#DC2626;');
    console.log('%c🔍 Check if bill status is "pending", "partial", 0, or 1', 'font-size:13px; color:#D97706;');
    <?php endif; ?>
    console.log('%c🔄 Auto-update every 3 seconds via cashier_global_stats.js', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>