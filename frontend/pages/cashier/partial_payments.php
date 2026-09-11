<?php
// ================================================================
// FILE: frontend/pages/cashier/partial_payments.php
// CASHIER - PARTIAL PAYMENTS LIST (FULLY FIXED - NO DUPLICATES)
// ✅ FIXED: Only status = 'partial' bills are shown
// ✅ FIXED: Each visit appears ONLY ONCE - NO DUPLICATES
// ✅ FIXED: Balance = subtotal - paid - discount
// ✅ ADDED: Scroll Left/Right buttons in header
// ✅ ADDED: Premium from bills.premium_amount column
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET ADMIN CONTACT NUMBERS
// ================================================================
$admin_phones = [];
try {
    $stmt = $db->prepare("
        SELECT phone FROM users 
        WHERE role = 'admin' AND branch_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute([$user_branch_id]);
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $admin_phones = [];
}

// ================================================================
// GET BRANCH PHONE
// ================================================================
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

$message = '';
$message_type = '';
$currency = 'TSh';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$partial_bills = [];
$total_partial = 0;
$total_subtotal = 0;
$total_balance = 0;
$total_discount = 0;
$total_paid = 0;
$total_premium = 0;
$total_pharmacy_discount = 0;
$total_cashier_discount = 0;
$total_discount_amount = 0;

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {
    $currency = 'TSh';
}

// ================================================================
// BUILD DATE FILTER
// ================================================================
$date_condition = "";
$params = [$user_branch_id];

switch ($filter) {
    case 'today':
        $date_condition = "AND DATE(b.updated_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "AND DATE(b.updated_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        break;
    default:
        $date_condition = "";
        break;
}

// ================================================================
// BUILD SEARCH CONDITION
// ================================================================
$search_condition = "";
if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ================================================================
// ✅ FULLY FIXED: Get ONE ROW PER VISIT using subquery
// This ensures NO DUPLICATES regardless of how many bills exist per visit
// ================================================================
try {
    $sql = "
        SELECT 
            v.id as visit_id,
            v.visit_number,
            v.visit_type,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_id_number,
            p.phone,
            
            -- ✅ Get ONE representative bill per visit (latest)
            (SELECT b2.id FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             ORDER BY b2.updated_at DESC LIMIT 1) as latest_bill_id,
            
            (SELECT b2.bill_number FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             ORDER BY b2.updated_at DESC LIMIT 1) as latest_bill_number,
            
            -- ✅ Latest update time
            (SELECT MAX(b2.updated_at) FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0) as updated_at,
            
            -- ✅ Created at
            (SELECT MIN(b2.created_at) FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0) as created_at,
            
            -- ✅ Created by name
            (SELECT u2.full_name FROM bills b2 
             LEFT JOIN users u2 ON b2.created_by = u2.id
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             ORDER BY b2.updated_at DESC LIMIT 1) as created_by_name,
            
            -- ✅ Aggregate amounts from ALL partial bills in this visit
            COALESCE((SELECT SUM(b2.subtotal) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as subtotal,
            
            COALESCE((SELECT SUM(b2.paid_amount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as paid_amount,
            
            COALESCE((SELECT SUM(b2.total_discount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as total_discount,
            
            COALESCE((SELECT SUM(b2.cashier_discount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as cashier_discount,
            
            COALESCE((SELECT SUM(b2.discount_amount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as pharmacy_discount,
            
            COALESCE((SELECT SUM(b2.premium_amount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as premium_amount,
            
            COALESCE((SELECT SUM(b2.balance) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as balance,
            
            -- ✅ Count of partial bills in this visit
            (SELECT COUNT(*) FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0) as bill_count,
            
            -- ✅ Items count
            (SELECT COUNT(*) FROM bill_items bi 
             INNER JOIN bills b2 ON bi.bill_id = b2.id
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0 AND bi.status != 'cancelled') as item_count,
            
            -- ✅ Pending prescriptions
            (SELECT COUNT(*) 
             FROM bill_items bi2 
             INNER JOIN bills b2 ON bi2.bill_id = b2.id
             LEFT JOIN prescriptions pr ON bi2.reference_id = pr.id 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             AND bi2.item_type = 'medication' 
             AND bi2.reference_type = 'prescription'
             AND (pr.status IS NULL OR pr.status NOT IN ('confirmed', 'dispensed'))
            ) as pending_prescriptions,
            
            -- ✅ Confirmed prescriptions
            (SELECT COUNT(*) 
             FROM bill_items bi2 
             INNER JOIN bills b2 ON bi2.bill_id = b2.id
             LEFT JOIN prescriptions pr ON bi2.reference_id = pr.id 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             AND bi2.item_type = 'medication' 
             AND bi2.reference_type = 'prescription'
             AND pr.status IN ('confirmed', 'dispensed')
            ) as confirmed_prescriptions
            
        FROM visits v
        INNER JOIN patients p ON v.patient_id = p.id
        WHERE v.branch_id = ?
        AND EXISTS (
            SELECT 1 FROM bills b 
            WHERE b.visit_id = v.id 
            AND b.branch_id = ? 
            AND b.status = 'partial' 
            AND b.balance > 0
            $date_condition
        )
        $search_condition
        ORDER BY updated_at DESC
    ";
    
    // Build params array in correct order for all the ? placeholders
    $query_params = [];
    
    // For each subquery in SELECT (count them):
    // latest_bill_id, latest_bill_number, updated_at, created_at, created_by_name,
    // subtotal, paid_amount, total_discount, cashier_discount, pharmacy_discount,
    // premium_amount, balance, bill_count, item_count, pending_prescriptions, confirmed_prescriptions
    // = 16 subqueries in SELECT
    for ($i = 0; $i < 16; $i++) {
        $query_params[] = $user_branch_id;
    }
    
    // For WHERE v.branch_id = ?
    $query_params[] = $user_branch_id;
    
    // For EXISTS b.branch_id = ?
    $query_params[] = $user_branch_id;
    
    // Add date condition params
    if ($filter === 'custom' && !empty($start_date) && !empty($end_date)) {
        $query_params[] = $start_date;
        $query_params[] = $end_date;
    }
    
    // Add search condition params
    if (!empty($search)) {
        $query_params[] = "%$search%";
        $query_params[] = "%$search%";
        $query_params[] = "%$search%";
        $query_params[] = "%$search%";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($query_params);
    $partial_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ✅ CALCULATE TOTALS
    // ================================================================
    $total_partial = count($partial_bills);
    $total_subtotal = 0;
    $total_balance = 0;
    $total_discount = 0;
    $total_paid = 0;
    $total_premium = 0;
    $total_pharmacy_discount = 0;
    $total_cashier_discount = 0;
    $total_discount_amount = 0;
    
    foreach ($partial_bills as &$bill) {
        $subtotal = (float)($bill['subtotal'] ?? 0);
        $paid = (float)($bill['paid_amount'] ?? 0);
        $discount = (float)($bill['total_discount'] ?? 0);
        $premium = (float)($bill['premium_amount'] ?? 0);
        $pharmacy_disc = (float)($bill['pharmacy_discount'] ?? 0);
        $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
        
        // ✅ Balance = Subtotal - Paid - Discount
        $correct_balance = $subtotal - $paid - $discount;
        if ($correct_balance < 0) $correct_balance = 0;
        
        $bill['correct_balance'] = $correct_balance;
        $bill['correct_subtotal'] = $subtotal;
        
        $total_subtotal += $subtotal;
        $total_paid += $paid;
        $total_discount += $discount;
        $total_premium += $premium;
        $total_pharmacy_discount += $pharmacy_disc;
        $total_cashier_discount += $cashier_disc;
        $total_balance += $correct_balance;
    }
    unset($bill);
    
    error_log("📊 Partial Payments (Fixed - No Duplicates):");
    error_log("  Total Unique Visits: " . $total_partial);
    error_log("  Total Subtotal: " . $total_subtotal);
    error_log("  Total Paid: " . $total_paid);
    error_log("  Total Discount: " . $total_discount);
    error_log("  Total Premium: " . $total_premium);
    error_log("  Total Balance: " . $total_balance);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $partial_bills = [];
    $total_partial = 0;
    $total_subtotal = 0;
    $total_balance = 0;
    $total_discount = 0;
    $total_paid = 0;
    $total_premium = 0;
    $total_pharmacy_discount = 0;
    $total_cashier_discount = 0;
    $total_discount_amount = 0;
    
    error_log("❌ Partial Payments Error: " . $e->getMessage());
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partial Payments - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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
            --premium-color: #D97706;
            --premium-bg: #FEF3C7;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
            --deep-green: #065F46;
            --deep-green-dark: #064E3B;
            --deep-green-light: #0D9488;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
            --deep-green: #0D9488;
            --deep-green-dark: #0F766E;
            --deep-green-light: #14B8A6;
            --premium-bg: #3D2E0A;
        }

        [data-theme="dark"] .bg-white { background-color: #1E293B !important; }
        [data-theme="dark"] .text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .text-gray-900 { color: #F1F5F9 !important; }
        [data-theme="dark"] .border-gray-200 { border-color: #334155 !important; }
        [data-theme="dark"] .bg-gray-50 { background-color: #1E293B !important; }
        [data-theme="dark"] .bg-gray-100 { background-color: #2D3748 !important; }
        [data-theme="dark"] .shadow { box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-md { box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-lg { box-shadow: 0 10px 25px rgba(0,0,0,0.4) !important; }
        [data-theme="dark"] .filter-btn { border-color: #334155; color: #94A3B8; }
        [data-theme="dark"] .filter-btn:hover { border-color: #34D399; color: #34D399; background: rgba(5, 150, 105, 0.15); }
        [data-theme="dark"] .filter-btn.active { background: #059669; color: white; border-color: #059669; }
        [data-theme="dark"] .filter-btn.active:hover { background: #047857; border-color: #047857; }
        [data-theme="dark"] .date-picker-group .form-control { background: #1E293B; color: #F1F5F9; border-color: #334155; }
        [data-theme="dark"] .date-picker-group .form-control:focus { border-color: #34D399; box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1); }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1A3A2A; }
        [data-theme="dark"] .data-table td { border-bottom-color: #334155; }
        [data-theme="dark"] .card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .card:hover { border-color: #059669; }
        [data-theme="dark"] .page-header { background: linear-gradient(135deg, #059669, #047857) !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .toast-custom.success { background: #059669; }
        [data-theme="dark"] .toast-custom.error { background: #DC2626; }
        [data-theme="dark"] .header-badge { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); }
        [data-theme="dark"] .header-badge.premium { background: rgba(251, 191, 36, 0.2); border-color: rgba(251, 191, 36, 0.2); color: #FCD34D; }
        [data-theme="dark"] .status-badge.partial { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.locked { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
        [data-theme="dark"] .text-gray-400 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-500 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-600 { color: #94A3B8 !important; }
        [data-theme="dark"] .font-mono.text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .font-semibold.text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .card-title { color: #F1F5F9 !important; }
        [data-theme="dark"] .footer .footer-brand { color: #34D399; }
        [data-theme="dark"] .footer .text-gray-300 { color: #475569 !important; }
        [data-theme="dark"] .toast-custom { box-shadow: 0 20px 25px rgba(0,0,0,0.4); }
        [data-theme="dark"] .role-badge-display { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .branch-badge-display { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .data-table thead th { 
            background: linear-gradient(135deg, #064E3B, #065F46, #0D9488) !important;
        }
        [data-theme="dark"] .scroll-btn {
            background: rgba(255,255,255,0.1) !important;
            color: #34D399 !important;
        }
        [data-theme="dark"] .scroll-btn:hover {
            background: rgba(255,255,255,0.2) !important;
        }
        [data-theme="dark"] .premium-badge {
            background: #3D2E0A !important;
            color: #F59E0B !important;
            border-color: #D97706 !important;
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
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
        
        .page-header .page-subtitle strong { color: white; font-weight: 600; }
        
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
        
        .page-header .header-badge.locked {
            background: rgba(239, 68, 68, 0.3);
            border-color: rgba(239, 68, 68, 0.3);
            color: #F87171;
        }
        .page-header .header-badge.confirmed {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.2);
            color: #34D399;
        }
        .page-header .header-badge.premium {
            background: rgba(251, 191, 36, 0.3);
            border-color: rgba(251, 191, 36, 0.2);
            color: #FCD34D;
        }
        .page-header .header-badge.partial-only {
            background: rgba(217, 119, 6, 0.35);
            border-color: rgba(217, 119, 6, 0.3);
            color: #FBBF24;
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
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .scroll-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .scroll-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.1);
            border-color: rgba(255,255,255,0.6);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        
        .scroll-btn:active {
            transform: scale(0.95);
        }
        
        .scroll-btn i {
            font-size: 1rem;
        }
        
        .scroll-indicator {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.6);
            padding: 4px 10px;
            background: rgba(255,255,255,0.08);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            white-space: nowrap;
        }
        
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            max-width: 1200px;
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
        
        .filter-btn i { margin-right: 4px; }
        
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
        
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }
        
        .card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table-wrap {
            overflow-x: auto;
            scroll-behavior: smooth;
            position: relative;
        }
        
        .table-scroll-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            justify-content: flex-end;
        }
        
        .table-scroll-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .table-scroll-btn:hover {
            background: var(--success);
            color: white;
            border-color: var(--success);
            transform: scale(1.05);
        }
        
        .table-scroll-btn:active {
            transform: scale(0.95);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 1000px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: white;
            background: linear-gradient(135deg, #064E3B, #065F46, #0D9488);
            border-bottom: 3px solid #14B8A6;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table .locked-row td {
            background: var(--danger-bg) !important;
            opacity: 0.85;
        }
        [data-theme="dark"] .data-table .locked-row td {
            background: #3A1A1A !important;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.partial {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-badge.locked {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #DC2626;
        }
        
        .locked-tag {
            background: #DC2626;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .bill-count-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 0.5rem;
            font-weight: 600;
            background: #0B5ED7;
            color: white;
            margin-left: 4px;
        }
        
        .premium-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 0.45rem;
            font-weight: 600;
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #D97706;
        }
        [data-theme="dark"] .premium-badge {
            background: #3D2E0A;
            color: #F59E0B;
            border-color: #D97706;
        }
        
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
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.65rem; 
            border-radius: 6px; 
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
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
        
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: 16px;
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 16px 16px 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
            margin-top: 0;
            padding-top: 28px;
        }
        
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 3px solid #059669;
            margin-bottom: 16px;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #059669;
            letter-spacing: -0.5px;
            margin-top: 4px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .doc-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #059669;
            margin-top: 4px;
            background: #D1FAE5;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .pdf-section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #059669;
            border-bottom: 2px solid #34D399;
            padding-bottom: 4px;
            margin: 6px 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 4px 0;
        }
        
        .pdf-content .pdf-table th {
            background: #059669;
            color: white;
            padding: 4px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid #047857;
        }
        
        .pdf-content .pdf-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 11px;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .pdf-content .pdf-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid #E2E8F0;
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 14px;
            border: 3px solid #059669;
            border-radius: 10px;
            background: #D1FAE5;
            min-width: 150px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #059669;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 10px;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 6px;
            font-size: 12px;
            color: #94A3B8;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; align-items: stretch; }
            .header-actions { justify-content: center; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card { padding: 14px 16px; }
            .scroll-btn { width: 34px; height: 34px; font-size: 0.9rem; }
            .scroll-indicator { font-size: 0.55rem; padding: 2px 8px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .card { padding: 10px 12px; }
            .btn { padding: 4px 8px; font-size: 0.6rem; }
            .data-table { font-size: 0.65rem; min-width: 600px; }
            .scroll-btn { width: 30px; height: 30px; font-size: 0.8rem; }
        }
    </style>
    
    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER WITH SCROLL BUTTONS -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Partial Payments
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <span class="header-badge partial-only">
                    <i class="fas fa-filter"></i> PARTIAL ONLY
                </span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
                <span class="header-badge premium">
                    <i class="fas fa-crown"></i> Premium
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-percent"></i>
                View bills with partial payments in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_partial ?> Partial Bills
                </span>
                
                <span class="header-badge locked">
                    <i class="fas fa-lock"></i> Locked - Wait Pharmacy
                </span>
                <span class="header-badge confirmed">
                    <i class="fas fa-check-circle"></i> Confirmed - Ready to Pay
                </span>
                
                <?php if ($total_premium > 0): ?>
                <span class="header-badge premium">
                    <i class="fas fa-crown"></i>
                    Total Premium: <?= $currency ?> <?= number_format($total_premium, 0) ?>
                </span>
                <?php endif; ?>
                
                <?php if ($total_discount > 0): ?>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FCD34D;">
                    <i class="fas fa-tag"></i>
                    Total Discount: <?= $currency ?> <?= number_format($total_discount, 0) ?>
                </span>
                <?php endif; ?>
                
                <span class="header-badge" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.2);color:#34D399;">
                    <i class="fas fa-info-circle"></i> Only status = PARTIAL
                </span>
            </p>
        </div>
        <div class="header-actions">
            <button class="scroll-btn" id="scrollLeftBtn" title="Scroll Left">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <span class="scroll-indicator" id="scrollIndicator">
                <i class="fas fa-arrows-alt-h"></i> Scroll
            </span>
            
            <button class="scroll-btn" id="scrollRightBtn" title="Scroll Right">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i>
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;">
            <div class="filter-group">
                <span class="filter-label"><i class="fas fa-clock"></i> Filter:</span>
                <a href="?filter=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=today<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?filter=week<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">7 Days</a>
                <a href="?filter=month<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">1 Month</a>
                <a href="?filter=3months<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">3 Months</a>
                <a href="?filter=6months<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">6 Months</a>
                <a href="?filter=year<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">1 Year</a>
            </div>
            <div class="date-picker-group">
                <form method="GET" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <input type="hidden" name="filter" value="custom">
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" placeholder="Start">
                    <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" placeholder="End">
                    <button type="submit" class="btn-apply">Apply</button>
                </form>
            </div>
        </div>
    </div>

    <!-- PARTIAL BILLS TABLE -->
    <div class="card" style="max-width:1200px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:#D97706;"></i> Partial Payment Bills (One Row Per Visit)
                <span class="text-sm font-normal text-gray-400">(<?= $total_partial ?> visits)</span>
            </h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <span class="text-xs text-gray-400">
                    <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
                </span>
                <?php if ($total_discount > 0): ?>
                <span class="text-xs" style="color:#D97706;">
                    <i class="fas fa-tag"></i> Discount: <?= $currency ?> <?= number_format($total_discount, 0) ?>
                </span>
                <?php endif; ?>
                <?php if ($total_premium > 0): ?>
                <span class="text-xs" style="color:#D97706;">
                    <i class="fas fa-crown"></i> Premium: <?= $currency ?> <?= number_format($total_premium, 0) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="table-scroll-controls">
            <button class="table-scroll-btn" id="tableScrollLeft" title="Scroll Left">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span style="font-size:0.6rem;color:var(--text-secondary);">
                <i class="fas fa-arrows-alt-h"></i>
            </span>
            <button class="table-scroll-btn" id="tableScrollRight" title="Scroll Right">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="table-wrap" id="tableWrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="text-align:center;width:40px;">#</th>
                        <th style="min-width:180px;">Visit #</th>
                        <th style="min-width:160px;">Patient</th>
                        <th style="min-width:100px;">Patient ID</th>
                        <th style="text-align:right;min-width:90px;">Subtotal</th>
                        <th style="text-align:right;min-width:90px;">Paid</th>
                        <th style="text-align:right;min-width:90px;">Discount</th>
                        <th style="text-align:right;min-width:90px;">Premium</th>
                        <th style="text-align:right;min-width:90px;">Balance</th>
                        <th style="text-align:center;min-width:80px;">Status</th>
                        <th style="min-width:120px;">Updated</th>
                        <th style="text-align:center;min-width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($partial_bills) && count($partial_bills) > 0): ?>
                        <?php $i = 1; foreach ($partial_bills as $bill): 
                            $subtotal = (float)($bill['subtotal'] ?? 0);
                            $paid = (float)($bill['paid_amount'] ?? 0);
                            $discount = (float)($bill['total_discount'] ?? 0);
                            $premium = (float)($bill['premium_amount'] ?? 0);
                            $bill_count = (int)($bill['bill_count'] ?? 1);
                            
                            $correct_balance = $subtotal - $paid - $discount;
                            if ($correct_balance < 0) $correct_balance = 0;
                            
                            $has_pending_prescriptions = ($bill['pending_prescriptions'] ?? 0) > 0;
                            $is_locked = $has_pending_prescriptions;
                            $row_class = $is_locked ? 'locked-row' : '';
                            $has_premium = $premium > 0;
                            
                            $display_number = !empty($bill['visit_number']) 
                                ? $bill['visit_number'] 
                                : $bill['latest_bill_number'];
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold" style="color:var(--primary);">
                                        <?= htmlspecialchars($display_number ?? 'N/A') ?>
                                    </span>
                                    
                                    <?php if ($bill_count > 1): ?>
                                        <span class="bill-count-badge">
                                            <i class="fas fa-file-invoice"></i> <?= $bill_count ?> Bills
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_locked): ?>
                                        <span class="locked-tag" style="font-size:0.45rem;padding:1px 6px;display:block;margin-top:2px;">
                                            <i class="fas fa-lock"></i> <?= $bill['pending_prescriptions'] ?> Pending
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($has_premium): ?>
                                        <span class="premium-badge" style="display:inline-block;margin-top:2px;font-size:0.45rem;">
                                            <i class="fas fa-crown"></i> Premium: <?= $currency ?> <?= number_format($premium, 0) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['phone'] ?? 'No phone') ?></div>
                                </td>
                                <td>
                                    <span class="text-xs font-mono"><?= htmlspecialchars($bill['patient_id_number'] ?? 'N/A') ?></span>
                                </td>
                                <td style="text-align:right;">
                                    <span class="font-semibold text-gray-800">
                                        <?= $currency ?> <?= number_format($subtotal, 0) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <span class="font-semibold text-green-600">
                                        <?= $currency ?> <?= number_format($paid, 0) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($discount > 0): ?>
                                        <span style="color:#D97706;font-weight:600;">
                                            <?= $currency ?> <?= number_format($discount, 0) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($has_premium): ?>
                                        <span style="color:#D97706;font-weight:600;">
                                            <?= $currency ?> <?= number_format($premium, 0) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <span class="font-semibold <?= $correct_balance > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= $currency ?> <?= number_format($correct_balance, 0) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_locked): ?>
                                        <span class="status-badge locked">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge partial">
                                            Partial
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs">
                                    <?= isset($bill['updated_at']) ? date('d/m/Y', strtotime($bill['updated_at'])) : 'N/A' ?>
                                    <br>
                                    <span class="text-gray-400 text-[0.6rem]">
                                        <?= isset($bill['updated_at']) ? date('h:i A', strtotime($bill['updated_at'])) : '' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="flex flex-wrap gap-1" style="justify-content:center;">
                                        <a href="view_bill.php?id=<?= $bill['latest_bill_id'] ?>" class="btn btn-primary btn-sm" title="View Bill Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($is_locked): ?>
                                            <span class="btn btn-sm" style="background:#DC2626;color:white;cursor:not-allowed;opacity:0.6;" title="Prescription pending confirmation">
                                                <i class="fas fa-lock"></i> Locked
                                            </span>
                                        <?php elseif ($correct_balance > 0): ?>
                                            <a href="make_payment.php?bill_id=<?= $bill['latest_bill_id'] ?>" class="btn btn-success btn-sm" title="Pay Bill">
                                                <i class="fas fa-money-bill-wave"></i> Pay
                                            </a>
                                        <?php else: ?>
                                            <span class="btn btn-sm" style="background:#059669;color:white;cursor:default;" title="Already paid">
                                                <i class="fas fa-check-circle"></i> Paid
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-8 text-gray-400">
                                <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                                <p class="text-lg">No partial payment bills</p>
                                <p class="text-sm">
                                    <?php if ($filter !== 'all'): ?>
                                        No partial bills found for the selected date range
                                    <?php else: ?>
                                        All bills are fully paid or pending
                                    <?php endif; ?>
                                </p>
                                <p style="font-size:0.7rem;margin-top:8px;color:var(--text-secondary);">
                                    <i class="fas fa-info-circle"></i> Only bills with <strong>status = PARTIAL</strong> are shown here.
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Partial Payments (One Row Per Visit)
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception Access</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                Partial Payments Report
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // ================================================================
    // SCROLL BUTTONS
    // ================================================================
    var tableWrap = document.getElementById('tableWrap');
    var scrollLeftBtn = document.getElementById('scrollLeftBtn');
    var scrollRightBtn = document.getElementById('scrollRightBtn');
    var tableScrollLeft = document.getElementById('tableScrollLeft');
    var tableScrollRight = document.getElementById('tableScrollRight');
    
    function scrollTable(direction) {
        if (!tableWrap) return;
        var scrollAmount = 300;
        if (direction === 'left') {
            tableWrap.scrollLeft -= scrollAmount;
        } else {
            tableWrap.scrollLeft += scrollAmount;
        }
    }
    
    if (scrollLeftBtn) scrollLeftBtn.addEventListener('click', function() { scrollTable('left'); });
    if (scrollRightBtn) scrollRightBtn.addEventListener('click', function() { scrollTable('right'); });
    if (tableScrollLeft) tableScrollLeft.addEventListener('click', function() { scrollTable('left'); });
    if (tableScrollRight) tableScrollRight.addEventListener('click', function() { scrollTable('right'); });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft' && e.altKey) {
            e.preventDefault();
            scrollTable('left');
        } else if (e.key === 'ArrowRight' && e.altKey) {
            e.preventDefault();
            scrollTable('right');
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    if (!searchBtn && !searchInput) {
        searchBtn = document.querySelector('.top-nav .search-btn');
        searchInput = document.querySelector('.top-nav #searchInput');
    }
    
    function performSearch() {
        var query = searchInput?.value?.trim() || '';
        var filter = '<?= $filter ?>';
        var start_date = '<?= $start_date ?>';
        var end_date = '<?= $end_date ?>';
        if (query.length > 0) {
            window.location.href = 'partial_payments.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&start_date=' + start_date + '&end_date=' + end_date;
        }
    }
    
    if (searchBtn) searchBtn.addEventListener('click', performSearch);
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
        searchInput.value = '<?= htmlspecialchars($search) ?>';
    }

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
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<span class="spinner"></span>';
            btn.disabled = true;
        }
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }

    // ================================================================
    // PDF GENERATION
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var adminPhones = '<?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>';
        var currency = '<?= $currency ?>';
        var branchName = '<?= htmlspecialchars($user_branch_name) ?>';
        var totalPartial = <?= $total_partial ?>;
        var totalSubtotal = <?= $total_subtotal ?>;
        var totalBalance = <?= $total_balance ?>;
        var totalDiscount = <?= $total_discount ?>;
        var totalPaid = <?= $total_paid ?>;
        var totalPremium = <?= $total_premium ?>;
        var filterLabel = '<?= $filter ?>';
        var filterDisplay = filterLabel === 'all' ? 'All Time' : filterLabel;
        
        var billsHtml = '';
        var counter = 1;
        <?php foreach ($partial_bills as $bill): 
            $subtotal = (float)($bill['subtotal'] ?? 0);
            $paid = (float)($bill['paid_amount'] ?? 0);
            $discount = (float)($bill['total_discount'] ?? 0);
            $premium = (float)($bill['premium_amount'] ?? 0);
            $correct_balance = $subtotal - $paid - $discount;
            if ($correct_balance < 0) $correct_balance = 0;
            $has_pending = ($bill['pending_prescriptions'] ?? 0) > 0;
            $bill_count = (int)($bill['bill_count'] ?? 1);
            $display_number = !empty($bill['visit_number']) ? $bill['visit_number'] : $bill['latest_bill_number'];
        ?>
            billsHtml += `
                <tr>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:12px;">${counter}</td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:12px;font-weight:600;color:#0B5ED7;">
                        <?= htmlspecialchars($display_number) ?>
                        ${<?= $bill_count ?> > 1 ? '<span style="background:#0B5ED7;color:white;padding:0 6px;border-radius:8px;font-size:9px;margin-left:4px;"><?= $bill_count ?> Bills</span>' : ''}
                    </td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:12px;"><strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong><br><span style="font-size:11px;color:#64748B;"><?= htmlspecialchars($bill['patient_id_number'] ?? 'N/A') ?></span></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;">${currency} <?= number_format($subtotal, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:#059669;">${currency} <?= number_format($paid, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:#D97706;">${currency} <?= number_format($discount, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:#D97706;">${currency} <?= number_format($premium, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;color:<?= $correct_balance > 0 ? '#DC2626' : '#059669' ?>;">${currency} <?= number_format($correct_balance, 0) ?></td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:12px;">${<?= $has_pending ? '"🔒 Locked"' : '"🟡 Partial"' ?>}</td>
                    <td style="padding:3px 6px;border-bottom:1px solid #E2E8F0;font-size:12px;"><?= isset($bill['updated_at']) ? date('d/m/Y h:i A', strtotime($bill['updated_at'])) : 'N/A' ?></td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        if (!billsHtml) {
            billsHtml = `<tr><td colspan="10" style="text-align:center;padding:20px;font-size:14px;color:#64748B;">No partial payment bills found</td></tr>`;
        }
        
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;width:auto;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                    <div class="clinic-name">BRAICK DISPENSARY</div>
                    <div class="clinic-sub">Tunajali Afya Yako</div>
                </div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:4px;padding-top:4px;border-top:1px solid #E2E8F0;font-size:0.6rem;color:#64748B;">
                    <span>📞 Admin Contacts: ${adminPhones}</span>
                    <span>🏢 Branch: ${branchName}</span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div class="doc-title">
                    🟡 Partial Payments Report (One Row Per Visit)
                </div>
            </div>
            
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-list"></i> Partial Bills (${totalPartial} visits)</div>
                <div style="font-size:11px;color:#64748B;margin-bottom:4px;">
                    📅 Filter: ${filterDisplay} | 📊 Subtotal: ${currency} ${totalSubtotal.toLocaleString()} | 💰 Paid: ${currency} ${totalPaid.toLocaleString()} | 🏷️ Discount: ${currency} ${totalDiscount.toLocaleString()} | 👑 Premium: ${currency} ${totalPremium.toLocaleString()} | 📊 Balance: ${currency} ${totalBalance.toLocaleString()}
                </div>
                <table class="pdf-table" style="font-size:11px;width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:center;font-size:9px;">#</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:left;font-size:9px;">Visit #</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:left;font-size:9px;">Patient</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:right;font-size:9px;">Subtotal</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:right;font-size:9px;">Paid</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:right;font-size:9px;">Discount</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:right;font-size:9px;">Premium</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:right;font-size:9px;">Balance</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:center;font-size:9px;">Status</th>
                            <th style="background:#059669;color:white;padding:3px 5px;text-align:left;font-size:9px;">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${billsHtml}
                    </tbody>
                </table>
            </div>
            
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Generated by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Partial_Payments_<?= date('Y-m-d') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { 
                mode: ['css', 'legacy']
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c🟡 Braick - Partial Payments (FIXED - NO DUPLICATES)', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c✅ FIXED: Each visit appears ONLY ONCE', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Uses subqueries to prevent duplicates', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Shows "X Bills" badge when multiple bills in one visit', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Total Unique Visits: <?= $total_partial ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>