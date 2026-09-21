<?php
// ================================================================
// FILE: frontend/pages/cashier/partial_payments.php
// CASHIER - PARTIAL PAYMENTS LIST (V3 - FIXED PAID + LARGER ROWS)
// ================================================================
// ✅ FIXED: Paid Amount inachukua kutoka payments table
// ✅ FIXED: Table rows zina height 72px + padding kubwa
// ✅ 8 Summary Cards - Pharmacy vs Cashier kivyake
// ✅ Each visit appears ONLY ONCE - NO DUPLICATES
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
$total_pharmacy_discount = 0;
$total_cashier_discount = 0;
$total_pharmacy_premium = 0;
$total_cashier_premium = 0;
$total_premium = 0;

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
// ✅ GET ONE ROW PER VISIT - WITH REAL PAID FROM PAYMENTS
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
            
            (SELECT b2.id FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             ORDER BY b2.updated_at DESC LIMIT 1) as latest_bill_id,
            
            (SELECT b2.bill_number FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             ORDER BY b2.updated_at DESC LIMIT 1) as latest_bill_number,
            
            (SELECT MAX(b2.updated_at) FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0) as updated_at,
            
            (SELECT MIN(b2.created_at) FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0) as created_at,
            
            (SELECT u2.full_name FROM bills b2 
             LEFT JOIN users u2 ON b2.created_by = u2.id
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             ORDER BY b2.updated_at DESC LIMIT 1) as created_by_name,
            
            -- ✅ Subtotal
            COALESCE((SELECT SUM(b2.subtotal) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as subtotal,
            
            -- ✅ REAL PAID - kutoka PAYMENTS table
            COALESCE((SELECT SUM(p.amount) FROM payments p
                      INNER JOIN bills b2 ON p.bill_id = b2.id
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as paid_amount,
            
            -- ✅ Total discount
            COALESCE((SELECT SUM(b2.total_discount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as total_discount,
            
            -- ✅ PHARMACY DISCOUNT
            COALESCE((SELECT SUM(b2.pharmacy_discount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as pharmacy_discount,
            
            -- ✅ CASHIER DISCOUNT
            COALESCE((SELECT SUM(b2.cashier_discount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as cashier_discount,
            
            -- ✅ PHARMACY PREMIUM
            COALESCE((SELECT SUM(b2.pharmacy_premium) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as pharmacy_premium,
            
            -- ✅ CASHIER PREMIUM
            COALESCE((SELECT SUM(b2.cashier_premium) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as cashier_premium,
            
            -- ✅ Total premium
            COALESCE((SELECT SUM(b2.premium_amount) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as premium_amount,
            
            -- ✅ Balance
            COALESCE((SELECT SUM(b2.balance) FROM bills b2 
                      WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0), 0) as balance,
            
            (SELECT COUNT(*) FROM bills b2 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0) as bill_count,
            
            (SELECT COUNT(*) FROM bill_items bi 
             INNER JOIN bills b2 ON bi.bill_id = b2.id
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0 AND bi.status != 'cancelled') as item_count,
            
            (SELECT COUNT(*) 
             FROM bill_items bi2 
             INNER JOIN bills b2 ON bi2.bill_id = b2.id
             LEFT JOIN prescriptions pr ON bi2.reference_id = pr.id 
             WHERE b2.visit_id = v.id AND b2.branch_id = ? AND b2.status = 'partial' AND b2.balance > 0
             AND bi2.item_type = 'medication' 
             AND bi2.reference_type = 'prescription'
             AND (pr.status IS NULL OR pr.status NOT IN ('confirmed', 'dispensed'))
            ) as pending_prescriptions,
            
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
    
    $query_params = [];
    
    // 18 subqueries in SELECT
    for ($i = 0; $i < 18; $i++) {
        $query_params[] = $user_branch_id;
    }
    
    $query_params[] = $user_branch_id; // WHERE v.branch_id
    $query_params[] = $user_branch_id; // EXISTS b.branch_id
    
    if ($filter === 'custom' && !empty($start_date) && !empty($end_date)) {
        $query_params[] = $start_date;
        $query_params[] = $end_date;
    }
    
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
    $total_pharmacy_discount = 0;
    $total_cashier_discount = 0;
    $total_pharmacy_premium = 0;
    $total_cashier_premium = 0;
    $total_premium = 0;
    
    foreach ($partial_bills as &$bill) {
        $subtotal = (float)($bill['subtotal'] ?? 0);
        $paid = (float)($bill['paid_amount'] ?? 0);
        $discount = (float)($bill['total_discount'] ?? 0);
        $premium = (float)($bill['premium_amount'] ?? 0);
        $pharmacy_disc = (float)($bill['pharmacy_discount'] ?? 0);
        $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
        $pharmacy_prem = (float)($bill['pharmacy_premium'] ?? 0);
        $cashier_prem = (float)($bill['cashier_premium'] ?? 0);
        
        // ✅ Balance = Subtotal + Pharm Prem + Cashier Prem - Pharm Disc - Cashier Disc - Paid
        $correct_balance = $subtotal + $pharmacy_prem + $cashier_prem - $pharmacy_disc - $cashier_disc - $paid;
        if ($correct_balance < 0) $correct_balance = 0;
        
        $bill['correct_balance'] = $correct_balance;
        $bill['correct_subtotal'] = $subtotal;
        
        $total_subtotal += $subtotal;
        $total_paid += $paid;
        $total_discount += $discount;
        $total_premium += $premium;
        $total_pharmacy_discount += $pharmacy_disc;
        $total_cashier_discount += $cashier_disc;
        $total_pharmacy_premium += $pharmacy_prem;
        $total_cashier_premium += $cashier_prem;
        $total_balance += $correct_balance;
    }
    unset($bill);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $partial_bills = [];
    $total_partial = 0;
    $total_subtotal = 0;
    $total_balance = 0;
    $total_discount = 0;
    $total_paid = 0;
    $total_pharmacy_discount = 0;
    $total_cashier_discount = 0;
    $total_pharmacy_premium = 0;
    $total_cashier_premium = 0;
    $total_premium = 0;
    
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
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --pharm-color: #D97706;
            --pharm-bg: #FEF3C7;
            --cashier-discount-color: #2563EB;
            --cashier-discount-bg: #DBEAFE;
            --cashier-premium-color: #7C3AED;
            --cashier-premium-bg: #EDE9FE;
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
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --pharm-bg: #3D2E0A;
            --cashier-discount-bg: #1E3A5F;
            --cashier-premium-bg: #2A1A3A;
            --premium-bg: #3D2E0A;
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
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
            position: relative;
            overflow: hidden;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 7px 14px;
            border-radius: 9px;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            cursor: pointer;
            white-space: nowrap;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ✅ 8 SUMMARY CARDS */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            min-height: 95px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-card .card-label {
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            line-height: 1.15;
        }
        
        .summary-card .card-value {
            font-size: 1.2rem;
            font-weight: 800;
            display: block;
            margin-top: 4px;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }
        
        .summary-card .card-sub {
            font-size: 0.5rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 3px;
            font-weight: 500;
        }
        
        .summary-card.total-card { border-color: var(--primary); }
        .summary-card.total-card::before { background: var(--primary); }
        .summary-card.total-card .card-value { color: var(--primary); }
        
        .summary-card.pharm-discount-card { 
            border-color: var(--pharm-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(217, 119, 6, 0.04) 100%);
        }
        .summary-card.pharm-discount-card::before { background: var(--pharm-color); }
        .summary-card.pharm-discount-card .card-value { color: var(--pharm-color); }
        .summary-card.pharm-discount-card .card-label { color: var(--pharm-color); }
        
        .summary-card.cashier-discount-card { 
            border-color: var(--cashier-discount-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 99, 235, 0.04) 100%);
        }
        .summary-card.cashier-discount-card::before { background: var(--cashier-discount-color); }
        .summary-card.cashier-discount-card .card-value { color: var(--cashier-discount-color); }
        .summary-card.cashier-discount-card .card-label { color: var(--cashier-discount-color); }
        
        .summary-card.pharm-premium-card { 
            border-color: #F59E0B;
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(245, 158, 11, 0.04) 100%);
        }
        .summary-card.pharm-premium-card::before { background: #F59E0B; }
        .summary-card.pharm-premium-card .card-value { color: #F59E0B; }
        .summary-card.pharm-premium-card .card-label { color: #F59E0B; }
        
        .summary-card.cashier-premium-card { 
            border-color: var(--cashier-premium-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(124, 58, 237, 0.04) 100%);
        }
        .summary-card.cashier-premium-card::before { background: var(--cashier-premium-color); }
        .summary-card.cashier-premium-card .card-value { color: var(--cashier-premium-color); }
        .summary-card.cashier-premium-card .card-label { color: var(--cashier-premium-color); }
        
        .summary-card.paid-card { border-color: var(--success); }
        .summary-card.paid-card::before { background: var(--success); }
        .summary-card.paid-card .card-value { color: var(--success); }
        
        .summary-card.remaining-card { border-color: var(--danger); }
        .summary-card.remaining-card::before { background: var(--danger); }
        .summary-card.remaining-card .card-value { color: var(--danger); }
        
        .summary-card.premium-card { border-color: var(--premium-color); }
        .summary-card.premium-card::before { background: var(--premium-color); }
        .summary-card.premium-card .card-value { color: var(--premium-color); }
        
        /* FILTER SECTION */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-btn {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
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
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group .filter-label {
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .date-picker-group .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.72rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
        }
        
        .date-picker-group .btn-apply {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            background: var(--success);
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 22px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* TABLE SCROLL */
        .table-scroll-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            justify-content: flex-end;
        }
        
        .table-scroll-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }
        
        .table-scroll-btn:hover {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .table-wrap {
            overflow-x: auto;
            scroll-behavior: smooth;
        }
        
        .table-wrap::-webkit-scrollbar { height: 8px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        /* ✅ TABLE - LARGER ROWS */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 1500px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 14px 12px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: linear-gradient(135deg, #064E3B, #065F46, #0D9488);
            border-bottom: 3px solid #14B8A6;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        /* ✅ LARGER ROWS - 72px height */
        .data-table tbody tr {
            height: 72px;
            transition: background 0.2s ease;
        }
        
        .data-table tbody td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            line-height: 1.5;
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
        
        .row-number-cell {
            text-align: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--success);
            background: var(--success-bg);
            font-family: 'Courier New', monospace;
            width: 45px;
            min-width: 45px;
            padding: 16px 8px !important;
        }
        
        .col-subtotal { color: var(--primary); font-weight: 700; }
        .col-paid { color: var(--success); font-weight: 700; }
        .col-pharm-disc { color: var(--pharm-color); font-weight: 700; }
        .col-cashier-disc { color: var(--cashier-discount-color); font-weight: 700; }
        .col-pharm-prem { color: #F59E0B; font-weight: 700; }
        .col-cashier-prem { color: var(--cashier-premium-color); font-weight: 700; }
        .col-balance { color: var(--danger); font-weight: 700; }
        .col-balance.zero { color: var(--success); }
        .col-dash { color: var(--text-secondary); font-size: 0.7rem; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.58rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.partial { background: #FEF3C7; color: #D97706; }
        .status-badge.locked { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }
        
        .locked-tag {
            background: #DC2626;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.5rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .bill-count-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.5rem;
            font-weight: 600;
            background: #0B5ED7;
            color: white;
            margin-left: 4px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.62rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-2px); }
        
        .btn-sm { padding: 6px 10px; font-size: 0.62rem; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.2rem; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .card { padding: 12px 14px; }
            .data-table tbody tr { height: auto; }
            .data-table tbody td { padding: 12px 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .summary-cards { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.6rem; min-width: 1300px; }
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

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Partial Payments
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <span class="header-badge">
                    <i class="fas fa-filter"></i> PARTIAL ONLY
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-percent"></i>
                Bills with partial payments in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_partial ?> Visits
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="table-scroll-btn" id="scrollLeftBtn" title="Scroll Left" style="background:rgba(255,255,255,0.15);color:white;border-color:rgba(255,255,255,0.2);">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="table-scroll-btn" id="scrollRightBtn" title="Scroll Right" style="background:rgba(255,255,255,0.15);color:white;border-color:rgba(255,255,255,0.2);">
                <i class="fas fa-chevron-right"></i>
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ✅ 8 SUMMARY CARDS - ROW 1 -->
    <div class="summary-cards">
        <div class="summary-card total-card">
            <span class="card-label">📋 Total Visits</span>
            <span class="card-value"><?= $total_partial ?></span>
            <span class="card-sub">Partial bills</span>
        </div>
        
        <div class="summary-card pharm-discount-card">
            <span class="card-label">🏷️ Pharm Discount</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_pharmacy_discount, 0) ?></span>
            <span class="card-sub">From Pharmacy</span>
        </div>
        
        <div class="summary-card cashier-discount-card">
            <span class="card-label">🏷️ Cashier Discount</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_cashier_discount, 0) ?></span>
            <span class="card-sub">From Cashier</span>
        </div>
        
        <div class="summary-card pharm-premium-card">
            <span class="card-label">👑 Pharm Premium</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_pharmacy_premium, 0) ?></span>
            <span class="card-sub">From Pharmacy</span>
        </div>
        
        <!-- ✅ ROW 2 -->
        <div class="summary-card cashier-premium-card">
            <span class="card-label">👑 Cashier Premium</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_cashier_premium, 0) ?></span>
            <span class="card-sub">From Cashier</span>
        </div>
        
        <div class="summary-card paid-card">
            <span class="card-label">✅ Paid Amount</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_paid, 0) ?></span>
            <span class="card-sub">
                <?php if ($total_paid > 0): ?>
                    <?= $total_partial ?> visit(s) with payments
                <?php else: ?>
                    No payments yet
                <?php endif; ?>
            </span>
        </div>
        
        <div class="summary-card remaining-card">
            <span class="card-label">📊 Remaining</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_balance, 0) ?></span>
            <span class="card-sub">Total balance</span>
        </div>
        
        <div class="summary-card premium-card">
            <span class="card-label">💰 Subtotal</span>
            <span class="card-value"><?= $currency ?> <?= number_format($total_subtotal, 0) ?></span>
            <span class="card-sub">Total subtotal</span>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;">
            <div class="filter-group">
                <span class="filter-label"><i class="fas fa-clock"></i> Filter:</span>
                <a href="?filter=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=today<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?filter=week<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">7D</a>
                <a href="?filter=month<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">1M</a>
                <a href="?filter=3months<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">3M</a>
                <a href="?filter=6months<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">6M</a>
                <a href="?filter=year<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">1Y</a>
            </div>
            <div class="date-picker-group">
                <form method="GET" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <input type="hidden" name="filter" value="custom">
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    <span style="color:var(--text-secondary);font-size:0.65rem;">→</span>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    <button type="submit" class="btn-apply">Apply</button>
                </form>
            </div>
        </div>
    </div>

    <!-- PARTIAL BILLS TABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:#D97706;"></i> Partial Payment Bills
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(<?= $total_partial ?> visits)</span>
            </h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <span style="font-size:0.65rem;color:var(--text-secondary);">
                    <i class="fas fa-clock"></i> <?= date('h:i:s A') ?>
                </span>
            </div>
        </div>
        
        <div class="table-scroll-controls">
            <button class="table-scroll-btn" id="tableScrollLeft" title="Scroll Left">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span style="font-size:0.55rem;color:var(--text-secondary);">
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
                        <th style="text-align:center;width:45px;">#</th>
                        <th style="min-width:170px;">Visit #</th>
                        <th style="min-width:150px;">Patient</th>
                        <th style="min-width:100px;">Patient ID</th>
                        <th style="text-align:right;min-width:95px;">Subtotal</th>
                        <th style="text-align:right;min-width:85px;">Pharm Disc</th>
                        <th style="text-align:right;min-width:85px;">Cashier Disc</th>
                        <th style="text-align:right;min-width:85px;">Pharm Prem</th>
                        <th style="text-align:right;min-width:85px;">Cashier Prem</th>
                        <th style="text-align:right;min-width:95px;">Paid</th>
                        <th style="text-align:right;min-width:95px;">Remaining</th>
                        <th style="text-align:center;min-width:80px;">Status</th>
                        <th style="min-width:110px;">Updated</th>
                        <th style="text-align:center;min-width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($partial_bills) && count($partial_bills) > 0): ?>
                        <?php $i = 1; foreach ($partial_bills as $bill): 
                            $subtotal = (float)($bill['subtotal'] ?? 0);
                            $paid = (float)($bill['paid_amount'] ?? 0);
                            $pharmacy_disc = (float)($bill['pharmacy_discount'] ?? 0);
                            $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
                            $pharmacy_prem = (float)($bill['pharmacy_premium'] ?? 0);
                            $cashier_prem = (float)($bill['cashier_premium'] ?? 0);
                            $bill_count = (int)($bill['bill_count'] ?? 1);
                            
                            $correct_balance = $subtotal + $pharmacy_prem + $cashier_prem - $pharmacy_disc - $cashier_disc - $paid;
                            if ($correct_balance < 0) $correct_balance = 0;
                            
                            $has_pending_prescriptions = ($bill['pending_prescriptions'] ?? 0) > 0;
                            $is_locked = $has_pending_prescriptions;
                            $row_class = $is_locked ? 'locked-row' : '';
                            
                            $display_number = !empty($bill['visit_number']) 
                                ? $bill['visit_number'] 
                                : $bill['latest_bill_number'];
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td class="row-number-cell"><?= $i++ ?></td>
                                <td>
                                    <span style="font-weight:700;font-family:monospace;color:var(--primary);font-size:0.7rem;">
                                        <?= htmlspecialchars($display_number ?? 'N/A') ?>
                                    </span>
                                    <?php if ($bill_count > 1): ?>
                                        <span class="bill-count-badge"><?= $bill_count ?> Bills</span>
                                    <?php endif; ?>
                                    <?php if ($is_locked): ?>
                                        <span class="locked-tag" style="display:block;margin-top:4px;font-size:0.5rem;">
                                            <i class="fas fa-lock"></i> <?= $bill['pending_prescriptions'] ?> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:0.75rem;"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:2px;"><?= htmlspecialchars($bill['phone'] ?? 'No phone') ?></div>
                                </td>
                                <td>
                                    <span style="font-size:0.68rem;font-family:monospace;"><?= htmlspecialchars($bill['patient_id_number'] ?? 'N/A') ?></span>
                                </td>
                                
                                <td style="text-align:right;" class="col-subtotal">
                                    <?= $currency ?> <?= number_format($subtotal, 0) ?>
                                </td>
                                
                                <td style="text-align:right;">
                                    <?php if ($pharmacy_disc > 0): ?>
                                        <span class="col-pharm-disc"><?= $currency ?> <?= number_format($pharmacy_disc, 0) ?></span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:right;">
                                    <?php if ($cashier_disc > 0): ?>
                                        <span class="col-cashier-disc"><?= $currency ?> <?= number_format($cashier_disc, 0) ?></span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:right;">
                                    <?php if ($pharmacy_prem > 0): ?>
                                        <span class="col-pharm-prem"><?= $currency ?> <?= number_format($pharmacy_prem, 0) ?></span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:right;">
                                    <?php if ($cashier_prem > 0): ?>
                                        <span class="col-cashier-prem"><?= $currency ?> <?= number_format($cashier_prem, 0) ?></span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:right;" class="col-paid">
                                    <?= $currency ?> <?= number_format($paid, 0) ?>
                                </td>
                                
                                <td style="text-align:right;">
                                    <span class="col-balance <?= $correct_balance <= 0 ? 'zero' : '' ?>">
                                        <?= $currency ?> <?= number_format($correct_balance, 0) ?>
                                    </span>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($is_locked): ?>
                                        <span class="status-badge locked">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge partial">Partial</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.68rem;">
                                        <?= isset($bill['updated_at']) ? date('d/m/Y', strtotime($bill['updated_at'])) : 'N/A' ?>
                                    </span>
                                    <br>
                                    <span style="font-size:0.58rem;color:var(--text-secondary);">
                                        <?= isset($bill['updated_at']) ? date('h:i A', strtotime($bill['updated_at'])) : '' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                        <a href="view_bill.php?id=<?= $bill['latest_bill_id'] ?>" class="btn btn-primary btn-sm" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($is_locked): ?>
                                            <span class="btn btn-sm" style="background:#DC2626;color:white;cursor:not-allowed;opacity:0.6;" title="Prescription pending">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                        <?php elseif ($correct_balance > 0): ?>
                                            <a href="make_payment.php?bill_id=<?= $bill['latest_bill_id'] ?>" class="btn btn-success btn-sm" title="Pay Bill">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="btn btn-sm" style="background:#059669;color:white;cursor:default;" title="Paid">
                                                <i class="fas fa-check-circle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                                <i class="fas fa-check-circle" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--success);"></i>
                                <p style="font-size:1rem;font-weight:600;">No partial payment bills</p>
                                <p style="font-size:0.8rem;margin-top:4px;">
                                    <?php if ($filter !== 'all'): ?>
                                        No partial bills found for the selected date range
                                    <?php else: ?>
                                        All bills are fully paid or pending
                                    <?php endif; ?>
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
            <span>|</span> Partial Payments (Pharmacy vs Cashier)
            <span>|</span>
            <span>👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span>|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?>
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

<script>
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
    
    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        syncDarkMode();
    })();

    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            btn.disabled = true;
        }
        setTimeout(function() { window.location.reload(); }, 800);
    }

    console.log('%c✅ Braick - Partial Payments V3 (FIXED PAID + LARGER ROWS)', 'font-size:16px; font-weight:bold; color:#D97706;');
    console.log('%c✅ Paid Amount inachukua kutoka payments table', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Table rows: 72px height', 'font-size:12px; color:#34D399;');
    console.log('%c📋 Total Visits: <?= $total_partial ?>', 'font-size:12px; color:#64748B;');
    console.log('%c✅ Total Paid: <?= number_format($total_paid, 0) ?>', 'font-size:12px; color:#059669;');
</script>

</body>
</html>