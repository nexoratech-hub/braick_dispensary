<?php
// ================================================================
// FILE: frontend/pages/cashier/view_bill.php
// CASHIER - VIEW BILL DETAILS (V3 - CORRECT SUBTOTAL)
// ================================================================
// ✅ FIXED: Subtotal inachukua kutoka bills.subtotal (sio bill_items)
// ✅ NEW: Summary breakdown kwenye table footer
// ✅ 7 Summary Cards - Pharmacy vs Cashier kivyake
// ✅ Balance = subtotal + pharm_prem + cashier_prem - pharm_disc - cashier_disc - paid
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bill_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
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
    
    // ================================================================
    // ✅ GET BILL DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            b.*,
            b.premium_amount,
            b.premium_note,
            b.discount_amount,
            b.pharmacy_discount,
            b.cashier_discount,
            b.total_discount,
            b.pharmacy_premium,
            b.cashier_premium,
            b.pharmacy_premium_note,
            b.cashier_premium_note,
            b.discount_percent,
            b.subtotal as bill_subtotal,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone,
            p.email,
            p.gender,
            p.date_of_birth,
            p.address,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            u.full_name as doctor_name,
            u2.full_name as created_by_name,
            br.name as branch_name
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users u2 ON b.created_by = u2.id
        LEFT JOIN branches br ON b.branch_id = br.id
        WHERE b.id = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: dashboard.php?error=Bill not found');
        exit;
    }

    // ================================================================
    // GET BILL ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
        ORDER BY id ASC
    ");
    $stmt->execute([$bill_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // ✅ CALCULATE TOTALS - SUBTOTAL KUTOKA bills.subtotal
    // ================================================================
    $total_items = count($items);
    
    // ✅ SUBTOTAL = kutoka bills.subtotal (SAHIHI - base amount)
    $subtotal = (float)($bill['bill_subtotal'] ?? 0);
    
    // Kama bills.subtotal ni 0, tumia SUM ya bill_items
    if ($subtotal == 0) {
        foreach ($items as $item) {
            $subtotal += (float)$item['total_price'];
        }
    }
    
    // ✅ Items total (kwa kuonyesha kwenye table) - inaweza kuwa tofauti na subtotal
    $items_total_display = 0;
    foreach ($items as $item) {
        $items_total_display += (float)$item['total_price'];
    }
    
    // ✅ PHARMACY DISCOUNT
    $pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0 && (float)($bill['discount_amount'] ?? 0) > 0) {
        $pharmacy_discount = (float)$bill['discount_amount'];
    }
    
    // ✅ CASHIER DISCOUNT
    $cashier_discount = (float)($bill['cashier_discount'] ?? 0);
    
    // ✅ TOTAL DISCOUNT
    $total_discount = $pharmacy_discount + $cashier_discount;
    
    $discount_percent = (float)($bill['discount_percent'] ?? 0);
    
    // ✅ PHARMACY PREMIUM
    $pharmacy_premium = (float)($bill['pharmacy_premium'] ?? 0);
    if ($pharmacy_premium == 0 && (float)($bill['premium_amount'] ?? 0) > 0) {
        $pharmacy_premium = (float)$bill['premium_amount'];
    }
    $pharmacy_premium_note = $bill['pharmacy_premium_note'] ?? '';
    
    // ✅ CASHIER PREMIUM
    $cashier_premium = (float)($bill['cashier_premium'] ?? 0);
    $cashier_premium_note = $bill['cashier_premium_note'] ?? '';
    
    // ✅ TOTAL PREMIUM
    $premium_amount = $pharmacy_premium + $cashier_premium;
    $premium_note = $bill['premium_note'] ?? '';
    $has_premium = $premium_amount > 0;
    $has_pharmacy_premium = $pharmacy_premium > 0;
    $has_cashier_premium = $cashier_premium > 0;
    
    // ✅ PAID
    $paid_amount = (float)$bill['paid_amount'];
    
    // ✅ FORMULA SAHIHI:
    // Balance = Subtotal + Pharm Prem + Cashier Prem - Pharm Disc - Cashier Disc - Paid
    $calculated_total = $subtotal + $pharmacy_premium + $cashier_premium 
                      - $pharmacy_discount - $cashier_discount;
    if ($calculated_total < 0) $calculated_total = 0;
    
    $calculated_balance = $calculated_total - $paid_amount;
    if ($calculated_balance < 0) $calculated_balance = 0;
    
    $total_amount = $calculated_total;
    $balance = $calculated_balance;
    $after_discount = $subtotal - $total_discount;

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
    $bill = null;
    $items = [];
    $total_items = 0;
    $subtotal = 0;
    $items_total_display = 0;
    $pharmacy_discount = 0;
    $cashier_discount = 0;
    $total_discount = 0;
    $discount_percent = 0;
    $pharmacy_premium = 0;
    $cashier_premium = 0;
    $premium_amount = 0;
    $pharmacy_premium_note = '';
    $cashier_premium_note = '';
    $premium_note = '';
    $has_premium = false;
    $has_pharmacy_premium = false;
    $has_cashier_premium = false;
    $total_amount = 0;
    $paid_amount = 0;
    $balance = 0;
    $after_discount = 0;
    $currency = 'TSh';
    $admin_phones = [];
    $branch_phone = '';
    error_log("View bill error: " . $e->getMessage());
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-bg: #D1FAE5;
            --success: #059669;
            --success-dark: #047857;
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
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #F0FDF4;
            --table-hover: #D1FAE5;
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #D97706;
            --badge-partial-bg: #DBEAFE;
            --badge-partial-text: #2563EB;
            --badge-paid-bg: #D1FAE5;
            --badge-paid-text: #059669;
            --badge-cancelled-bg: #FEE2E2;
            --badge-cancelled-text: #DC2626;
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
            --radius: 10px;
            --radius-lg: 14px;
            --font-mono: 'JetBrains Mono', monospace;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
            --table-stripe: #1A3A2A;
            --table-hover: #1A4A3A;
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --pharm-bg: #3D2E0A;
            --cashier-discount-bg: #1E3A5F;
            --cashier-premium-bg: #2A1A3A;
            --premium-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
            --badge-pending-bg: #3D2E0A;
            --badge-pending-text: #FBBF24;
            --badge-partial-bg: #1E3A5F;
            --badge-partial-text: #60A5FA;
            --badge-paid-bg: #1A3A2A;
            --badge-paid-text: #34D399;
            --badge-cancelled-bg: #3A1A1A;
            --badge-cancelled-text: #F87171;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
        }
        
        .mono {
            font-family: var(--font-mono) !important;
            font-feature-settings: 'tnum';
            font-variant-numeric: tabular-nums;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 30px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--page-header-bg-from), var(--page-header-bg-to));
            border-radius: var(--radius-lg);
            padding: 20px 28px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 3px 16px var(--page-header-shadow);
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
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
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .page-header .header-badge.premium {
            background: rgba(251, 191, 36, 0.3);
            border: 1px solid rgba(251, 191, 36, 0.2);
            color: #FCD34D;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* ✅ 7 SUMMARY CARDS */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 10px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
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
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-card .card-icon {
            font-size: 1.1rem;
            display: block;
            margin-bottom: 2px;
        }
        
        .summary-card .card-label {
            font-size: 0.5rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            line-height: 1.1;
        }
        
        .summary-card .card-value {
            font-size: 0.95rem;
            font-weight: 800;
            display: block;
            margin-top: 3px;
            font-family: var(--font-mono);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        
        .summary-card .card-sub {
            font-size: 0.45rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
            font-weight: 500;
        }
        
        .summary-card.total-card { border-color: var(--primary); }
        .summary-card.total-card::before { background: var(--primary); }
        .summary-card.total-card .card-value { color: var(--primary); }
        
        .summary-card.paid-card { border-color: var(--success); }
        .summary-card.paid-card::before { background: var(--success); }
        .summary-card.paid-card .card-value { color: var(--success); }
        
        .summary-card.balance-card { border-color: var(--danger); }
        .summary-card.balance-card::before { background: var(--danger); }
        .summary-card.balance-card .card-value { color: var(--danger); }
        .summary-card.balance-card.zero-balance { border-color: var(--success); }
        .summary-card.balance-card.zero-balance::before { background: var(--success); }
        .summary-card.balance-card.zero-balance .card-value { color: var(--success); }
        
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
        
        /* DISCOUNT CARD */
        .discount-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 22px;
            border: 2px solid var(--border-color);
            margin-bottom: 18px;
        }
        
        .discount-card .discount-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .discount-card .discount-title i { color: var(--warning); }
        
        .discount-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }
        
        .discount-grid .discount-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            background: var(--bg-body);
        }
        
        .discount-grid .discount-item.pharm { border-left: 4px solid var(--pharm-color); }
        .discount-grid .discount-item.cashier-disc { border-left: 4px solid var(--cashier-discount-color); }
        .discount-grid .discount-item.cashier-prem { border-left: 4px solid var(--cashier-premium-color); }
        .discount-grid .discount-item.pharm-prem { border-left: 4px solid #F59E0B; }
        
        .discount-grid .discount-item .discount-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .discount-grid .discount-item .discount-value {
            font-size: 0.85rem;
            font-weight: 700;
            font-family: var(--font-mono);
        }
        
        .discount-grid .discount-item.pharm .discount-value { color: var(--pharm-color); }
        .discount-grid .discount-item.cashier-disc .discount-value { color: var(--cashier-discount-color); }
        .discount-grid .discount-item.cashier-prem .discount-value { color: var(--cashier-premium-color); }
        .discount-grid .discount-item.pharm-prem .discount-value { color: #F59E0B; }
        
        /* BILL SUMMARY CARD */
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 18px;
        }
        
        .bill-summary-card .bill-number-large {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--success);
            font-family: var(--font-mono);
        }
        
        .bill-summary-card .bill-status-large {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .bill-summary-card .bill-status-large.pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .bill-summary-card .bill-status-large.partial { background: var(--badge-partial-bg); color: var(--badge-partial-text); }
        .bill-summary-card .bill-status-large.paid { background: var(--badge-paid-bg); color: var(--badge-paid-text); }
        .bill-summary-card .bill-status-large.cancelled { background: var(--badge-cancelled-bg); color: var(--badge-cancelled-text); }
        
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px 24px;
            margin-top: 12px;
        }
        
        .bill-summary-grid .summary-item {
            display: flex;
            flex-direction: column;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .bill-summary-grid .summary-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .bill-summary-grid .summary-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 1px;
        }
        
        .bill-summary-grid .summary-item .value.premium { color: var(--premium-color); }
        
        /* PATIENT INFO */
        .patient-info-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 22px;
            border: 2px solid var(--border-color);
            margin-bottom: 18px;
        }
        
        .patient-info-card .patient-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            background: var(--success);
        }
        
        .patient-info-card .patient-name-large {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .patient-info-card .patient-details {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 3px;
        }
        
        .patient-info-card .patient-details span {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-info-card .patient-details span i {
            color: var(--success);
            width: 15px;
        }
        
        /* TABLE */
        .table-wrapper {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
        }
        
        .table-wrapper .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: var(--gray-50);
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .table-wrapper .table-header {
            background: var(--gray-700);
        }
        
        .table-wrapper .table-header .table-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-wrapper .table-header .table-title i { color: var(--success); }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #ffffff;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-family: var(--font-mono);
            font-size: 0.75rem;
        }
        
        .data-table tbody tr:hover {
            background: var(--table-hover);
        }
        
        /* ✅ SUMMARY ROWS Kwenye Table */
        .summary-row {
            background: var(--gray-50);
            font-weight: 700;
        }
        
        [data-theme="dark"] .summary-row {
            background: var(--gray-700);
        }
        
        .summary-row td {
            padding: 10px 14px !important;
            font-size: 0.8rem !important;
        }
        
        .summary-row.subtotal-row td {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .summary-row.pharm-disc-row td {
            background: var(--pharm-bg);
            color: var(--pharm-color);
        }
        
        .summary-row.cashier-disc-row td {
            background: var(--cashier-discount-bg);
            color: var(--cashier-discount-color);
        }
        
        .summary-row.pharm-prem-row td {
            background: var(--pharm-bg);
            color: #F59E0B;
        }
        
        .summary-row.cashier-prem-row td {
            background: var(--cashier-premium-bg);
            color: var(--cashier-premium-color);
        }
        
        .summary-row.grand-total-row td {
            background: var(--success-bg);
            color: var(--success);
            font-size: 0.95rem !important;
            border-top: 2px solid var(--success);
            font-weight: 800 !important;
        }
        
        .summary-row.paid-row td {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .summary-row.remaining-row td {
            background: var(--danger-bg);
            color: var(--danger);
            font-size: 0.9rem !important;
            font-weight: 800 !important;
        }
        
        .summary-row.remaining-row.zero-balance td {
            background: var(--success-bg);
            color: var(--success);
        }
        
        /* STATUS BADGE */
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        .status-badge.pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .status-badge.partial { background: var(--badge-partial-bg); color: var(--badge-partial-text); }
        .status-badge.paid { background: var(--badge-paid-bg); color: var(--badge-paid-text); }
        .status-badge.cancelled { background: var(--badge-cancelled-bg); color: var(--badge-cancelled-text); }
        
        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 16px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
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
        
        .btn-danger { background: var(--danger); color: white; }
        
        /* PDF MODAL */
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
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
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
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-content {
            background: white;
            padding: 24px 28px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            color: #1E293B;
        }
        
        /* FOOTER */
        .footer {
            padding: 12px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 18px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .summary-cards { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .summary-cards { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 480px) {
            .summary-cards { grid-template-columns: 1fr 1fr; }
            .bill-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Bill Details
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($has_premium): ?>
                    <span class="header-badge premium">
                        <i class="fas fa-crown"></i> Premium: <?= $currency ?> <?= number_format($premium_amount, 0) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-cube"></i> <?= $total_items ?> item(s)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="paid_bills.php" class="btn-outline-light">
                <i class="fas fa-list"></i> Paid Bills
            </a>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($bill): ?>
        
    <!-- ✅ 7 SUMMARY CARDS -->
    <div class="summary-cards animate-fade-in-up">
        <!-- 1. Subtotal -->
        <div class="summary-card total-card">
            <span class="card-icon">📋</span>
            <span class="card-label">Subtotal</span>
            <span class="card-value"><?= $currency ?> <?= number_format($subtotal, 0) ?></span>
            <span class="card-sub">Bill amount</span>
        </div>
        
        <!-- 2. Paid -->
        <div class="summary-card paid-card">
            <span class="card-icon">✅</span>
            <span class="card-label">Paid Amount</span>
            <span class="card-value"><?= $currency ?> <?= number_format($paid_amount, 0) ?></span>
            <span class="card-sub">Total paid</span>
        </div>
        
        <!-- 3. Remaining -->
        <div class="summary-card balance-card <?= $balance <= 0 ? 'zero-balance' : '' ?>">
            <span class="card-icon">⚖️</span>
            <span class="card-label">Remaining</span>
            <span class="card-value"><?= $currency ?> <?= number_format($balance, 0) ?></span>
            <span class="card-sub">
                <?php if ($balance <= 0): ?>
                    ✅ Fully paid
                <?php else: ?>
                    Pending
                <?php endif; ?>
            </span>
        </div>
        
        <!-- 4. Pharmacy Discount -->
        <div class="summary-card pharm-discount-card">
            <span class="card-icon">🏷️</span>
            <span class="card-label">Pharm Disc</span>
            <span class="card-value">
                <?php if ($pharmacy_discount > 0): ?>
                    -<?= $currency ?> <?= number_format($pharmacy_discount, 0) ?>
                <?php else: ?>
                    <?= $currency ?> 0
                <?php endif; ?>
            </span>
            <span class="card-sub">From Pharmacy</span>
        </div>
        
        <!-- 5. Cashier Discount -->
        <div class="summary-card cashier-discount-card">
            <span class="card-icon">🏷️</span>
            <span class="card-label">Cashier Disc</span>
            <span class="card-value">
                <?php if ($cashier_discount > 0): ?>
                    -<?= $currency ?> <?= number_format($cashier_discount, 0) ?>
                <?php else: ?>
                    <?= $currency ?> 0
                <?php endif; ?>
            </span>
            <span class="card-sub">From Cashier</span>
        </div>
        
        <!-- 6. Pharmacy Premium -->
        <div class="summary-card pharm-premium-card">
            <span class="card-icon">👑</span>
            <span class="card-label">Pharm Prem</span>
            <span class="card-value">
                <?php if ($pharmacy_premium > 0): ?>
                    +<?= $currency ?> <?= number_format($pharmacy_premium, 0) ?>
                <?php else: ?>
                    <?= $currency ?> 0
                <?php endif; ?>
            </span>
            <span class="card-sub">From Pharmacy</span>
        </div>
        
        <!-- 7. Cashier Premium -->
        <div class="summary-card cashier-premium-card">
            <span class="card-icon">👑</span>
            <span class="card-label">Cashier Prem</span>
            <span class="card-value">
                <?php if ($cashier_premium > 0): ?>
                    +<?= $currency ?> <?= number_format($cashier_premium, 0) ?>
                <?php else: ?>
                    <?= $currency ?> 0
                <?php endif; ?>
            </span>
            <span class="card-sub">From Cashier</span>
        </div>
    </div>
        
    <!-- DISCOUNT & PREMIUM CARD -->
    <div class="discount-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="discount-title">
            <i class="fas fa-tags"></i>
            Discount & Premium Breakdown
        </div>
        
        <div class="discount-grid">
            <div class="discount-item pharm">
                <span class="discount-label">
                    <i class="fas fa-pills"></i> Pharmacy Discount
                </span>
                <span class="discount-value">
                    <?= $currency ?> <?= number_format($pharmacy_discount, 0) ?>
                </span>
            </div>
            
            <div class="discount-item cashier-disc">
                <span class="discount-label">
                    <i class="fas fa-cash-register"></i> Cashier Discount
                </span>
                <span class="discount-value">
                    <?= $currency ?> <?= number_format($cashier_discount, 0) ?>
                </span>
            </div>
            
            <div class="discount-item pharm-prem">
                <span class="discount-label">
                    <i class="fas fa-crown"></i> Pharmacy Premium
                </span>
                <span class="discount-value">
                    <?= $currency ?> <?= number_format($pharmacy_premium, 0) ?>
                </span>
            </div>
            
            <div class="discount-item cashier-prem">
                <span class="discount-label">
                    <i class="fas fa-crown"></i> Cashier Premium
                </span>
                <span class="discount-value">
                    <?= $currency ?> <?= number_format($cashier_premium, 0) ?>
                </span>
            </div>
        </div>
        
        <!-- Formula Summary -->
        <div style="margin-top:14px;padding:12px 16px;background:var(--bg-body);border-radius:var(--radius);border:1px dashed var(--border-color);font-size:0.72rem;">
            <div style="font-weight:700;color:var(--text-primary);margin-bottom:6px;">
                <i class="fas fa-calculator"></i> Balance Formula:
            </div>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-family:var(--font-mono);font-size:0.7rem;">
                <span style="background:var(--primary-bg);padding:3px 8px;border-radius:5px;color:var(--primary);font-weight:700;">
                    Subtotal: <?= number_format($subtotal, 0) ?>
                </span>
                <span>+</span>
                <span style="background:var(--pharm-bg);padding:3px 8px;border-radius:5px;color:var(--pharm-color);font-weight:700;">
                    Pharm Prem: <?= number_format($pharmacy_premium, 0) ?>
                </span>
                <span>+</span>
                <span style="background:var(--cashier-premium-bg);padding:3px 8px;border-radius:5px;color:var(--cashier-premium-color);font-weight:700;">
                    Cashier Prem: <?= number_format($cashier_premium, 0) ?>
                </span>
                <span>−</span>
                <span style="background:var(--pharm-bg);padding:3px 8px;border-radius:5px;color:var(--pharm-color);font-weight:700;">
                    Pharm Disc: <?= number_format($pharmacy_discount, 0) ?>
                </span>
                <span>−</span>
                <span style="background:var(--cashier-discount-bg);padding:3px 8px;border-radius:5px;color:var(--cashier-discount-color);font-weight:700;">
                    Cashier Disc: <?= number_format($cashier_discount, 0) ?>
                </span>
                <span>−</span>
                <span style="background:var(--success-bg);padding:3px 8px;border-radius:5px;color:var(--success);font-weight:700;">
                    Paid: <?= number_format($paid_amount, 0) ?>
                </span>
                <span>=</span>
                <span style="background:<?= $balance > 0 ? 'var(--danger-bg)' : 'var(--success-bg)' ?>;padding:3px 10px;border-radius:5px;color:<?= $balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:800;">
                    Remaining: <?= number_format($balance, 0) ?>
                </span>
            </div>
        </div>
    </div>
        
    <!-- BILL SUMMARY -->
    <div class="bill-summary-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <div class="bill-number-large">#<?= htmlspecialchars($bill['bill_number']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?>
                </div>
            </div>
            <div>
                <span class="bill-status-large <?= $bill['status'] ?>">
                    <i class="fas <?= $bill['status'] === 'paid' ? 'fa-check-circle' : ($bill['status'] === 'partial' ? 'fa-clock' : 'fa-hourglass-half') ?>"></i>
                    <?= ucfirst($bill['status']) ?>
                </span>
            </div>
        </div>
        
        <div class="bill-summary-grid">
            <div class="summary-item">
                <span class="label">Branch</span>
                <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? $user_branch_name) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Visit Number</span>
                <span class="value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Visit Type</span>
                <span class="value capitalize"><?= htmlspecialchars($bill['visit_type'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Doctor</span>
                <span class="value" style="color:var(--primary);">
                    <?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?>
                </span>
            </div>
            <div class="summary-item">
                <span class="label">Created By</span>
                <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Total Premium</span>
                <span class="value premium"><?= $currency ?> <?= number_format($premium_amount, 0) ?></span>
            </div>
        </div>
    </div>

    <!-- PATIENT INFO -->
    <div class="patient-info-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div class="patient-avatar">
                <?= strtoupper(substr($bill['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <div class="patient-name-large"><?= htmlspecialchars($bill['patient_name'] ?? 'Unknown Patient') ?></div>
                <div class="patient-details">
                    <span><i class="fas fa-hashtag"></i> <?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($bill['email'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-calendar-alt"></i> <?= $bill['date_of_birth'] ? date('M d, Y', strtotime($bill['date_of_birth'])) : 'N/A' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ BILL ITEMS TABLE WITH SUMMARY BREAKDOWN -->
    <div class="table-wrapper animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list-ul"></i>
                Bill Items
                <span style="font-size:0.7rem;color:var(--text-secondary);">(<?= $total_items ?>)</span>
            </div>
            <div style="font-size:0.7rem;color:var(--text-secondary);">
                <i class="fas fa-clock"></i> <?= date('h:i:s A') ?>
            </div>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:35px;text-align:center;">#</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php $counter = 1; foreach ($items as $item): ?>
                            <tr>
                                <td style="text-align:center;"><?= $counter++ ?></td>
                                <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                                <td>
                                    <span style="font-size:0.7rem;color:var(--text-secondary);text-transform:capitalize;">
                                        <?= htmlspecialchars($item['item_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= $item['quantity'] ?? 1 ?></td>
                                <td style="text-align:right;"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="text-align:right;font-weight:600;"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= ($item['status'] ?? 'pending') ?>">
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- ✅ SUBTOTAL ROW -->
                        <tr class="summary-row subtotal-row">
                            <td colspan="5" style="text-align:right;font-size:0.85rem;">
                                📋 SUBTOTAL
                            </td>
                            <td style="text-align:right;font-size:0.9rem;">
                                <?= $currency ?> <?= number_format($subtotal, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        
                        <!-- ✅ PHARMACY DISCOUNT ROW -->
                        <?php if ($pharmacy_discount > 0): ?>
                        <tr class="summary-row pharm-disc-row">
                            <td colspan="5" style="text-align:right;font-size:0.8rem;">
                                <i class="fas fa-pills"></i> Pharmacy Discount
                            </td>
                            <td style="text-align:right;font-size:0.85rem;">
                                -<?= $currency ?> <?= number_format($pharmacy_discount, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- ✅ CASHIER DISCOUNT ROW -->
                        <?php if ($cashier_discount > 0): ?>
                        <tr class="summary-row cashier-disc-row">
                            <td colspan="5" style="text-align:right;font-size:0.8rem;">
                                <i class="fas fa-cash-register"></i> Cashier Discount
                            </td>
                            <td style="text-align:right;font-size:0.85rem;">
                                -<?= $currency ?> <?= number_format($cashier_discount, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- ✅ PHARMACY PREMIUM ROW -->
                        <?php if ($pharmacy_premium > 0): ?>
                        <tr class="summary-row pharm-prem-row">
                            <td colspan="5" style="text-align:right;font-size:0.8rem;">
                                <i class="fas fa-crown"></i> Pharmacy Premium
                            </td>
                            <td style="text-align:right;font-size:0.85rem;">
                                +<?= $currency ?> <?= number_format($pharmacy_premium, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- ✅ CASHIER PREMIUM ROW -->
                        <?php if ($cashier_premium > 0): ?>
                        <tr class="summary-row cashier-prem-row">
                            <td colspan="5" style="text-align:right;font-size:0.8rem;">
                                <i class="fas fa-crown"></i> Cashier Premium
                            </td>
                            <td style="text-align:right;font-size:0.85rem;">
                                +<?= $currency ?> <?= number_format($cashier_premium, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- ✅ GRAND TOTAL ROW -->
                        <tr class="summary-row grand-total-row">
                            <td colspan="5" style="text-align:right;">
                                💰 GRAND TOTAL
                            </td>
                            <td style="text-align:right;">
                                <?= $currency ?> <?= number_format($total_amount, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        
                        <!-- ✅ PAID ROW -->
                        <tr class="summary-row paid-row">
                            <td colspan="5" style="text-align:right;font-size:0.85rem;">
                                ✅ PAID
                            </td>
                            <td style="text-align:right;font-size:0.85rem;">
                                <?= $currency ?> <?= number_format($paid_amount, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        
                        <!-- ✅ REMAINING ROW -->
                        <tr class="summary-row remaining-row <?= $balance <= 0 ? 'zero-balance' : '' ?>">
                            <td colspan="5" style="text-align:right;">
                                ⚖️ REMAINING
                            </td>
                            <td style="text-align:right;">
                                <?= $currency ?> <?= number_format($balance, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:25px 20px;color:var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i> No items found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
        <a href="paid_bills.php" class="btn btn-primary">
            <i class="fas fa-list"></i> Paid Bills
        </a>
        <button onclick="generatePDF()" class="btn btn-outline">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <?php if ($is_admin): ?>
            <button onclick="editBill(<?= $bill_id ?>)" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button onclick="voidBill(<?= $bill_id ?>)" class="btn btn-danger">
                <i class="fas fa-ban"></i> Void
            </button>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <div style="text-align:center;padding:50px 20px;background:var(--bg-card);border-radius:var(--radius-lg);border:2px solid var(--border-color);">
            <i class="fas fa-file-invoice" style="font-size:3.5rem;color:var(--border-color);display:block;margin-bottom:14px;"></i>
            <h3 style="font-size:1.2rem;color:var(--text-primary);margin-bottom:8px;">Bill Not Found</h3>
            <p style="color:var(--text-secondary);font-size:0.9rem;">The bill you are looking for does not exist.</p>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top:16px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span> Bill Details (Pharmacy vs Cashier)
            <span>|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span>|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf"></i>
                Bill PDF - <?= htmlspecialchars($bill['bill_number'] ?? 'Bill') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

<script>
    // DARK MODE
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }

    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    function editBill(billId) {
        if (confirm('Edit this bill?')) {
            window.location.href = 'edit_bill.php?id=' + billId;
        }
    }
    
    function voidBill(billId) {
        if (confirm('Are you sure you want to void this bill?')) {
            fetch('void_bill.php?id=' + billId, { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert('Bill voided successfully');
                        setTimeout(function() { window.location.reload(); }, 1000);
                    } else {
                        alert('Error: ' + (data.message || 'Failed'));
                    }
                });
        }
    }

    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var adminPhones = '<?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>';
        var currency = '<?= $currency ?>';
        var billNumber = '<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>';
        var patientName = '<?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?>';
        var patientId = '<?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?>';
        var patientPhone = '<?= htmlspecialchars($bill['phone'] ?? 'N/A') ?>';
        var patientGender = '<?= htmlspecialchars($bill['gender'] ?? 'N/A') ?>';
        var visitNumber = '<?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>';
        var doctorName = '<?= htmlspecialchars($bill['doctor_name'] ?? 'Not assigned') ?>';
        var billStatus = '<?= ucfirst($bill['status'] ?? 'Pending') ?>';
        var branchName = '<?= htmlspecialchars($bill['branch_name'] ?? $user_branch_name) ?>';
        var createdAt = '<?= date('F d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?>';
        var subtotal = <?= $subtotal ?>;
        var pharmacyDiscount = <?= $pharmacy_discount ?>;
        var cashierDiscount = <?= $cashier_discount ?>;
        var pharmacyPremium = <?= $pharmacy_premium ?>;
        var cashierPremium = <?= $cashier_premium ?>;
        var total = <?= $total_amount ?>;
        var paid = <?= $paid_amount ?>;
        var balance = <?= $balance ?>;
        var totalItems = <?= $total_items ?>;
        
        var itemsHtml = '';
        var counter = 1;
        <?php foreach ($items as $item): ?>
            itemsHtml += `
                <tr>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:12px;">${counter}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;"><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;"><?= htmlspecialchars($item['item_type'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:12px;"><?= $item['quantity'] ?? 1 ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:12px;">${currency} <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600;font-size:12px;">${currency} <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        var html = `
            <div style="text-align:center;padding-bottom:12px;border-bottom:3px solid #059669;margin-bottom:16px;">
                <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                <div style="font-size:1.4rem;font-weight:800;color:#059669;margin-top:4px;">BRAICK DISPENSARY</div>
                <div style="font-size:0.75rem;color:#64748B;">Tunajali Afya Yako</div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:6px;padding-top:6px;border-top:1px solid #E2E8F0;font-size:0.7rem;color:#64748B;">
                    <span>📞 ${adminPhones}</span>
                    <span>🏢 ${branchName}</span>
                    <span>📅 <?= date('F d, Y') ?></span>
                </div>
                <div style="font-size:0.85rem;font-weight:700;color:#059669;margin-top:6px;background:#D1FAE5;padding:4px 16px;border-radius:20px;display:inline-block;">
                    💰 Bill Details - #${billNumber}
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:5px;margin-bottom:12px;">
                <div style="background:#E8F0FE;padding:6px 4px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;">
                    <div style="font-size:12px;font-weight:700;color:#0B5ED7;">${currency} ${subtotal.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">📋 Subtotal</div>
                </div>
                <div style="background:#D1FAE5;padding:6px 4px;border-radius:6px;text-align:center;border:1px solid #059669;">
                    <div style="font-size:12px;font-weight:700;color:#059669;">${currency} ${paid.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">✅ Paid</div>
                </div>
                <div style="background:${balance > 0 ? '#FEE2E2' : '#D1FAE5'};padding:6px 4px;border-radius:6px;text-align:center;border:1px solid ${balance > 0 ? '#DC2626' : '#059669'};">
                    <div style="font-size:12px;font-weight:700;color:${balance > 0 ? '#DC2626' : '#059669'};">${currency} ${balance.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">⚖️ Remain</div>
                </div>
                <div style="background:#FEF3C7;padding:6px 4px;border-radius:6px;text-align:center;border:1px solid #D97706;">
                    <div style="font-size:12px;font-weight:700;color:#D97706;">${currency} ${pharmacyDiscount.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">🏷️ Pharm Disc</div>
                </div>
                <div style="background:#DBEAFE;padding:6px 4px;border-radius:6px;text-align:center;border:1px solid #2563EB;">
                    <div style="font-size:12px;font-weight:700;color:#2563EB;">${currency} ${cashierDiscount.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">🏷️ Cashier Disc</div>
                </div>
                <div style="background:#FEF3C7;padding:6px 4px;border-radius:6px;text-align:center;border:1px solid #D97706;">
                    <div style="font-size:12px;font-weight:700;color:#D97706;">${currency} ${pharmacyPremium.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">👑 Pharm Prem</div>
                </div>
                <div style="background:#EDE9FE;padding:6px 4px;border-radius:6px;text-align:center;border:1px solid #7C3AED;">
                    <div style="font-size:12px;font-weight:700;color:#7C3AED;">${currency} ${cashierPremium.toLocaleString()}</div>
                    <div style="font-size:7px;color:#64748B;text-transform:uppercase;">👑 Cashier Prem</div>
                </div>
            </div>
            
            <div style="margin-bottom:10px;">
                <div style="font-weight:700;font-size:0.9rem;color:#059669;border-bottom:2px solid #34D399;padding-bottom:4px;margin-bottom:6px;">
                    📋 Bill Summary
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px 14px;font-size:13px;">
                    <div><strong>Bill #:</strong> ${billNumber}</div>
                    <div><strong>Status:</strong> ${billStatus}</div>
                    <div><strong>Created:</strong> ${createdAt}</div>
                    <div><strong>Branch:</strong> ${branchName}</div>
                    <div><strong>Visit:</strong> ${visitNumber}</div>
                    <div><strong>Doctor:</strong> ${doctorName}</div>
                </div>
            </div>
            
            <div style="margin-bottom:10px;">
                <div style="font-weight:700;font-size:0.9rem;color:#059669;border-bottom:2px solid #34D399;padding-bottom:4px;margin-bottom:6px;">
                    👤 Patient Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 14px;font-size:13px;">
                    <div><strong>Name:</strong> ${patientName}</div>
                    <div><strong>Patient ID:</strong> ${patientId}</div>
                    <div><strong>Phone:</strong> ${patientPhone}</div>
                    <div><strong>Gender:</strong> ${patientGender}</div>
                </div>
            </div>
            
            <div style="margin-bottom:10px;">
                <div style="font-weight:700;font-size:0.9rem;color:#059669;border-bottom:2px solid #34D399;padding-bottom:4px;margin-bottom:6px;">
                    📦 Bill Items (${totalItems})
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">#</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Item</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Type</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">Qty</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:11px;">Unit</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:11px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                        <tr style="background:#E8F0FE;font-weight:700;">
                            <td colspan="5" style="text-align:right;padding:5px 10px;">SUBTOTAL</td>
                            <td style="text-align:right;color:#0B5ED7;padding:5px 10px;">${currency} ${subtotal.toLocaleString()}</td>
                        </tr>
                        ${pharmacyDiscount > 0 ? `<tr style="background:#FEF3C7;"><td colspan="5" style="text-align:right;padding:4px 10px;color:#D97706;">Pharmacy Discount</td><td style="text-align:right;color:#D97706;padding:4px 10px;">-${currency} ${pharmacyDiscount.toLocaleString()}</td></tr>` : ''}
                        ${cashierDiscount > 0 ? `<tr style="background:#DBEAFE;"><td colspan="5" style="text-align:right;padding:4px 10px;color:#2563EB;">Cashier Discount</td><td style="text-align:right;color:#2563EB;padding:4px 10px;">-${currency} ${cashierDiscount.toLocaleString()}</td></tr>` : ''}
                        ${pharmacyPremium > 0 ? `<tr style="background:#FEF3C7;"><td colspan="5" style="text-align:right;padding:4px 10px;color:#D97706;">👑 Pharmacy Premium</td><td style="text-align:right;color:#D97706;padding:4px 10px;">+${currency} ${pharmacyPremium.toLocaleString()}</td></tr>` : ''}
                        ${cashierPremium > 0 ? `<tr style="background:#EDE9FE;"><td colspan="5" style="text-align:right;padding:4px 10px;color:#7C3AED;">👑 Cashier Premium</td><td style="text-align:right;color:#7C3AED;padding:4px 10px;">+${currency} ${cashierPremium.toLocaleString()}</td></tr>` : ''}
                        <tr style="background:#D1FAE5;font-weight:700;border-top:2px solid #059669;">
                            <td colspan="5" style="text-align:right;padding:6px 10px;color:#059669;">GRAND TOTAL</td>
                            <td style="text-align:right;color:#059669;padding:6px 10px;">${currency} ${total.toLocaleString()}</td>
                        </tr>
                        <tr style="background:#D1FAE5;">
                            <td colspan="5" style="text-align:right;padding:4px 10px;color:#059669;">PAID</td>
                            <td style="text-align:right;color:#059669;padding:4px 10px;">${currency} ${paid.toLocaleString()}</td>
                        </tr>
                        <tr style="background:${balance > 0 ? '#FEE2E2' : '#D1FAE5'};font-weight:700;">
                            <td colspan="5" style="text-align:right;padding:5px 10px;color:${balance > 0 ? '#DC2626' : '#059669'};">REMAINING</td>
                            <td style="text-align:right;color:${balance > 0 ? '#DC2626' : '#059669'};padding:5px 10px;">${currency} ${balance.toLocaleString()}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top:12px;padding-top:10px;border-top:2px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="font-size:13px;color:#64748B;">
                    <div>Generated by: <?= htmlspecialchars($user_full_name) ?></div>
                    <div>Date: <?= date('F d, Y') ?></div>
                </div>
                <div style="text-align:center;padding:6px 14px;border:3px solid #059669;border-radius:10px;background:#D1FAE5;min-width:150px;">
                    <div style="font-size:9px;color:#64748B;text-transform:uppercase;font-weight:700;">Official Stamp</div>
                    <div style="font-size:13px;font-weight:800;color:#059669;">BRAICK DISPENSARY</div>
                    <div style="font-size:11px;color:#64748B;margin-top:2px;">Approved By: __________</div>
                </div>
            </div>
            <div style="text-align:center;margin-top:8px;font-size:11px;color:#94A3B8;">
                Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Bill_<?= htmlspecialchars($bill['bill_number'] ?? 'bill') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePDFModal();
    });

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) closePDFModal();
    });

    console.log('%c✅ Braick - View Bill V3 (CORRECT SUBTOTAL)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c✅ Subtotal kutoka bills.subtotal: <?= number_format($subtotal, 0) ?>', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Summary breakdown kwenye table footer', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Balance: <?= number_format($balance, 0) ?>', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>