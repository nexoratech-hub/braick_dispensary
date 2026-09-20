<?php
// ================================================================
// FILE: frontend/pages/admin/audit/view_payment.php
// ADMIN AUDIT - VIEW PAYMENT DETAILS (V2 - GREEN ITEMS THEME)
// ✅ View payment full details
// ✅ Receipt + Bill + Patient info
// ✅ Related bill summary (all payments for that bill)
// ✅ GREEN THEME kwa Bill Items table
// ✅ SCROLL BUTTONS <> kwa items table
// ✅ Print & PDF export
// ✅ BLUE THEME (main) + GREEN (items)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$payment_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($payment_id <= 0) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// GET PAYMENT DETAILS
// ================================================================
$payment = null;
try {
    $sql = "SELECT 
        p.*,
        b.bill_number,
        b.total_amount as bill_total,
        b.subtotal as bill_subtotal,
        b.discount_amount as bill_discount,
        b.pharmacy_discount,
        b.cashier_discount,
        b.premium_amount,
        b.premium_note,
        b.total_discount as bill_total_discount,
        b.paid_amount as bill_paid,
        b.balance as bill_balance,
        b.status as bill_status,
        b.payment_method as bill_payment_method,
        b.created_at as bill_created_at,
        b.notes as bill_notes,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        pat.address as patient_address,
        pat.blood_group as patient_blood,
        pat.allergies as patient_allergies,
        v.visit_number,
        v.visit_date,
        v.diagnosis,
        u.full_name as received_by_name,
        u.username as received_by_username,
        u.role as received_by_role,
        u.profile_pic as received_by_pic,
        u.phone as received_by_phone,
        br.name as branch_name,
        br.location as branch_location,
        br.phone as branch_phone,
        br.email as branch_email
    FROM payments p
    LEFT JOIN bills b ON p.bill_id = b.id
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON p.received_by = u.id
    LEFT JOIN branches br ON p.branch_id = br.id
    WHERE p.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Payment fetch error: " . $e->getMessage());
}

if (!$payment) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET ALL PAYMENTS FOR THIS BILL
// ================================================================
$bill_payments = [];
if (!empty($payment['bill_id'])) {
    try {
        $sql = "SELECT 
            p.id,
            p.receipt_number,
            p.amount,
            p.payment_method,
            p.received_at,
            p.notes,
            u.full_name as received_by_name,
            u.role as received_by_role
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ?
        ORDER BY p.received_at ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$payment['bill_id']]);
        $bill_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// GET BILL ITEMS
// ================================================================
$bill_items = [];
if (!empty($payment['bill_id'])) {
    try {
        $sql = "SELECT * FROM bill_items 
                WHERE bill_id = ? 
                AND status != 'cancelled'
                ORDER BY id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$payment['bill_id']]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

$patient_age = calculateAge($payment['date_of_birth']);

// Payment method icons
$payment_icons = [
    'cash' => ['icon' => 'fa-money-bill-wave', 'label' => 'Cash'],
    'm-pesa' => ['icon' => 'fa-mobile-alt', 'label' => 'M-Pesa'],
    'airtel_money' => ['icon' => 'fa-mobile-alt', 'label' => 'Airtel Money'],
    'tigo_pesa' => ['icon' => 'fa-mobile-alt', 'label' => 'Tigo Pesa'],
    'halopesa' => ['icon' => 'fa-mobile-alt', 'label' => 'Halopesa'],
    'bank' => ['icon' => 'fa-university', 'label' => 'Bank'],
    'card' => ['icon' => 'fa-credit-card', 'label' => 'Card'],
    'insurance' => ['icon' => 'fa-shield-alt', 'label' => 'Insurance'],
    'other' => ['icon' => 'fa-wallet', 'label' => 'Other'],
];

$pm_key = $payment['payment_method'] ?? 'cash';
$pm_meta = $payment_icons[$pm_key] ?? $payment_icons['cash'];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payment - <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --primary-soft: #DBEAFE;
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
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}
[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-bg: #1E3A5F;
    --primary-soft: #1E40AF;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .money-cell, .font-mono, .mono { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

/* ALERT */
.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }

/* PAGE HEADER */
.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

/* STATUS BANNER */
.status-banner { border-radius: 14px; padding: 18px 24px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; position: relative; overflow: hidden; box-shadow: var(--shadow-md); }
.status-banner.paid { background: linear-gradient(135deg, #059669, #047857); color: white; }
.status-banner.partial { background: linear-gradient(135deg, #0891B2, #0E7490); color: white; }
.status-banner.pending { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.status-banner.cancelled { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.status-banner::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.08); border-radius: 50%; pointer-events: none; }
.status-banner .status-left { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; }
.status-banner .status-icon { width: 58px; height: 58px; border-radius: 16px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; border: 2px solid rgba(255,255,255,0.3); backdrop-filter: blur(4px); }
.status-banner .status-label { font-size: 1.35rem; font-weight: 900; letter-spacing: -0.02em; line-height: 1.1; }
.status-banner .status-sub { font-size: 0.75rem; opacity: 0.9; font-weight: 500; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
.status-banner .status-amount { text-align: right; position: relative; z-index: 1; }
.status-banner .status-amount .label { font-size: 0.65rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 4px; }
.status-banner .status-amount .value { font-size: 2rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: -0.03em; line-height: 1; }
.status-banner .status-amount .currency-prefix { font-size: 1rem; opacity: 0.85; font-family: var(--font-primary); font-weight: 700; }

/* GRID */
.view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }

/* INFO CARD */
.info-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
.info-card:hover { box-shadow: var(--shadow-md); border-color: var(--primary); }
.info-card .card-header { padding: 14px 18px; background: var(--primary-bg); border-bottom: 2px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.info-card .card-header .title { display: flex; align-items: center; gap: 8px; }
.info-card .card-header i { color: var(--primary); font-size: 0.95rem; }
.info-card .card-body { padding: 18px 20px; }

.info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-color); font-size: 0.82rem; }
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }
.info-row .label { color: var(--text-secondary); font-weight: 600; font-size: 0.72rem; display: flex; align-items: center; gap: 6px; flex-shrink: 0; min-width: 120px; }
.info-row .label i { font-size: 0.7rem; opacity: 0.8; color: var(--primary); }
.info-row .value { color: var(--text-primary); font-weight: 700; text-align: right; word-break: break-word; flex: 1; }
.info-row .value.mono { font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: -0.02em; }
.info-row .value.amount { font-family: var(--font-mono); font-size: 1.05rem; font-weight: 900; color: var(--primary); }
.info-row .value.green { color: var(--success); }
.info-row .value.red { color: var(--danger); }
.info-row .value.purple { color: var(--purple); }

/* RECEIPT BOX */
.receipt-box { background: linear-gradient(135deg, #059669, #047857); color: white; border-radius: 14px; padding: 20px 24px; margin-bottom: 18px; box-shadow: 0 6px 20px rgba(5, 150, 105, 0.25); position: relative; overflow: hidden; }
.receipt-box::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.08); border-radius: 50%; pointer-events: none; }
.receipt-box .receipt-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; position: relative; z-index: 1; }
.receipt-box .receipt-number { font-size: 1.2rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: -0.02em; display: flex; align-items: center; gap: 8px; }
.receipt-box .receipt-badge { background: rgba(255,255,255,0.2); color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid rgba(255,255,255,0.2); }
.receipt-box .receipt-body { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; position: relative; z-index: 1; }
.receipt-box .receipt-item { background: rgba(255,255,255,0.1); padding: 10px 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); }
.receipt-box .receipt-item .ri-label { font-size: 0.6rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 4px; }
.receipt-box .receipt-item .ri-value { font-size: 1.05rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: -0.02em; }

/* PATIENT PROFILE */
.patient-profile { display: flex; align-items: center; gap: 14px; padding: 16px 20px; background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft)); border-bottom: 2px solid var(--border-color); }
.patient-avatar { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.patient-name { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 4px; }
.patient-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-item { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-card); padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.patient-meta-item i { color: var(--primary); font-size: 0.65rem; }

/* BILL SUMMARY */
.bill-summary { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--purple); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.bill-summary .bs-header { padding: 14px 20px; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.bill-summary .bs-header i { color: #C4B5FD; }
.bill-summary .bs-body { padding: 16px 20px; }
.bill-totals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.bill-total-item { background: var(--bg-body); border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border-color); text-align: center; }
.bill-total-item .bt-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); font-weight: 800; margin-bottom: 4px; }
.bill-total-item .bt-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; color: var(--text-primary); letter-spacing: -0.02em; }
.bill-total-item.total .bt-value { color: var(--primary); }
.bill-total-item.paid .bt-value { color: var(--success); }
.bill-total-item.balance .bt-value { color: var(--danger); }

/* PAYMENTS HISTORY */
.payments-history { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--success); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.payments-history .ph-header { padding: 14px 20px; background: linear-gradient(135deg, #059669, #047857); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.payments-history .ph-header i { color: #A7F3D0; }
.payments-history .ph-body { padding: 0; }
.payment-history-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 20px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; transition: background 0.2s ease; }
.payment-history-item:last-child { border-bottom: none; }
.payment-history-item:hover { background: var(--primary-bg); }
.payment-history-item.current { background: var(--success-bg); border-left: 4px solid var(--success); }
.payment-history-item .phi-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px; }
.payment-history-item .phi-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #059669, #34D399); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; flex-shrink: 0; }
.payment-history-item .phi-info { display: flex; flex-direction: column; gap: 2px; }
.payment-history-item .phi-receipt { font-size: 0.75rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono); }
.payment-history-item .phi-received-by { font-size: 0.68rem; color: var(--text-secondary); font-weight: 600; }
.payment-history-item .phi-right { text-align: right; }
.payment-history-item .phi-amount { font-family: var(--font-mono); font-weight: 900; font-size: 0.95rem; color: var(--success); }
.payment-history-item .phi-date { font-size: 0.62rem; color: var(--text-secondary); font-weight: 600; }
.payment-history-item .phi-current-tag { background: var(--success); color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; margin-left: 6px; }

/* ================================================================
   ✅ BILL ITEMS - GREEN THEME + SCROLL BUTTONS
   ================================================================ */
.items-table-card { 
    background: var(--bg-card); 
    border-radius: 14px; 
    border: 2px solid #059669;  /* ✅ GREEN BORDER */
    overflow: hidden; 
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.12);
    margin-bottom: 18px; 
}
.items-table-card:hover {
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.2);
    border-color: #047857;
}
.items-table-card .card-header { 
    padding: 14px 20px; 
    background: linear-gradient(135deg, #059669, #047857);  /* ✅ GREEN HEADER */
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    gap: 8px; 
    flex-wrap: wrap; 
}
.items-table-card .card-header .title { 
    color: white; 
    font-size: 0.9rem; 
    font-weight: 800; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
}
.items-table-card .card-header .title i { 
    color: #A7F3D0;  /* ✅ LIGHT GREEN ICON */
    font-size: 1rem; 
}
.items-table-card .card-header .header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* ✅ SCROLL BUTTONS <> */
.scroll-buttons { 
    display: inline-flex; 
    gap: 4px; 
    background: rgba(255,255,255,0.15); 
    border-radius: 10px; 
    padding: 4px; 
    border: 1px solid rgba(255,255,255,0.25);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.btn-scroll { 
    width: 34px; 
    height: 34px; 
    border-radius: 8px; 
    background: rgba(255,255,255,0.1); 
    color: white; 
    border: 1px solid rgba(255,255,255,0.15); 
    cursor: pointer; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 0.85rem; 
    font-weight: 800; 
    transition: all 0.25s ease; 
}
.btn-scroll:hover { 
    background: rgba(255,255,255,0.35); 
    transform: scale(1.1); 
    border-color: rgba(255,255,255,0.5); 
    box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
}
.btn-scroll:active { 
    transform: scale(0.92); 
}

.items-table-card .card-header .count { 
    color: rgba(255,255,255,0.95); 
    font-size: 0.7rem; 
    font-weight: 700; 
    background: rgba(255,255,255,0.2); 
    padding: 4px 12px; 
    border-radius: 10px; 
    border: 1px solid rgba(255,255,255,0.2);
}

/* ✅ TABLE SCROLL WRAPPER */
.items-scroll-wrapper {
    overflow-x: auto;
    scroll-behavior: smooth;
    position: relative;
}
.items-scroll-wrapper::-webkit-scrollbar { height: 10px; }
.items-scroll-wrapper::-webkit-scrollbar-track { 
    background: rgba(5, 150, 105, 0.1); 
    border-radius: 10px; 
    margin: 0 8px;
}
.items-scroll-wrapper::-webkit-scrollbar-thumb { 
    background: linear-gradient(90deg, #059669, #10B981); 
    border-radius: 10px; 
    border: 2px solid var(--bg-card);
}
.items-scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(90deg, #047857, #059669);
}

/* ✅ GREEN DATA TABLE */
.data-table-green { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 0.78rem; 
    min-width: 1000px;
}
.data-table-green thead th { 
    text-align: left; 
    padding: 10px 12px; 
    font-weight: 800; 
    font-size: 0.6rem; 
    text-transform: uppercase; 
    letter-spacing: 0.06em; 
    color: white; 
    background: linear-gradient(135deg, #059669, #047857);  /* ✅ GREEN HEADER */
    white-space: nowrap; 
}
.data-table-green tbody td { 
    padding: 10px 12px; 
    border-bottom: 1px solid var(--border-color); 
    color: var(--text-primary); 
    vertical-align: middle; 
    font-weight: 500; 
}
.data-table-green tbody tr:hover td { 
    background: rgba(5, 150, 105, 0.08);  /* ✅ GREEN HOVER */
}
[data-theme="dark"] .data-table-green tbody tr:hover td {
    background: rgba(5, 150, 105, 0.15);
}
.data-table-green tbody tr:last-child td { border-bottom: none; }

.money-cell-green { 
    font-family: var(--font-mono); 
    font-weight: 800; 
    font-size: 0.8rem; 
    color: var(--success); 
    text-align: right; 
    letter-spacing: -0.02em; 
}
.money-cell-green .currency-prefix { 
    font-size: 0.65rem; 
    color: var(--text-secondary); 
    margin-right: 2px; 
    font-family: var(--font-primary); 
    font-weight: 600; 
}

.item-type-badge-green { 
    display: inline-block; 
    padding: 2px 7px; 
    border-radius: 5px; 
    font-size: 0.55rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    background: rgba(5, 150, 105, 0.12); 
    color: #059669; 
    border: 1px solid rgba(5, 150, 105, 0.3);
}
.item-type-badge-green.medication { background: rgba(217, 119, 6, 0.12); color: #D97706; border-color: rgba(217, 119, 6, 0.3); }
.item-type-badge-green.lab_test { background: rgba(59, 130, 246, 0.12); color: #3B82F6; border-color: rgba(59, 130, 246, 0.3); }
.item-type-badge-green.consultation { background: rgba(5, 150, 105, 0.15); color: #047857; border-color: rgba(5, 150, 105, 0.4); }
.item-type-badge-green.procedure { background: rgba(13, 148, 136, 0.12); color: #0D9488; border-color: rgba(13, 148, 136, 0.3); }
.item-type-badge-green.registration { background: rgba(100, 116, 139, 0.12); color: #64748B; border-color: rgba(100, 116, 139, 0.3); }
.item-type-badge-green.equipment { background: rgba(124, 58, 237, 0.12); color: #7C3AED; border-color: rgba(124, 58, 237, 0.3); }

/* GREEN TOTAL ROW */
.data-table-green tbody tr.total-row-green td {
    border-top: 3px solid #059669;
    font-weight: 800;
    background: rgba(5, 150, 105, 0.08) !important;
    color: #059669;
}

/* ACTION BAR */
.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); }
.btn-secondary { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }
.btn-edit { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
.btn-edit:hover { box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5); }

/* RESPONSIVE */
@media (max-width: 1024px) { .view-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.5rem; }
    .status-banner .status-icon { width: 48px; height: 48px; font-size: 1.3rem; }
    .status-banner .status-label { font-size: 1.1rem; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .info-row .label { min-width: 90px; font-size: 0.68rem; }
    .patient-avatar { width: 48px; height: 48px; font-size: 1.2rem; }
    .patient-name { font-size: 0.95rem; }
    .data-table-green { font-size: 0.7rem; }
    .data-table-green thead th, .data-table-green tbody td { padding: 7px 8px; }
    .btn-scroll { width: 30px; height: 30px; font-size: 0.75rem; }
}
@media print {
    .action-bar, .btn-header, .scroll-buttons { display: none !important; }
    .page-header, .status-banner, .receipt-box, .items-table-card .card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Payment Details
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($payment['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($payment['received_at'] ?? $payment['created_at'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="edit_payment.php?id=<?= $payment_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-edit"></i> Edit
            </a>
            <?php if (!empty($payment['bill_id'])): ?>
            <a href="view_bill.php?id=<?= $payment['bill_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-file-invoice"></i> Bill
            </a>
            <?php endif; ?>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <div class="status-banner <?= htmlspecialchars($payment['bill_status'] ?? 'paid') ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="status-info">
                <div class="status-label">
                    PAYMENT RECEIVED
                    <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-weight:700;text-transform:uppercase;">
                        <?= htmlspecialchars($pm_meta['label']) ?>
                    </span>
                </div>
                <div class="status-sub">
                    <i class="fas fa-user-check"></i>
                    Received by: <?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?>
                    <span style="opacity:0.6;">•</span>
                    <i class="fas fa-clock"></i>
                    <?= date('d M Y, H:i', strtotime($payment['received_at'] ?? $payment['created_at'])) ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label">Amount Paid</div>
            <div class="value">
                <span class="currency-prefix"><?= $currency ?></span>
                <?= number_format($payment['amount'] ?? 0, 0) ?>
            </div>
        </div>
    </div>

    <!-- RECEIPT BOX -->
    <div class="receipt-box">
        <div class="receipt-header">
            <div class="receipt-number">
                <i class="fas fa-receipt"></i>
                <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
            </div>
            <span class="receipt-badge">
                <i class="fas fa-check-circle"></i> Official Receipt
            </span>
        </div>
        <div class="receipt-body">
            <div class="receipt-item">
                <div class="ri-label">Payment Method</div>
                <div class="ri-value">
                    <i class="fas <?= $pm_meta['icon'] ?>"></i>
                    <?= htmlspecialchars($pm_meta['label']) ?>
                </div>
            </div>
            <div class="receipt-item">
                <div class="ri-label">Amount Paid</div>
                <div class="ri-value"><?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?></div>
            </div>
            <div class="receipt-item">
                <div class="ri-label">Date & Time</div>
                <div class="ri-value" style="font-size:0.85rem;"><?= date('d M Y, H:i', strtotime($payment['received_at'] ?? $payment['created_at'])) ?></div>
            </div>
            <div class="receipt-item">
                <div class="ri-label">Received By</div>
                <div class="ri-value" style="font-size:0.85rem;"><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="view-grid">
        
        <!-- PATIENT INFO -->
        <div class="info-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-user-injured"></i> Patient Information</span>
            </div>
            
            <?php if (!empty($payment['patient_name'])): ?>
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($payment['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="patient-name"><?= htmlspecialchars($payment['patient_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($payment['patient_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($payment['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($payment['patient_gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($payment['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($payment['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="patient-profile">
                <div class="patient-avatar" style="background:linear-gradient(135deg,#64748B,#94A3B8);">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="patient-name">Walk-in Customer</div>
                    <div class="patient-meta">
                        <span class="patient-meta-item">
                            <i class="fas fa-info-circle"></i> No patient linked
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($payment['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($payment['patient_address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value"><?= htmlspecialchars($payment['patient_blood'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($payment['patient_allergies'] ?? 'None') ?></span>
                </div>
                <?php if (!empty($payment['visit_number'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-clipboard-check"></i> Visit #</span>
                    <span class="value mono" style="color:var(--primary);"><?= htmlspecialchars($payment['visit_number']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($payment['diagnosis'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-stethoscope"></i> Diagnosis</span>
                    <span class="value"><?= htmlspecialchars($payment['diagnosis']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PAYMENT INFO -->
        <div class="info-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-info-circle"></i> Payment Information</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-receipt"></i> Receipt #</span>
                    <span class="value mono" style="color:var(--primary);"><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Amount</span>
                    <span class="value amount">
                        <span style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                        <?= number_format($payment['amount'] ?? 0, 0) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-credit-card"></i> Method</span>
                    <span class="value">
                        <i class="fas <?= $pm_meta['icon'] ?>" style="color:var(--primary);margin-right:4px;"></i>
                        <?= htmlspecialchars($pm_meta['label']) ?>
                    </span>
                </div>
                <?php if (!empty($payment['reference_number'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-barcode"></i> Reference</span>
                    <span class="value mono"><?= htmlspecialchars($payment['reference_number']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar"></i> Date & Time</span>
                    <span class="value"><?= date('d M Y, H:i:s', strtotime($payment['received_at'] ?? $payment['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($payment['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-tie"></i> Received By</span>
                    <span class="value">
                        <?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?>
                        <?php if (!empty($payment['received_by_role'])): ?>
                            <span style="font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;font-weight:800;text-transform:uppercase;margin-left:4px;">
                                <?= strtoupper($payment['received_by_role']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($payment['notes'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                    <span class="value" style="font-size:0.72rem;"><?= htmlspecialchars($payment['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- BILL SUMMARY -->
    <?php if (!empty($payment['bill_id'])): ?>
    <div class="bill-summary">
        <div class="bs-header">
            <span><i class="fas fa-file-invoice"></i> Related Bill: <?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?></span>
            <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                <i class="fas fa-check-circle"></i> <?= strtoupper($payment['bill_status'] ?? 'PAID') ?>
            </span>
        </div>
        <div class="bs-body">
            <div class="bill-totals-grid">
                <div class="bill-total-item">
                    <div class="bt-label">Subtotal</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($payment['bill_subtotal'] ?? 0, 0) ?></div>
                </div>
                <div class="bill-total-item">
                    <div class="bt-label">Discount</div>
                    <div class="bt-value" style="color:var(--danger);">
                        - <?= $currency ?> <?= number_format($payment['bill_total_discount'] ?? 0, 0) ?>
                    </div>
                </div>
                <?php if (($payment['premium_amount'] ?? 0) > 0): ?>
                <div class="bill-total-item">
                    <div class="bt-label">Premium</div>
                    <div class="bt-value" style="color:var(--purple);">
                        + <?= $currency ?> <?= number_format($payment['premium_amount'] ?? 0, 0) ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="bill-total-item total">
                    <div class="bt-label">Total Amount</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($payment['bill_total'] ?? 0, 0) ?></div>
                </div>
                <div class="bill-total-item paid">
                    <div class="bt-label">Total Paid</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($payment['bill_paid'] ?? 0, 0) ?></div>
                </div>
                <div class="bill-total-item balance">
                    <div class="bt-label">Balance</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($payment['bill_balance'] ?? 0, 0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PAYMENT HISTORY FOR THIS BILL -->
    <?php if (count($bill_payments) > 0): ?>
    <div class="payments-history">
        <div class="ph-header">
            <span><i class="fas fa-history"></i> Payment History (<?= count($bill_payments) ?> payments)</span>
            <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                <i class="fas fa-list"></i> All Payments for this Bill
            </span>
        </div>
        <div class="ph-body">
            <?php foreach ($bill_payments as $bp): 
                $is_current = ($bp['id'] == $payment_id);
                $bp_initials = strtoupper(substr($bp['received_by_name'] ?? 'NA', 0, 2));
                $bp_name_parts = explode(' ', trim($bp['received_by_name'] ?? 'N/A'));
                if (count($bp_name_parts) >= 2) {
                    $bp_initials = strtoupper(substr($bp_name_parts[0], 0, 1) . substr($bp_name_parts[1], 0, 1));
                }
            ?>
                <div class="payment-history-item <?= $is_current ? 'current' : '' ?>">
                    <div class="phi-left">
                        <div class="phi-avatar"><?= htmlspecialchars($bp_initials) ?></div>
                        <div class="phi-info">
                            <span class="phi-receipt">
                                <i class="fas fa-receipt" style="font-size:0.65rem;opacity:0.7;"></i>
                                <?= htmlspecialchars($bp['receipt_number'] ?? 'N/A') ?>
                                <?php if ($is_current): ?>
                                    <span class="phi-current-tag">Current</span>
                                <?php endif; ?>
                            </span>
                            <span class="phi-received-by">
                                By: <?= htmlspecialchars($bp['received_by_name'] ?? 'N/A') ?>
                                <span style="font-size:0.55rem;background:var(--primary-bg);color:var(--primary);padding:1px 5px;border-radius:4px;font-weight:800;text-transform:uppercase;margin-left:3px;">
                                    <?= strtoupper($bp['received_by_role'] ?? 'user') ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="phi-right">
                        <span class="phi-amount">
                            <span class="currency-prefix" style="font-size:0.65rem;color:var(--text-secondary);"><?= $currency ?></span>
                            <?= number_format($bp['amount'] ?? 0, 0) ?>
                        </span>
                        <span class="phi-date">
                            <i class="fas fa-clock" style="font-size:0.6rem;"></i>
                            <?= date('d M Y, H:i', strtotime($bp['received_at'])) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ✅ BILL ITEMS - GREEN THEME + SCROLL BUTTONS -->
    <?php if (count($bill_items) > 0): ?>
    <div class="items-table-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-list"></i>
                Bill Items (<?= count($bill_items) ?> items)
            </span>
            <div class="header-actions">
                <!-- ✅ SCROLL BUTTONS <> -->
                <div class="scroll-buttons" title="Scroll left / right">
                    <button type="button" class="btn-scroll" onclick="scrollItems('left')" title="Scroll Left (Alt+←)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="btn-scroll" onclick="scrollItems('right')" title="Scroll Right (Alt+→)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <span class="count">
                    <i class="fas fa-money-bill-wave"></i>
                    Total: <?= $currency ?> <?= number_format($payment['bill_total'] ?? 0, 0) ?>
                </span>
            </div>
        </div>
        <div class="items-scroll-wrapper" id="itemsWrapper">
            <table class="data-table-green">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Item Name</th>
                        <th style="text-align:center;">Type</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $item_num = 1; 
                    $items_total_calc = 0;
                    foreach ($bill_items as $item): 
                        $item_total = (float)($item['total_price'] ?? 0);
                        $item_discount = (float)($item['discount_amount'] ?? 0);
                        $item_final = $item_total - $item_discount;
                        $items_total_calc += $item_final;
                        $item_type = strtolower($item['item_type'] ?? 'other');
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);"><?= $item_num++ ?></td>
                            <td>
                                <div style="font-weight:700;font-size:0.78rem;"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></div>
                                <?php if (!empty($item['description'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;"><?= htmlspecialchars($item['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="item-type-badge-green <?= htmlspecialchars($item_type) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $item_type)) ?>
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:800;color:#059669;font-family:var(--font-mono);">
                                <?= (int)($item['quantity'] ?? 0) ?>
                            </td>
                            <td class="money-cell-green">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item['unit_price'] ?? 0, 0) ?>
                            </td>
                            <td class="money-cell-green" style="color:<?= $item_discount > 0 ? 'var(--danger)' : 'var(--text-secondary)' ?>;">
                                <?php if ($item_discount > 0): ?>
                                    - <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_discount, 0) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="money-cell-green" style="color:#059669;font-weight:900;">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_final, 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- ✅ GREEN TOTAL ROW -->
                    <tr class="total-row-green">
                        <td colspan="6" style="text-align:right;font-weight:800;font-size:0.85rem;color:#059669;">
                            <i class="fas fa-calculator"></i> ITEMS TOTAL
                        </td>
                        <td class="money-cell-green" style="color:#059669;font-weight:900;font-size:0.9rem;">
                            <span class="currency-prefix" style="color:#059669;"><?= $currency ?></span><?= number_format($items_total_calc, 0) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- ✅ SCROLL HINT -->
        <div style="padding:6px 16px;background:rgba(5,150,105,0.08);border-top:1px solid rgba(5,150,105,0.2);text-align:center;font-size:0.65rem;color:var(--text-secondary);font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
            <i class="fas fa-arrows-alt-h" style="color:#059669;"></i>
            Use <kbd style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:4px;padding:1px 6px;font-family:var(--font-mono);font-size:0.62rem;color:#059669;font-weight:800;">‹</kbd> <kbd style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:4px;padding:1px 6px;font-family:var(--font-mono);font-size:0.62rem;color:#059669;font-weight:800;">›</kbd> buttons to scroll left/right
            <span style="opacity:0.5;">•</span>
            Keyboard: <kbd style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:4px;padding:1px 6px;font-family:var(--font-mono);font-size:0.62rem;color:#059669;font-weight:800;">Alt</kbd> + <kbd style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:4px;padding:1px 6px;font-family:var(--font-mono);font-size:0.62rem;color:#059669;font-weight:800;">←</kbd> / <kbd style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:4px;padding:1px 6px;font-family:var(--font-mono);font-size:0.62rem;color:#059669;font-weight:800;">→</kbd>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="info-text">
                <div class="info-title">Payment Actions</div>
                <div class="info-sub">View, edit, or print this payment</div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="edit_payment.php?id=<?= $payment_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-edit">
                <i class="fas fa-edit"></i> Edit Payment
            </a>
            <?php if (!empty($payment['bill_id'])): ?>
            <a href="view_bill.php?id=<?= $payment['bill_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-secondary">
                <i class="fas fa-file-invoice"></i> View Bill
            </a>
            <?php endif; ?>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Revenue
            </a>
        </div>
    </div>

</main>

<script>
// ================================================================
// ✅ SCROLL ITEMS TABLE (Left / Right)
// ================================================================
function scrollItems(direction) {
    var wrapper = document.getElementById('itemsWrapper');
    if (!wrapper) return;
    if (wrapper.scrollWidth <= wrapper.clientWidth) return;
    
    var scrollAmount = 350;
    var currentScroll = wrapper.scrollLeft;
    var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
    
    var targetScroll = direction === 'left' 
        ? Math.max(0, currentScroll - scrollAmount)
        : Math.min(maxScroll, currentScroll + scrollAmount);
    
    wrapper.scrollTo({
        left: targetScroll,
        behavior: 'smooth'
    });
    
    // Visual feedback
    var btns = document.querySelectorAll('.btn-scroll');
    btns.forEach(function(btn) {
        btn.style.transform = 'scale(0.9)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

// ✅ Keyboard shortcuts (Alt + Arrow)
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'ArrowLeft') {
        e.preventDefault();
        scrollItems('left');
    }
    if (e.altKey && e.key === 'ArrowRight') {
        e.preventDefault();
        scrollItems('right');
    }
});

console.log('%c🧾 View Payment V2 - GREEN ITEMS THEME', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ GREEN Theme kwa Items table', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c✅ SCROLL BUTTONS <> kwenye header', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c⌨️ Alt + ← / → kwa keyboard scroll', 'font-size:12px; color:#7C3AED;');
console.log('%c✅ Receipt: <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c💰 Amount: <?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?>', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c📋 Bill Items: <?= count($bill_items) ?>', 'font-size:12px; color:#7C3AED;');
</script>

</body>
</html>