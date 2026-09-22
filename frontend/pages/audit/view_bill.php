<?php
// ================================================================
// FILE: frontend/pages/audit/view_bill.php
// AUDIT ROLE - VIEW BILL DETAILS
// ✅ Branch ya aliye login TU
// ✅ AUDIT role only - VIEW ONLY
// ✅ Recalculates total from subtotal - discount + premium
// ✅ Blue theme #0B5ED7
// ✅ Print export
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// AUDIT ROLE ONLY
// ================================================================
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$bill_id = (int)($_GET['id'] ?? 0);

// ✅ AUDIT anaona branch yake TU
$selected_branch_id = (int)$user_branch_id;

if ($bill_id <= 0) {
    header('Location: revenue.php');
    exit;
}

// ================================================================
// DATABASE PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

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
// ✅ GET BILL DETAILS - LAZIMA branch ya mtumiaji
// ================================================================
$bill = null;
try {
    $sql = "SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        p.date_of_birth as patient_dob,
        p.gender as patient_gender,
        p.address as patient_address,
        p.blood_group as patient_blood,
        p.allergies as patient_allergies,
        v.visit_number,
        v.visit_date,
        v.diagnosis,
        u.full_name as created_by_name,
        u.username as created_by_username,
        u.role as created_by_role,
        u.profile_pic as created_by_pic,
        br.name as branch_name,
        br.location as branch_location,
        br.phone as branch_phone
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ? AND b.branch_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bill fetch error: " . $e->getMessage());
}

if (!$bill) {
    // ✅ Bill hayupo kwenye branch yake - redirect
    header('Location: revenue.php');
    exit;
}

$bill_branch_id = (int)$bill['branch_id'];

// ================================================================
// GET BILL ITEMS
// ================================================================
$bill_items = [];
try {
    $sql = "SELECT * FROM bill_items 
            WHERE bill_id = ? 
            ORDER BY id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bill items error: " . $e->getMessage());
}

// ================================================================
// GET PAYMENTS
// ================================================================
$payments = [];
try {
    $sql = "SELECT 
        pay.*,
        u.full_name as received_by_name,
        u.role as received_by_role,
        u.profile_pic as received_by_pic
    FROM payments pay
    LEFT JOIN users u ON pay.received_by = u.id
    WHERE pay.bill_id = ?
    ORDER BY pay.received_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Payments error: " . $e->getMessage());
}

// ================================================================
// CALCULATIONS
// ================================================================
$subtotal = (float)($bill['subtotal'] ?? 0);
$pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
$cashier_discount = (float)($bill['cashier_discount'] ?? 0);
$discount_amount = (float)($bill['discount_amount'] ?? 0);
$total_discount_db = (float)($bill['total_discount'] ?? 0);
$premium_amount = (float)($bill['premium_amount'] ?? 0);
$db_total_amount = (float)($bill['total_amount'] ?? 0);
$paid_amount = (float)($bill['paid_amount'] ?? 0);
$db_balance = (float)($bill['balance'] ?? 0);

// Calculate item-level discount
$items_discount_total = 0;
$items_subtotal = 0;
foreach ($bill_items as $item) {
    $items_subtotal += (float)($item['total_price'] ?? 0);
    $items_discount_total += (float)($item['discount_amount'] ?? 0);
}

// Effective discount
$effective_discount = $total_discount_db;
if ($effective_discount == 0) {
    $effective_discount = $discount_amount + $pharmacy_discount + $cashier_discount;
}
if ($effective_discount == 0 && $items_discount_total > 0) {
    $effective_discount = $items_discount_total;
}

// Recalculate total
$calculated_total = $subtotal - $effective_discount + $premium_amount;
if ($calculated_total < 0) $calculated_total = 0;

$total_amount = $calculated_total;
$has_discrepancy = (abs($db_total_amount - $total_amount) > 1);

$balance = $total_amount - $paid_amount;
if ($balance < 0) $balance = 0;

// ================================================================
// TYPE META
// ================================================================
$type_meta = [
    'registration' => ['icon' => 'fa-id-card', 'label' => 'Registration', 'color' => '#64748B', 'bg' => '#F1F5F9'],
    'consultation' => ['icon' => 'fa-stethoscope', 'label' => 'Consultation', 'color' => '#059669', 'bg' => '#D1FAE5'],
    'lab_test' => ['icon' => 'fa-flask', 'label' => 'Lab Test', 'color' => '#3B82F6', 'bg' => '#DBEAFE'],
    'medication' => ['icon' => 'fa-pills', 'label' => 'Medication', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    'procedure' => ['icon' => 'fa-syringe', 'label' => 'Procedure', 'color' => '#0D9488', 'bg' => '#CCFBF1'],
    'equipment' => ['icon' => 'fa-tools', 'label' => 'Equipment', 'color' => '#7C3AED', 'bg' => '#EDE9FE'],
    'tool' => ['icon' => 'fa-wrench', 'label' => 'Tool', 'color' => '#DB2777', 'bg' => '#FCE7F3'],
    'other' => ['icon' => 'fa-ellipsis-h', 'label' => 'Other', 'color' => '#64748B', 'bg' => '#F1F5F9'],
];

// STATUS INFO
$status_info = [
    'paid' => ['label' => 'PAID', 'icon' => 'fa-check-circle', 'color' => '#059669', 'bg' => '#D1FAE5'],
    'pending' => ['label' => 'PENDING', 'icon' => 'fa-clock', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    'partial' => ['label' => 'PARTIAL', 'icon' => 'fa-hourglass-half', 'color' => '#0891B2', 'bg' => '#CFFAFE'],
    'cancelled' => ['label' => 'CANCELLED', 'icon' => 'fa-times-circle', 'color' => '#DC2626', 'bg' => '#FEE2E2'],
];

$current_status = $status_info[$bill['status'] ?? 'pending'] ?? $status_info['pending'];

// AGE CALC
$patient_age = 'N/A';
if (!empty($bill['patient_dob'])) {
    $dob = new DateTime($bill['patient_dob']);
    $now = new DateTime();
    $diff = $now->diff($dob);
    $patient_age = $diff->y . ' years';
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// HEADER NA SIDEBAR
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></title>
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

.money-number, .money-cell, .font-mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}

.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
    position: relative;
    z-index: 1;
}

.branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.1);
}

.btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.btn-header:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-2px);
}

/* DISCREPANCY ALERT */
.discrepancy-alert {
    background: var(--warning-bg);
    border-left: 4px solid var(--warning);
    padding: 14px 18px;
    margin-bottom: 18px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: var(--shadow-sm);
}

.discrepancy-alert i {
    font-size: 1.3rem;
    color: var(--warning);
    flex-shrink: 0;
}

.discrepancy-alert .alert-text {
    font-size: 0.8rem;
    color: #78350F;
    font-weight: 600;
    line-height: 1.5;
}

.discrepancy-alert .alert-text strong {
    color: var(--warning);
}

.discrepancy-alert .alert-text .mono {
    font-family: var(--font-mono);
    background: rgba(255,255,255,0.5);
    padding: 1px 6px;
    border-radius: 4px;
}

[data-theme="dark"] .discrepancy-alert .alert-text {
    color: #FEF3C7;
}

/* STATUS BANNER */
.status-banner {
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-md);
}

.status-banner.paid {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
}

.status-banner.pending {
    background: linear-gradient(135deg, #D97706, #B45309);
    color: white;
}

.status-banner.partial {
    background: linear-gradient(135deg, #0891B2, #0E7490);
    color: white;
}

.status-banner.cancelled {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
}

.status-banner::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    pointer-events: none;
}

.status-banner .status-left {
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    z-index: 1;
}

.status-banner .status-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    border: 2px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(4px);
}

.status-banner .status-info .status-label {
    font-size: 1.3rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.status-banner .status-info .status-sub {
    font-size: 0.72rem;
    opacity: 0.85;
    font-weight: 500;
    margin-top: 2px;
}

.status-banner .status-amount {
    text-align: right;
    position: relative;
    z-index: 1;
}

.status-banner .status-amount .label {
    font-size: 0.65rem;
    opacity: 0.75;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 700;
    margin-bottom: 2px;
}

.status-banner .status-amount .value {
    font-size: 1.75rem;
    font-weight: 900;
    font-family: var(--font-mono);
    letter-spacing: -0.03em;
    line-height: 1;
}

/* MAIN GRID */
.view-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 18px;
}

/* INFO CARD */
.info-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.info-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.info-card .card-header {
    padding: 12px 18px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--text-primary);
}

.info-card .card-header i {
    color: var(--primary);
    font-size: 0.95rem;
}

.info-card .card-body {
    padding: 16px 18px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.8rem;
}

.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }

.info-row .label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.info-row .label i {
    font-size: 0.7rem;
    opacity: 0.7;
}

.info-row .value {
    color: var(--text-primary);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
}

.info-row .value.mono {
    font-family: var(--font-mono);
    font-size: 0.78rem;
    letter-spacing: -0.02em;
}

/* PATIENT PROFILE */
.patient-profile {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft));
    border-bottom: 2px solid var(--border-color);
}

.patient-avatar {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
    border: 3px solid var(--bg-card);
}

.patient-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 4px;
}

.patient-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.patient-meta-item {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-card);
    padding: 3px 10px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.patient-meta-item i {
    color: var(--primary);
    font-size: 0.65rem;
}

/* ITEMS TABLE */
.items-table-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
}

.items-table-card .card-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
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
    color: #93C5FD;
    font-size: 1rem;
}

.items-table-card .card-header .count {
    color: rgba(255,255,255,0.9);
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(255,255,255,0.15);
    padding: 4px 12px;
    border-radius: 10px;
    backdrop-filter: blur(4px);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.items-table thead th {
    text-align: left;
    padding: 11px 16px;
    font-weight: 800;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}

.items-table tbody td {
    padding: 11px 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}

.items-table tbody tr { transition: background 0.2s ease; }
.items-table tbody tr:hover td { background: var(--primary-bg); }
.items-table tbody tr:last-child td { border-bottom: none; }

.item-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 8px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
}

.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.partial { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.82rem;
    text-align: right;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.money-cell.green { color: var(--success); }
.money-cell.red { color: var(--danger); }
.money-cell.blue { color: var(--primary); }

.currency-prefix {
    font-size: 0.68rem;
    color: var(--text-secondary);
    margin-right: 2px;
    font-family: var(--font-primary);
    font-weight: 600;
}

/* TOTALS CARD */
.totals-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
}

.totals-card .card-header {
    padding: 12px 20px;
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    font-size: 0.85rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}

.totals-card .card-header i { color: #A7F3D0; }

.totals-body { padding: 8px 0; }

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border-color);
}

.total-row:last-child { border-bottom: none; }

.total-row .label {
    color: var(--text-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.total-row .label i {
    color: var(--primary);
    font-size: 0.8rem;
    width: 16px;
    text-align: center;
}

.total-row .value {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.92rem;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.total-row .value.blue { color: var(--primary); }
.total-row .value.green { color: var(--success); }
.total-row .value.red { color: var(--danger); }
.total-row .value.purple { color: var(--purple); }

.total-row.grand {
    background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft));
    border-top: 3px solid var(--primary);
    border-bottom: 3px solid var(--primary);
    padding: 16px 20px;
    margin: 6px 0;
}

.total-row.grand .label {
    color: var(--primary);
    font-size: 1rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.total-row.grand .value {
    color: var(--primary);
    font-size: 1.35rem;
    font-weight: 900;
}

.total-row.paid-row {
    background: var(--success-bg);
    border-top: 2px solid var(--success);
}

.total-row.paid-row .label { color: var(--success); font-weight: 800; }
.total-row.paid-row .value { color: var(--success); }

.total-row.balance-row {
    background: var(--danger-bg);
    border-top: 2px solid var(--danger);
}

.total-row.balance-row .label { color: var(--danger); font-weight: 800; }
.total-row.balance-row .value { color: var(--danger); }

/* PAYMENTS CARD */
.payments-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.payment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg-body);
    border-radius: 10px;
    border-left: 4px solid var(--success);
    transition: all 0.3s ease;
    flex-wrap: wrap;
}

.payment-item:hover {
    background: var(--primary-bg);
    transform: translateX(4px);
}

.payment-item .payment-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 200px;
}

.payment-item .payment-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #059669, #34D399);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.7rem;
    text-transform: uppercase;
    flex-shrink: 0;
}

.payment-item .payment-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.payment-item .payment-receipt {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--primary);
    font-family: var(--font-mono);
}

.payment-item .payment-received-by {
    font-size: 0.68rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.payment-item .payment-right {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.payment-item .payment-amount {
    font-family: var(--font-mono);
    font-weight: 900;
    font-size: 0.95rem;
    color: var(--success);
}

.payment-item .payment-date {
    font-size: 0.62rem;
    color: var(--text-secondary);
    font-weight: 600;
}

/* ACTION BAR */
.action-bar {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    padding: 16px 20px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.action-bar .action-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.action-bar .action-info .info-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--primary-bg);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.action-bar .action-info .info-text .info-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-primary);
}

.action-bar .action-info .info-text .info-sub {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.action-bar .action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    white-space: nowrap;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.btn-primary:hover {
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5);
}

.btn-secondary {
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* EMPTY STATE */
.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 2.5rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 10px;
}

.empty-state p {
    font-weight: 600;
    font-size: 0.85rem;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .view-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.35rem; }
    .status-banner .status-icon { width: 44px; height: 44px; font-size: 1.2rem; }
    .status-banner .status-info .status-label { font-size: 1.1rem; }
    .items-table { font-size: 0.72rem; }
    .items-table thead th,
    .items-table tbody td { padding: 8px 10px; }
    .total-row { padding: 8px 14px; font-size: 0.78rem; }
    .total-row.grand .value { font-size: 1.1rem; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
}

@media (max-width: 480px) {
    .patient-profile { padding: 12px 14px; }
    .patient-avatar { width: 44px; height: 44px; font-size: 1.15rem; }
    .patient-name { font-size: 0.95rem; }
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
                <span class="branch-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($bill['created_at'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="revenue.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- DISCREPANCY ALERT -->
    <?php if ($has_discrepancy): ?>
    <div class="discrepancy-alert">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="alert-text">
            <strong>Note:</strong> Total has been recalculated from DB value. 
            DB was <span class="mono"><?= $currency ?> <?= number_format($db_total_amount, 0) ?></span>, 
            calculation is <span class="mono"><?= $currency ?> <?= number_format($total_amount, 0) ?></span>.
            <br>
            <span style="font-size:0.72rem;opacity:0.8;">
                Formula: Subtotal (<?= number_format($subtotal, 0) ?>) 
                - Discount (<?= number_format($effective_discount, 0) ?>) 
                + Premium (<?= number_format($premium_amount, 0) ?>) 
                = <?= number_format($total_amount, 0) ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- STATUS BANNER -->
    <div class="status-banner <?= htmlspecialchars($bill['status'] ?? 'pending') ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas <?= htmlspecialchars($current_status['icon']) ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label"><?= htmlspecialchars($current_status['label']) ?></div>
                <div class="status-sub">
                    <?php if (($bill['status'] ?? '') === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Bill has been fully paid
                    <?php elseif (($bill['status'] ?? '') === 'partial'): ?>
                        <i class="fas fa-hourglass-half"></i> Partial payment received
                    <?php elseif (($bill['status'] ?? '') === 'cancelled'): ?>
                        <i class="fas fa-times-circle"></i> Bill was cancelled
                    <?php else: ?>
                        <i class="fas fa-clock"></i> Awaiting payment
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label">Total Amount</div>
            <div class="value"><?= $currency ?> <?= number_format($total_amount, 0) ?></div>
        </div>
    </div>

    <!-- PATIENT + BILL INFO GRID -->
    <div class="view-grid">
        
        <!-- PATIENT INFO -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-user-injured"></i> Patient Information
            </div>
            
            <?php if (!empty($bill['patient_name'])): ?>
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($bill['patient_name'] ?? 'P', 0, 1)) ?>
                </div>
                <div>
                    <div class="patient-name"><?= htmlspecialchars($bill['patient_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($bill['patient_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($bill['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($bill['patient_gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($bill['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i> 
                                <?= htmlspecialchars($bill['patient_gender']) ?>
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
            <?php endif; ?>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?= htmlspecialchars($bill['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($bill['patient_address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value"><?= htmlspecialchars($bill['patient_blood'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($bill['patient_allergies'] ?? 'None') ?></span>
                </div>
                <?php if (!empty($bill['visit_number'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-clipboard-check"></i> Visit #</span>
                    <span class="value mono"><?= htmlspecialchars($bill['visit_number']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($bill['diagnosis'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-stethoscope"></i> Diagnosis</span>
                    <span class="value"><?= htmlspecialchars($bill['diagnosis']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- BILL INFO -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Bill Information
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Bill Number</span>
                    <span class="value mono" style="color:var(--primary);"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Created</span>
                    <span class="value"><?= date('d M Y, H:i', strtotime($bill['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-check"></i> Last Updated</span>
                    <span class="value"><?= date('d M Y, H:i', strtotime($bill['updated_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                    <span class="value"><?= ucfirst(str_replace('_', ' ', $bill['payment_method'] ?? 'Cash')) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-tie"></i> Created By</span>
                    <span class="value">
                        <?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?>
                        <?php if (!empty($bill['created_by_role'])): ?>
                            <span style="font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;font-weight:800;text-transform:uppercase;margin-left:4px;">
                                <?= strtoupper($bill['created_by_role']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($bill['notes'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                    <span class="value" style="font-size:0.72rem;"><?= htmlspecialchars($bill['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- BILL ITEMS TABLE -->
    <div class="items-table-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-list"></i>
                Bill Items
            </span>
            <span class="count"><?= count($bill_items) ?> items</span>
        </div>
        
        <?php if (count($bill_items) > 0): ?>
        <div style="overflow-x:auto;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:50px;text-align:center;">Type</th>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $item_num = 1; foreach ($bill_items as $item): 
                        $type = $item['item_type'] ?? 'other';
                        $meta = $type_meta[$type] ?? $type_meta['other'];
                        
                        $item_status = $item['status'] ?? 'pending';
                        $item_status_class = 'pending';
                        if ($item_status === 'paid') $item_status_class = 'paid';
                        elseif ($item_status === 'cancelled') $item_status_class = 'cancelled';
                        elseif ($item_status === 'refunded') $item_status_class = 'cancelled';
                        
                        $item_total = (float)($item['total_price'] ?? 0);
                        $item_discount = (float)($item['discount_amount'] ?? 0);
                        $item_final = $item_total - $item_discount;
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_num++ ?></td>
                            <td style="text-align:center;">
                                <div class="item-icon" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;" title="<?= $meta['label'] ?>">
                                    <i class="fas <?= $meta['icon'] ?>"></i>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:700;font-size:0.82rem;"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></div>
                                <?php if (!empty($item['item_code'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-mono);">
                                        <?= htmlspecialchars($item['item_code']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);max-width:200px;">
                                <?= htmlspecialchars($item['description'] ?? '—') ?>
                                <?php if (!empty($item['reference_type'])): ?>
                                    <div style="font-size:0.6rem;margin-top:2px;">
                                        <span style="background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;font-weight:700;text-transform:uppercase;">
                                            <?= htmlspecialchars(str_replace('_', ' ', $item['reference_type'])) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                                <?= (int)($item['quantity'] ?? 0) ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item['unit_price'] ?? 0, 0) ?>
                            </td>
                            <td class="money-cell <?= $item_discount > 0 ? 'red' : '' ?>">
                                <?php if ($item_discount > 0): ?>
                                    - <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_discount, 0) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="money-cell blue">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_final, 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= $item_status_class ?>">
                                    <i class="fas fa-<?= $item_status_class === 'paid' ? 'check-circle' : ($item_status_class === 'cancelled' ? 'times-circle' : 'clock') ?>"></i>
                                    <?= strtoupper($item_status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No items in this bill</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TOTALS + PAYMENTS GRID -->
    <div class="view-grid">
        
        <!-- TOTALS CARD -->
        <div class="totals-card">
            <div class="card-header">
                <i class="fas fa-calculator"></i> Bill Summary
            </div>
            <div class="totals-body">
                <div class="total-row">
                    <span class="label"><i class="fas fa-receipt"></i> Subtotal</span>
                    <span class="value blue">
                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($subtotal, 0) ?>
                    </span>
                </div>
                
                <?php if ($effective_discount > 0): ?>
                <div class="total-row">
                    <span class="label">
                        <i class="fas fa-tags"></i> Total Discount
                    </span>
                    <span class="value red">
                        - <span class="currency-prefix"><?= $currency ?></span><?= number_format($effective_discount, 0) ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($premium_amount > 0): ?>
                <div class="total-row">
                    <span class="label"><i class="fas fa-star"></i> Premium Charge</span>
                    <span class="value purple">
                        + <span class="currency-prefix"><?= $currency ?></span><?= number_format($premium_amount, 0) ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="total-row grand">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                    <span class="value">
                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($total_amount, 0) ?>
                    </span>
                </div>
                
                <?php if ($paid_amount > 0): ?>
                <div class="total-row paid-row">
                    <span class="label"><i class="fas fa-check-circle"></i> Paid Amount</span>
                    <span class="value green">
                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($paid_amount, 0) ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($balance > 0): ?>
                <div class="total-row balance-row">
                    <span class="label"><i class="fas fa-exclamation-circle"></i> Balance Due</span>
                    <span class="value red">
                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($balance, 0) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PAYMENTS CARD -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-money-check-alt"></i> Payment History (<?= count($payments) ?>)
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                    <div class="payments-list">
                        <?php foreach ($payments as $payment): 
                            $p_name = $payment['received_by_name'] ?? 'N/A';
                            $p_parts = explode(' ', trim($p_name));
                            $p_initials = count($p_parts) >= 2 
                                ? strtoupper(substr($p_parts[0], 0, 1) . substr($p_parts[1], 0, 1))
                                : strtoupper(substr($p_name, 0, 2));
                        ?>
                            <div class="payment-item">
                                <div class="payment-left">
                                    <div class="payment-avatar"><?= htmlspecialchars($p_initials) ?></div>
                                    <div class="payment-info">
                                        <span class="payment-receipt">
                                            <i class="fas fa-receipt" style="font-size:0.65rem;opacity:0.7;"></i>
                                            <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
                                        </span>
                                        <span class="payment-received-by">
                                            By: <?= htmlspecialchars($p_name) ?>
                                            <?php if (!empty($payment['received_by_role'])): ?>
                                                <span style="font-size:0.55rem;background:var(--primary-bg);color:var(--primary);padding:1px 5px;border-radius:4px;font-weight:800;text-transform:uppercase;margin-left:3px;">
                                                    <?= strtoupper($payment['received_by_role']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="payment-right">
                                    <span class="payment-amount">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($payment['amount'] ?? 0, 0) ?>
                                    </span>
                                    <span class="payment-date">
                                        <i class="fas fa-clock" style="font-size:0.6rem;"></i>
                                        <?= date('d M Y, H:i', strtotime($payment['received_at'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-money-check-alt"></i>
                        <p>No payments recorded yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="info-text">
                <div class="info-title">View Only</div>
                <div class="info-sub">Audit role has read-only access to bills</div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Bill
            </button>
            <a href="revenue.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Revenue
            </a>
        </div>
    </div>

</main>

<script>
console.log('%c📄 View Bill - Audit (VIEW ONLY)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ AUDIT ROLE', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c✅ VIEW ONLY - No Edit/Delete', 'font-size:13px; color:#FCD34D; font-weight:bold;');
console.log('%c✅ Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#34D399;');
console.log('%c💰 Subtotal: <?= $currency ?> <?= number_format($subtotal, 0) ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c🏷️ Discount: <?= $currency ?> <?= number_format($effective_discount, 0) ?>', 'font-size:13px; color:#DC2626;');
console.log('%c⭐ Premium: <?= $currency ?> <?= number_format($premium_amount, 0) ?>', 'font-size:13px; color:#7C3AED;');
console.log('%c💎 Total: <?= $currency ?> <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#059669; font-weight:bold;');
</script>

</body>
</html>