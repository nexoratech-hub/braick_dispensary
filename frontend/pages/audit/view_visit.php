<?php
// ================================================================
// FILE: frontend/pages/audit/view_visit.php
// AUDIT - VIEW VISIT DETAILS (V10 - BRANCH LOCKED CLEAN)
// ✅ AUDIT ANAONA VISIT ZA BRANCH YAKE TU
// ✅ ONDOA READ-ONLY notice na AUDIT MODE notice
// ✅ V9: Bills table IMETOLEWA
// ✅ V9: Payments = table MOJA (grouped by bill, partial payments)
// ✅ V9: Summary cards ZOTE kwenye ROW MOJA
// ✅ V9: Prescriptions - buttons zimetolewa
// ✅ V9: Kila table inajifunga/kufunguka (toggle)
// ✅ V9: Scroll buttons <> kwenye kila table header
// ✅ Timezone: Africa/Dar_es_Salaam
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'audit') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ✅ AUDIT ANAONA BRANCH YAKE TU
$selected_branch_id = $user_branch_id;

if ($visit_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

function money($amount, $currency = '') {
    $formatted = number_format((float)$amount, 0, '.', ',');
    return $currency ? $currency . ' ' . $formatted : $formatted;
}

$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// FETCH VISIT - ✅ BRANCH YA ALIYE LOGIN TU
$visit = null;
try {
    $stmt = $db->prepare("SELECT 
        v.*, pat.patient_id as patient_code, pat.full_name as patient_name,
        pat.phone as patient_phone, pat.gender, pat.date_of_birth, pat.address,
        pat.blood_group, pat.allergies, pat.created_at as patient_since,
        d.full_name as doctor_name, d.role as doctor_role, d.email as doctor_email,
        r.full_name as receptionist_name, r.role as receptionist_role,
        ab.full_name as assigned_by_name, br.name as branch_name
        FROM visits v
        LEFT JOIN patients pat ON v.patient_id = pat.id
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN users ab ON v.assigned_by_id = ab.id
        LEFT JOIN branches br ON v.branch_id = br.id
        WHERE v.id = ? AND v.branch_id = ?");
    $stmt->execute([$visit_id, $user_branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Error: " . $e->getMessage()); }

if (!$visit) {
    $_SESSION['error_message'] = "Visit not found or you don't have permission to view this visit (different branch).";
    header('Location: dashboard.php');
    exit;
}

// FETCH BILLS
$bills = [];
try {
    $stmt = $db->prepare("SELECT b.*, u.full_name as created_by_name, u.role as created_by_role
        FROM bills b LEFT JOIN users u ON b.created_by = u.id
        WHERE b.visit_id = ? AND b.branch_id = ? ORDER BY b.created_at ASC");
    $stmt->execute([$visit_id, $user_branch_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FETCH BILL ITEMS
$bill_items = [
    'consultation' => [], 'registration' => [], 'lab_test' => [],
    'medication' => [], 'procedure' => [], 'equipment' => [],
    'tool' => [], 'other' => []
];
$total_billed = 0; $total_paid = 0; $total_balance = 0;
$total_discount = 0; $total_premium = 0;
$bill_ids = array_column($bills, 'id');

if (!empty($bill_ids)) {
    foreach ($bills as $b) {
        $total_billed += (float)$b['total_amount'];
        $total_paid += (float)$b['paid_amount'];
        $total_balance += (float)$b['balance'];
        $total_discount += (float)$b['total_discount'];
        $total_premium += (float)$b['premium_amount'];
    }
    
    $placeholders = implode(',', array_fill(0, count($bill_ids), '?'));
    try {
        $sql = "SELECT bi.*, b.bill_number, b.status as bill_status, b.created_at as bill_created_at, u.full_name as created_by_name
            FROM bill_items bi
            LEFT JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN users u ON b.created_by = u.id
            WHERE bi.bill_id IN ($placeholders) AND bi.status != 'cancelled'
            ORDER BY FIELD(bi.item_type, 'consultation', 'registration', 'lab_test', 'medication', 'procedure', 'equipment', 'tool', 'other'), bi.id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($bill_ids);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $type = $item['item_type'];
            if (!isset($bill_items[$type])) $bill_items['other'][] = $item;
            else $bill_items[$type][] = $item;
        }
    } catch (Exception $e) {}
}

$category_totals = [];
foreach ($bill_items as $cat => $items) {
    $subtotal = 0; $discount = 0; $final = 0;
    foreach ($items as $it) {
        $subtotal += (float)$it['total_price'];
        $discount += (float)$it['discount_amount'];
        $final += (float)$it['total_price'] - (float)$it['discount_amount'];
    }
    $category_totals[$cat] = ['count' => count($items), 'subtotal' => $subtotal, 'discount' => $discount, 'final' => $final];
}

// FETCH PRESCRIPTIONS - ✅ BRANCH YA ALIYE LOGIN TU
$prescriptions = [];
try {
    $stmt = $db->prepare("SELECT p.*, u.full_name as doctor_name FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id 
        WHERE p.visit_id = ? AND p.branch_id = ? ORDER BY p.id");
    $stmt->execute([$visit_id, $user_branch_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FETCH PAYMENTS
$payments = [];
try {
    if (!empty($bill_ids)) {
        $placeholders = implode(',', array_fill(0, count($bill_ids), '?'));
        $stmt = $db->prepare("SELECT p.*, u.full_name as received_by_name, u.role as received_by_role, b.bill_number
            FROM payments p
            LEFT JOIN users u ON p.received_by = u.id
            LEFT JOIN bills b ON p.bill_id = b.id
            WHERE p.bill_id IN ($placeholders)
            ORDER BY p.bill_id ASC, p.received_at ASC");
        $stmt->execute($bill_ids);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$total_payments_sum = 0;
foreach ($payments as $pay) $total_payments_sum += (float)$pay['amount'];

// GROUP PAYMENTS BY BILL
$payments_by_bill = [];
foreach ($payments as $pay) {
    $bid = $pay['bill_id'] ?? 0;
    if (!isset($payments_by_bill[$bid])) {
        $payments_by_bill[$bid] = ['bill_number' => $pay['bill_number'] ?? 'N/A', 'payments' => [], 'total' => 0];
    }
    $payments_by_bill[$bid]['payments'][] = $pay;
    $payments_by_bill[$bid]['total'] += (float)$pay['amount'];
}

$bill_details_map = [];
foreach ($bills as $b) $bill_details_map[$b['id']] = $b;

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Visit • <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?> • AUDIT</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-bg: #E8F0FE;
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
    --teal: #0D9488;
    --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --border-strong: #CBD5E1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-full: 9999px;
}

[data-theme="dark"] {
    --bg-body: #0B1220;
    --bg-card: #111C33;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #1E2E4A;
    --border-strong: #2A3E5F;
    --primary-bg: #12294A;
    --success-bg: #0F2E22;
    --danger-bg: #3A1414;
    --warning-bg: #3A2A0F;
    --purple-bg: #2A1A4A;
    --cyan-bg: #0A2E3A;
    --slate-bg: #1E2A3D;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
    line-height: 1.5;
    min-height: 100vh;
}

.money-number, .money-cell, .font-mono {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header { 
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); 
    border-radius: var(--radius-lg); 
    padding: 24px 28px; 
    margin-bottom: 20px; 
    display: flex; 
    flex-wrap: wrap; 
    justify-content: space-between; 
    align-items: center; 
    gap: 16px; 
    box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35); 
    position: relative; 
    overflow: hidden; 
}
.page-header::before { 
    content: ''; 
    position: absolute; 
    top: -50%; right: -10%; 
    width: 400px; height: 400px; 
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); 
    border-radius: 50%; 
    pointer-events: none; 
}
.page-header .page-title { 
    color: white; 
    font-size: 1.5rem; 
    font-weight: 900; 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    flex-wrap: wrap; 
    position: relative; 
    z-index: 1; 
}
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; }
.page-header .page-subtitle { 
    color: rgba(255,255,255,0.9); 
    font-size: 0.8rem; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    flex-wrap: wrap; 
    margin-top: 8px; 
    position: relative; 
    z-index: 1; 
}
.header-badge {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
}
.header-badge.green { background: linear-gradient(135deg, #10B981, #059669); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.purple { background: linear-gradient(135deg, #7C3AED, #A78BFA); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.cyan { background: linear-gradient(135deg, #0891B2, #22D3EE); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.audit-tag { background: linear-gradient(135deg, #3B82F6, #2563EB); font-weight: 800; }

.btn-header { 
    background: rgba(255,255,255,0.15); 
    color: white; 
    border: 1px solid rgba(255,255,255,0.25); 
    padding: 10px 16px; 
    border-radius: var(--radius-sm); 
    font-weight: 700; 
    font-size: 0.75rem; 
    transition: all 0.25s; 
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    backdrop-filter: blur(10px); 
    position: relative; 
    z-index: 1; 
    cursor: pointer; 
}
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.btn-header.success { background: rgba(16,185,129,0.3); border-color: rgba(255,255,255,0.3); }
.btn-header.success:hover { background: rgba(16,185,129,0.6); }
.btn-header.audit { background: linear-gradient(135deg, #7C3AED, #6D28D9); border-color: rgba(255,255,255,0.3); }
.btn-header.audit:hover { background: linear-gradient(135deg, #6D28D9, #5B21B6); }

.card { 
    background: var(--bg-card); 
    border-radius: var(--radius-lg); 
    border: 1px solid var(--border-color); 
    overflow: hidden; 
    box-shadow: var(--shadow-sm); 
    margin-bottom: 20px; 
}

/* COLLAPSIBLE CARD (Toggle) */
.collapsible-card { 
    background: var(--bg-card); 
    border-radius: var(--radius-lg); 
    border: 1px solid var(--border-color); 
    overflow: hidden; 
    box-shadow: var(--shadow-sm); 
    margin-bottom: 20px; 
}
.collapsible-card > summary {
    list-style: none;
    cursor: pointer;
    padding: 14px 20px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    user-select: none;
    transition: all 0.25s;
}
.collapsible-card > summary::-webkit-details-marker { display: none; }
.collapsible-card > summary::marker { display: none; content: ''; }
.collapsible-card > summary:hover { filter: brightness(1.08); }

.collapsible-card.cyan > summary { background: linear-gradient(135deg, #0891B2, #0E7490); }
.collapsible-card.purple > summary { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.collapsible-card.green > summary { background: linear-gradient(135deg, #059669, #047857); }
.collapsible-card.dark > summary { background: linear-gradient(135deg, #1E293B, #334155); }

.collapsible-card > summary .title { 
    color: white; font-size: 0.88rem; font-weight: 800; 
    display: flex; align-items: center; gap: 10px; 
}
.collapsible-card > summary .title i { color: #93C5FD; }
.collapsible-card.green > summary .title i,
.collapsible-card.purple > summary .title i,
.collapsible-card.cyan > summary .title i { color: rgba(255,255,255,0.85); }

.toggle-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    color: white;
    font-size: 0.7rem;
    transition: transform 0.3s ease;
    border: 1px solid rgba(255,255,255,0.25);
    flex-shrink: 0;
}
.collapsible-card[open] .toggle-icon { transform: rotate(180deg); }

.collapsible-body { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

.header-right-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.header-scroll-btns {
    display: inline-flex;
    gap: 4px;
    background: rgba(255,255,255,0.15);
    padding: 3px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
}
.header-scroll-btn {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    transition: all 0.2s;
    font-weight: 700;
}
.header-scroll-btn:hover { background: rgba(255,255,255,0.4); transform: scale(1.1); }
.header-scroll-btn:active { transform: scale(0.95); }

.collapsible-card > summary .count { 
    color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700; 
    background: rgba(255,255,255,0.18); padding: 5px 12px; 
    border-radius: var(--radius-full); backdrop-filter: blur(10px); 
    display: inline-flex; align-items: center; gap: 8px;
}

/* SUMMARY GRID */
.summary-grid-single-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    padding: 16px 18px;
    background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
    border-top: 2px solid var(--primary);
}
.summary-box-compact {
    background: white;
    border-radius: var(--radius-md);
    padding: 10px 12px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    transition: all 0.25s;
    min-width: 0;
}
.summary-box-compact:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.summary-label-compact {
    font-size: 0.55rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 800;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.summary-label-compact i { font-size: 0.65rem; color: var(--primary); }
.summary-value-compact {
    font-family: var(--font-mono);
    font-size: 0.92rem;
    font-weight: 900;
    color: var(--text-primary);
    letter-spacing: -0.03em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.summary-value-compact.billed { color: var(--primary); }
.summary-value-compact.paid { color: var(--success); }
.summary-value-compact.balance { color: var(--danger); }
.summary-value-compact.discount { color: var(--warning); }
.summary-value-compact.premium { color: var(--purple); }
.summary-value-compact.items { color: var(--cyan); }

@media (max-width: 1200px) {
    .summary-grid-single-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
    .summary-grid-single-row { grid-template-columns: repeat(2, 1fr); }
}

/* INFO GRID */
.info-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
    gap: 0; 
    background: var(--slate-bg); 
    border-bottom: 1px solid var(--border-color); 
}
.info-item { 
    padding: 14px 18px; 
    border-right: 1px solid var(--border-color); 
    border-bottom: 1px solid var(--border-color); 
}
.info-label { 
    font-size: 0.6rem; 
    color: var(--text-secondary); 
    text-transform: uppercase; 
    letter-spacing: 0.08em; 
    font-weight: 800; 
    margin-bottom: 5px; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
}
.info-label i { color: var(--primary); font-size: 0.7rem; }
.info-value { 
    font-size: 0.85rem; 
    font-weight: 700; 
    color: var(--text-primary); 
    word-break: break-word; 
}
.info-value.diagnosis { color: var(--danger); font-weight: 800; }
.info-value.treatment { color: var(--success); }
.info-value.doctor { color: var(--purple); }
.info-value.receptionist { color: var(--primary); }

.patient-info-header { 
    background: linear-gradient(135deg, #0B5ED7, #7C3AED); 
    padding: 20px 24px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    flex-wrap: wrap; 
    gap: 14px; 
    position: relative; 
    overflow: hidden; 
}
.patient-info-left { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; }
.patient-avatar {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 3px solid rgba(255,255,255,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    color: white;
    flex-shrink: 0;
    text-transform: uppercase;
}
.patient-name { font-size: 1.25rem; font-weight: 900; color: white; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.patient-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.72rem; color: rgba(255,255,255,0.9); margin-top: 6px; }
.patient-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,0.15);
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.15);
}
.patient-stats { display: flex; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; }
.patient-stat {
    background: rgba(255,255,255,0.18);
    padding: 10px 16px;
    border-radius: var(--radius-md);
    text-align: center;
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 100px;
}
.patient-stat-label { font-size: 0.58rem; color: rgba(255,255,255,0.8); text-transform: uppercase; font-weight: 800; margin-bottom: 3px; }
.patient-stat-value { font-size: 1rem; font-weight: 900; color: white; font-family: var(--font-mono); }

/* ITEM CATEGORIES */
.item-category-block { border-bottom: 1px solid var(--border-color); }
.item-category-block:last-child { border-bottom: none; }

.item-category-header {
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.item-category-header.consultation { background: linear-gradient(90deg, #D1FAE5, #A7F3D0); color: #065F46; border-left: 4px solid #059669; }
.item-category-header.lab_test { background: linear-gradient(90deg, #DBEAFE, #BFDBFE); color: #1E40AF; border-left: 4px solid #3B82F6; }
.item-category-header.medication { background: linear-gradient(90deg, #FEF3C7, #FDE68A); color: #92400E; border-left: 4px solid #D97706; }
.item-category-header.procedure { background: linear-gradient(90deg, #CCFBF1, #99F6E4); color: #115E59; border-left: 4px solid #0D9488; }
.item-category-header.equipment { background: linear-gradient(90deg, #EDE9FE, #DDD6FE); color: #5B21B6; border-left: 4px solid #7C3AED; }
.item-category-header.registration { background: linear-gradient(90deg, #F1F5F9, #E2E8F0); color: #475569; border-left: 4px solid #94A3B8; }
.item-category-header.other { background: linear-gradient(90deg, #FCE7F3, #FBCFE8); color: #9D174D; border-left: 4px solid #DB2777; }
.item-category-header.tool { background: linear-gradient(90deg, #E0E7FF, #C7D2FE); color: #3730A3; border-left: 4px solid #6366F1; }

.item-category-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.item-category-count {
    font-size: 0.62rem;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: var(--radius-full);
    background: rgba(0,0,0,0.12);
    font-family: var(--font-mono);
}
.item-category-total {
    font-family: var(--font-mono);
    font-weight: 900;
    font-size: 0.85rem;
}
.category-scroll-btn {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(0,0,0,0.15);
    border: none;
    color: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    transition: all 0.25s;
    font-weight: 700;
}
.category-scroll-btn:hover { background: rgba(0,0,0,0.3); transform: translateY(-2px); }

/* TABLES */
.items-table-wrapper { 
    overflow-x: auto; 
    scroll-behavior: smooth; 
    background: var(--bg-card); 
}
.items-table-wrapper::-webkit-scrollbar { height: 8px; }
.items-table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.items-table-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 10px; }

.items-table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 0.78rem; 
    min-width: 900px; 
}
.items-table thead th {
    text-align: left;
    padding: 10px 14px;
    font-weight: 800;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-secondary);
    background: var(--slate-bg);
    white-space: nowrap;
    border-bottom: 1px solid var(--border-color);
}
.items-table tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}
.items-table tbody tr:hover td { background: var(--primary-bg); }
.items-table tbody tr:last-child td { border-bottom: none; }

.item-index { 
    text-align: center; 
    font-weight: 800; 
    color: var(--text-secondary); 
    font-family: var(--font-mono); 
    font-size: 0.7rem; 
    width: 45px; 
}
.item-name-cell { 
    font-weight: 700; 
    font-size: 0.78rem; 
    color: var(--text-primary); 
    word-break: break-word; 
    min-width: 200px; 
}
.item-qty-cell { 
    text-align: center; 
    font-weight: 800; 
    color: var(--primary); 
    font-family: var(--font-mono); 
    font-size: 0.82rem; 
}
.item-price-cell { 
    text-align: right; 
    font-family: var(--font-mono); 
    font-weight: 700; 
    font-size: 0.75rem; 
    white-space: nowrap; 
}
.item-price-cell.unit { color: var(--text-secondary); }
.item-price-cell.total { color: var(--success); font-weight: 800; }
.item-price-cell.discount { color: var(--warning); font-weight: 800; }
.item-price-cell.final { color: var(--primary); font-weight: 900; }
.item-status-cell { text-align: center; }
.item-actions-cell { text-align: center; white-space: nowrap; }

.status-badge { 
    display: inline-flex; 
    align-items: center; 
    gap: 4px; 
    padding: 4px 10px; 
    border-radius: var(--radius-full); 
    font-size: 0.62rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    white-space: nowrap; 
}
.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.partial { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.dispensed { background: var(--purple-bg); color: var(--purple); border: 1px solid var(--purple); }
.status-badge.confirmed { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.in_progress { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }

.action-buttons { display: flex; gap: 5px; justify-content: center; align-items: center; }
.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    border: none;
    cursor: pointer;
    transition: all 0.25s;
    text-decoration: none;
}
.btn-action:hover { transform: translateY(-2px) scale(1.08); }
.btn-action.view { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
.btn-action.view:hover { background: #0B5ED7; color: white; }

/* PARTIAL PAYMENTS (BILL GROUP) */
.partial-payments-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: var(--radius-full);
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    color: #92400E;
    border: 1px solid #D97706;
    animation: pulsePartial 2.2s ease-in-out infinite;
}
@keyframes pulsePartial {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4); }
    50% { transform: scale(1.04); box-shadow: 0 0 0 6px rgba(217, 119, 6, 0); }
}

.payment-bill-group {
    border-bottom: 2px solid var(--border-color);
}
.payment-bill-group:last-child {
    border-bottom: none;
}

.payment-bill-header {
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.78rem;
    font-weight: 800;
    border-left: 4px solid var(--primary);
    background: linear-gradient(90deg, #EFF6FF, #DBEAFE);
    color: #1E40AF;
}
.payment-bill-header.bill-paid { border-left-color: #059669; background: linear-gradient(90deg, #ECFDF5, #D1FAE5); color: #065F46; }
.payment-bill-header.bill-partial { border-left-color: #0891B2; background: linear-gradient(90deg, #ECFEFF, #CFFAFE); color: #155E75; }
.payment-bill-header.bill-pending { border-left-color: #D97706; background: linear-gradient(90deg, #FFFBEB, #FEF3C7); color: #92400E; }

.bill-info-line {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 0.68rem;
    font-weight: 700;
}
.bill-info-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255,255,255,0.7);
    padding: 3px 10px;
    border-radius: var(--radius-full);
    font-family: var(--font-mono);
    font-size: 0.66rem;
}
.bill-info-item i { font-size: 0.7rem; }
.bill-info-item.total { color: var(--primary); }
.bill-info-item.paid { color: var(--success); }
.bill-info-item.balance { color: var(--danger); }

.payment-row-adjusted { background: rgba(254, 243, 199, 0.4) !important; }
.payment-row-adjusted:hover td { background: rgba(254, 243, 199, 0.7) !important; }

.verify-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
}
.verify-badge.match { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.verify-badge.mismatch { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.empty-state { 
    padding: 50px 20px; 
    text-align: center; 
    color: var(--text-secondary); 
}
.empty-state i { 
    font-size: 2.5rem; 
    opacity: 0.3; 
    display: block; 
    margin-bottom: 12px; 
    color: var(--primary); 
}

@media (max-width: 1024px) {
    .info-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 18px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    .info-grid { grid-template-columns: 1fr; }
    .patient-info-header { flex-direction: column; align-items: stretch; }
    .summary-grid-single-row { grid-template-columns: repeat(2, 1fr); }
}

@media print {
    .btn-header, .action-buttons, .category-scroll-btn, .header-scroll-btns, .toggle-icon { display: none !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .collapsible-card[open] .collapsible-body { display: block !important; }
    .card { break-inside: avoid; }
}
</style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Visit Details
                <span class="header-badge audit-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <span class="header-badge cyan"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <span class="header-badge"><i class="fas fa-calendar"></i> <?= !empty($visit['visit_date']) ? date('d M Y, H:i', strtotime($visit['visit_date'])) : 'N/A' ?></span>
                <?php if (!empty($visit['visit_type'])): ?>
                <span class="header-badge"><i class="fas fa-tag"></i> <?= htmlspecialchars($visit['visit_type']) ?></span>
                <?php endif; ?>
                <span class="header-badge green"><i class="fas fa-info-circle"></i> <?= htmlspecialchars(strtoupper($visit['status'] ?? 'N/A')) ?></span>
                <span class="header-badge purple"><i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($total_billed, 0) ?></span>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? $user_branch_name) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header success">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn-header audit">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- PATIENT INFO -->
    <div class="card">
        <div class="patient-info-header">
            <div class="patient-info-left">
                <?php 
                    $name_parts = explode(' ', trim($visit['patient_name'] ?? 'N/A'));
                    $initials = count($name_parts) >= 2 ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) : strtoupper(substr($visit['patient_name'] ?? 'NA', 0, 2));
                ?>
                <div class="patient-avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <div class="patient-name">
                        <i class="fas fa-user-circle" style="font-size:1rem;opacity:0.8;"></i>
                        <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                    </div>
                    <div class="patient-meta">
                        <?php if (!empty($visit['patient_code'])): ?>
                            <span class="patient-meta-item"><i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_code']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visit['patient_phone'])): ?>
                            <span class="patient-meta-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($visit['patient_phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visit['gender'])): ?>
                            <span class="patient-meta-item"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars(ucfirst($visit['gender'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visit['blood_group'])): ?>
                            <span class="patient-meta-item"><i class="fas fa-tint"></i> <?= htmlspecialchars($visit['blood_group']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visit['allergies'])): ?>
                            <span class="patient-meta-item" style="background:rgba(220,38,38,0.4);"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($visit['allergies']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="patient-stats">
                <div class="patient-stat">
                    <div class="patient-stat-label">Billed</div>
                    <div class="patient-stat-value"><?= number_format($total_billed, 0) ?></div>
                </div>
                <div class="patient-stat">
                    <div class="patient-stat-label">Paid</div>
                    <div class="patient-stat-value"><?= number_format($total_paid, 0) ?></div>
                </div>
                <?php if ($total_balance > 0): ?>
                <div class="patient-stat" style="background:rgba(220,38,38,0.3);">
                    <div class="patient-stat-label">Balance</div>
                    <div class="patient-stat-value"><?= number_format($total_balance, 0) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- VISIT INFO (TOGGLE) -->
    <details class="collapsible-card cyan" open>
        <summary>
            <span class="title"><i class="fas fa-info-circle"></i> Visit Information</span>
            <span class="count">
                <?= count($bills) ?> Bill(s) • <?= array_sum(array_column($category_totals, 'count')) ?> Item(s)
                <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
            </span>
        </summary>
        <div class="collapsible-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-hashtag"></i> Visit Number</div>
                    <div class="info-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-calendar"></i> Visit Date</div>
                    <div class="info-value"><?= !empty($visit['visit_date']) ? date('d M Y, H:i', strtotime($visit['visit_date'])) : 'N/A' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-md"></i> Doctor</div>
                    <div class="info-value doctor"><?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-tie"></i> Receptionist</div>
                    <div class="info-value receptionist"><?= htmlspecialchars($visit['receptionist_name'] ?? 'Not Assigned') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-store-alt"></i> Branch</div>
                    <div class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>
    </details>

    <!-- CLINICAL INFO (TOGGLE) -->
    <?php if (!empty($visit['diagnosis']) || !empty($visit['treatment']) || !empty($visit['complaint'])): ?>
    <details class="collapsible-card purple" open>
        <summary>
            <span class="title"><i class="fas fa-notes-medical"></i> Clinical Information</span>
            <span class="count">
                Diagnosis & Treatment
                <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
            </span>
        </summary>
        <div class="collapsible-body">
            <div class="info-grid">
                <?php if (!empty($visit['complaint'])): ?>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-comment-medical"></i> Chief Complaint</div>
                    <div class="info-value"><?= htmlspecialchars($visit['complaint']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <div class="info-label"><i class="fas fa-diagnoses"></i> Diagnosis</div>
                    <div class="info-value diagnosis"><?= htmlspecialchars($visit['diagnosis']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <div class="info-label"><i class="fas fa-prescription-bottle-medical"></i> Treatment Plan</div>
                    <div class="info-value treatment"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <!-- BILL ITEMS BY CATEGORY (TOGGLE) -->
    <details class="collapsible-card dark" open>
        <summary>
            <span class="title"><i class="fas fa-list-check"></i> Bill Items by Category</span>
            <div class="header-right-group">
                <span class="count">
                    <?= array_sum(array_column($category_totals, 'count')) ?> Items • <?= $currency ?> <?= number_format($total_billed, 0) ?>
                </span>
                <div class="header-scroll-btns">
                    <button type="button" class="header-scroll-btn" onclick="event.preventDefault(); scrollCategoryTable('items-main-table', 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="header-scroll-btn" onclick="event.preventDefault(); scrollCategoryTable('items-main-table', 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                </div>
                <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
            </div>
        </summary>
        <div class="collapsible-body">
        
        <?php 
        $categories = [
            'consultation' => ['label' => 'Consultation', 'icon' => 'fa-stethoscope'],
            'registration' => ['label' => 'Registration', 'icon' => 'fa-user-plus'],
            'lab_test' => ['label' => 'Lab Tests', 'icon' => 'fa-flask'],
            'medication' => ['label' => 'Medications', 'icon' => 'fa-pills'],
            'procedure' => ['label' => 'Procedures', 'icon' => 'fa-procedures'],
            'equipment' => ['label' => 'Medical Equipment', 'icon' => 'fa-toolbox'],
            'tool' => ['label' => 'Tools', 'icon' => 'fa-tools'],
            'other' => ['label' => 'Other Items', 'icon' => 'fa-box']
        ];
        
        $has_any_items = false;
        foreach ($categories as $cat_key => $cat_info):
            if (empty($bill_items[$cat_key])) continue;
            $has_any_items = true;
            $cat_items = $bill_items[$cat_key];
            $cat_count = count($cat_items);
            $cat_subtotal = $category_totals[$cat_key]['subtotal'];
            $cat_discount = $category_totals[$cat_key]['discount'];
            $cat_final = $category_totals[$cat_key]['final'];
            $scroll_id = 'cat_' . $cat_key . '_' . uniqid();
        ?>
        
        <div class="item-category-block">
            <div class="item-category-header <?= $cat_key ?>">
                <div class="item-category-title">
                    <i class="fas <?= $cat_info['icon'] ?>"></i>
                    <?= $cat_info['label'] ?>
                    <span class="item-category-count"><?= $cat_count ?> item<?= $cat_count > 1 ? 's' : '' ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="item-category-total"><?= $currency ?> <?= number_format($cat_final, 0) ?></span>
                    <button type="button" class="category-scroll-btn" onclick="scrollCategoryTable('<?= $scroll_id ?>', 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="category-scroll-btn" onclick="scrollCategoryTable('<?= $scroll_id ?>', 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <div class="items-table-wrapper" id="<?= $scroll_id ?>">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:45px;text-align:center;">#</th>
                            <th>Item Name</th>
                            <th style="text-align:center;width:80px;">Qty</th>
                            <th style="text-align:right;width:120px;">Unit Price</th>
                            <th style="text-align:right;width:120px;">Total Price</th>
                            <th style="text-align:right;width:110px;">Discount</th>
                            <th style="text-align:right;width:120px;">Final Price</th>
                            <th style="text-align:center;width:100px;">Status</th>
                            <th style="text-align:center;width:80px;">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $item_idx = 1; foreach ($cat_items as $item): 
                            $item_discount = (float)($item['discount_amount'] ?? 0);
                            $item_total = (float)($item['total_price'] ?? 0);
                            $item_final = $item_total - $item_discount;
                            $item_status = strtolower($item['status'] ?? 'pending');
                            $status_class = 'pending';
                            $status_icon = 'fa-clock';
                            if ($item_status === 'paid') { $status_class = 'paid'; $status_icon = 'fa-check-circle'; }
                            elseif ($item_status === 'cancelled') { $status_class = 'cancelled'; $status_icon = 'fa-times-circle'; }
                        ?>
                        <tr>
                            <td class="item-index"><?= $item_idx++ ?></td>
                            <td class="item-name-cell">
                                <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                <?php if (!empty($item['bill_number'])): ?>
                                    <div style="font-size:0.6rem;color:var(--primary);font-family:var(--font-mono);font-weight:700;margin-top:3px;">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($item['bill_number']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="item-qty-cell"><?= number_format((int)($item['quantity'] ?? 1)) ?></td>
                            <td class="item-price-cell unit"><?= $currency ?> <?= number_format((float)($item['unit_price'] ?? 0), 0) ?></td>
                            <td class="item-price-cell total"><?= $currency ?> <?= number_format($item_total, 0) ?></td>
                            <td class="item-price-cell discount"><?= $item_discount > 0 ? '- ' . $currency . ' ' . number_format($item_discount, 0) : '—' ?></td>
                            <td class="item-price-cell final"><?= $currency ?> <?= number_format($item_final, 0) ?></td>
                            <td class="item-status-cell">
                                <span class="status-badge <?= $status_class ?>"><i class="fas <?= $status_icon ?>"></i> <?= strtoupper($item_status) ?></span>
                            </td>
                            <td class="item-actions-cell">
                                <a href="view_bill_item.php?id=<?= $item['id'] ?>" class="btn-action view" title="View"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--slate-bg);font-weight:800;">
                            <td colspan="4" style="text-align:right;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">
                                <i class="fas <?= $cat_info['icon'] ?>"></i> <?= $cat_info['label'] ?> Subtotal
                            </td>
                            <td class="item-price-cell total"><?= $currency ?> <?= number_format($cat_subtotal, 0) ?></td>
                            <td class="item-price-cell discount"><?= $cat_discount > 0 ? '- ' . $currency . ' ' . number_format($cat_discount, 0) : '—' ?></td>
                            <td class="item-price-cell final"><?= $currency ?> <?= number_format($cat_final, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php endforeach; ?>
        
        <?php if (!$has_any_items): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No bill items found.</p>
        </div>
        <?php endif; ?>
        
        <!-- SUMMARY - ZOTE KWENYE ROW MOJA -->
        <?php if ($has_any_items): ?>
        <div class="summary-grid-single-row">
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-list"></i> Total Items</div>
                <div class="summary-value-compact items"><?= array_sum(array_column($category_totals, 'count')) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-tag"></i> Discount</div>
                <div class="summary-value-compact discount"><?= $currency ?> <?= number_format($total_discount, 0) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-star"></i> Premium</div>
                <div class="summary-value-compact premium"><?= $currency ?> <?= number_format($total_premium, 0) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-file-invoice"></i> Billed</div>
                <div class="summary-value-compact billed"><?= $currency ?> <?= number_format($total_billed, 0) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-check-circle"></i> Paid</div>
                <div class="summary-value-compact paid"><?= $currency ?> <?= number_format($total_paid, 0) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-exclamation-circle"></i> Balance</div>
                <div class="summary-value-compact balance"><?= $currency ?> <?= number_format($total_balance, 0) ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        </div>
    </details>

    <!-- PAYMENTS (TOGGLE) - GROUPED BY BILL, PARTIAL PAYMENTS -->
    <?php if (!empty($payments)): ?>
    <details class="collapsible-card green" open>
        <summary>
            <span class="title"><i class="fas fa-money-bill-wave"></i> Payments (<?= count($payments) ?>)</span>
            <div class="header-right-group">
                <span class="count">
                    <?= $currency ?> <?= number_format($total_payments_sum, 0) ?>
                    <?php if (abs($total_payments_sum - $total_paid) < 1): ?>
                        <span class="verify-badge match"><i class="fas fa-check-circle"></i> MATCH</span>
                    <?php else: ?>
                        <span class="verify-badge mismatch"><i class="fas fa-exclamation-triangle"></i> DIFF</span>
                    <?php endif; ?>
                </span>
                <div class="header-scroll-btns">
                    <button type="button" class="header-scroll-btn" onclick="event.preventDefault(); scrollCategoryTable('payments-table', 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="header-scroll-btn" onclick="event.preventDefault(); scrollCategoryTable('payments-table', 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                </div>
                <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
            </div>
        </summary>
        <div class="collapsible-body">
        
        <?php foreach ($payments_by_bill as $bid => $bill_payment_data): 
            $bill_info = $bill_details_map[$bid] ?? null;
            $bill_number = $bill_payment_data['bill_number'];
            $bill_total = $bill_info ? (float)$bill_info['total_amount'] : 0;
            $bill_paid = $bill_info ? (float)$bill_info['paid_amount'] : 0;
            $bill_balance = $bill_info ? (float)$bill_info['balance'] : 0;
            $bill_status = $bill_info ? strtolower($bill_info['status']) : 'pending';
            $payment_count = count($bill_payment_data['payments']);
            $is_partial = $payment_count > 1;
            
            $bill_header_class = 'payment-bill-header';
            if ($bill_status === 'paid') $bill_header_class .= ' bill-paid';
            elseif ($bill_status === 'partial') $bill_header_class .= ' bill-partial';
            else $bill_header_class .= ' bill-pending';
        ?>
        
        <!-- BILL PAYMENT GROUP -->
        <div class="payment-bill-group">
            <div class="<?= $bill_header_class ?>">
                <div class="item-category-title">
                    <i class="fas fa-file-invoice"></i>
                    Bill: <?= htmlspecialchars($bill_number) ?>
                    <span class="item-category-count"><?= $payment_count ?> payment<?= $payment_count > 1 ? 's' : '' ?></span>
                    <?php if ($is_partial): ?>
                        <span class="partial-payments-badge"><i class="fas fa-layer-group"></i> PARTIAL PAYMENTS</span>
                    <?php endif; ?>
                </div>
                <div class="bill-info-line">
                    <span class="bill-info-item total"><i class="fas fa-money-bill"></i> Total: <?= number_format($bill_total, 0) ?></span>
                    <span class="bill-info-item paid"><i class="fas fa-check-circle"></i> Paid: <?= number_format($bill_paid, 0) ?></span>
                    <?php if ($bill_balance > 0): ?>
                    <span class="bill-info-item balance"><i class="fas fa-exclamation-circle"></i> Balance: <?= number_format($bill_balance, 0) ?></span>
                    <?php endif; ?>
                    <span class="item-category-total" style="color:inherit;"><?= $currency ?> <?= number_format($bill_payment_data['total'], 0) ?></span>
                </div>
            </div>
            
            <div class="items-table-wrapper" id="payments-table-<?= $bid ?>">
                <table class="items-table" style="min-width:950px;">
                    <thead>
                        <tr>
                            <th style="width:50px;text-align:center;">#</th>
                            <th>Receipt #</th>
                            <th>Payment Method</th>
                            <th>Received By</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th style="text-align:center;">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pay_idx = 1; foreach ($bill_payment_data['payments'] as $pay): 
                            $role = strtolower($pay['received_by_role'] ?? 'user');
                            $name_parts = explode(' ', trim($pay['received_by_name'] ?? 'N/A'));
                            $initials = count($name_parts) >= 2 ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) : strtoupper(substr($pay['received_by_name'] ?? 'NA', 0, 2));
                        ?>
                        <tr>
                            <td class="item-index"><?= $pay_idx++ ?></td>
                            <td class="item-name-cell">
                                <span style="font-size:0.68rem;color:var(--primary);font-weight:800;background:var(--primary-bg);padding:4px 8px;border-radius:5px;display:inline-block;font-family:var(--font-mono);">
                                    <?= htmlspecialchars($pay['receipt_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge partial" style="text-transform:none;">
                                    <i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pay['payment_method'] ?? 'Cash'))) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#0B5ED7,#3B82F6);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.65rem;text-transform:uppercase;flex-shrink:0;"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <div style="font-size:0.72rem;font-weight:700;"><?= htmlspecialchars($pay['received_by_name'] ?? 'N/A') ?></div>
                                        <div style="font-size:0.55rem;font-weight:800;color:var(--text-secondary);text-transform:uppercase;"><?= htmlspecialchars($role) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:0.7rem;"><?= !empty($pay['received_at']) ? date('d M Y, H:i', strtotime($pay['received_at'])) : '—' ?></td>
                            <td class="item-price-cell final" style="font-size:0.85rem;"><?= $currency ?> <?= number_format((float)($pay['amount'] ?? 0), 0) ?></td>
                            <td class="item-actions-cell">
                                <a href="view_payment.php?id=<?= $pay['id'] ?>" class="btn-action view" title="View"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr style="background:var(--slate-bg);font-weight:800;">
                            <td colspan="5" style="text-align:right;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);">
                                <i class="fas fa-calculator"></i> <?= htmlspecialchars($bill_number) ?> — Jumla ya Malipo (<?= $payment_count ?>)
                            </td>
                            <td class="item-price-cell final" style="font-size:0.9rem;"><?= $currency ?> <?= number_format($bill_payment_data['total'], 0) ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- JUMLA KUU -->
        <div class="summary-grid-single-row" style="grid-template-columns: repeat(4, 1fr);">
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-receipt"></i> Total Receipts</div>
                <div class="summary-value-compact items"><?= count($payments) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-file-invoice"></i> Bills</div>
                <div class="summary-value-compact items"><?= count($payments_by_bill) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-money-bill-wave"></i> Jumla ya Malipo</div>
                <div class="summary-value-compact paid"><?= $currency ?> <?= number_format($total_payments_sum, 0) ?></div>
            </div>
            <div class="summary-box-compact">
                <div class="summary-label-compact"><i class="fas fa-check-circle"></i> Hali</div>
                <div class="summary-value-compact" style="font-size:0.75rem;">
                    <?php if (abs($total_payments_sum - $total_paid) < 1): ?>
                        <span class="verify-badge match"><i class="fas fa-check-circle"></i> MATCH</span>
                    <?php else: ?>
                        <span class="verify-badge mismatch"><i class="fas fa-exclamation-triangle"></i> DIFF</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        </div>
    </details>
    <?php endif; ?>

    <!-- PRESCRIPTIONS (TOGGLE) - Bila View/Edit buttons -->
    <?php if (!empty($prescriptions)): ?>
    <details class="collapsible-card purple" open>
        <summary>
            <span class="title"><i class="fas fa-prescription"></i> Prescriptions (<?= count($prescriptions) ?>)</span>
            <span class="count">
                <?= count($prescriptions) ?> prescription<?= count($prescriptions) > 1 ? 's' : '' ?>
                <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
            </span>
        </summary>
        <div class="collapsible-body">
            <div class="items-table-wrapper" id="prescriptions-table">
                <table class="items-table" style="min-width:800px;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prescription #</th>
                            <th>Doctor</th>
                            <th style="text-align:center;">Status</th>
                            <th>Created</th>
                            <th>Dispensed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $prs_idx = 1; foreach ($prescriptions as $prs): 
                            $prs_status = strtolower($prs['status'] ?? 'pending');
                            $prs_class = 'pending'; $prs_icon = 'fa-clock';
                            if ($prs_status === 'dispensed') { $prs_class = 'dispensed'; $prs_icon = 'fa-check-circle'; }
                            elseif ($prs_status === 'confirmed') { $prs_class = 'confirmed'; $prs_icon = 'fa-check'; }
                            elseif ($prs_status === 'cancelled') { $prs_class = 'cancelled'; $prs_icon = 'fa-times-circle'; }
                        ?>
                        <tr>
                            <td class="item-index"><?= $prs_idx++ ?></td>
                            <td class="item-name-cell"><?= htmlspecialchars($prs['prescription_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($prs['doctor_name'] ?? 'N/A') ?></td>
                            <td class="item-status-cell"><span class="status-badge <?= $prs_class ?>"><i class="fas <?= $prs_icon ?>"></i> <?= strtoupper($prs_status) ?></span></td>
                            <td><?= !empty($prs['created_at']) ? date('d M Y, H:i', strtotime($prs['created_at'])) : '—' ?></td>
                            <td><?= !empty($prs['dispensed_at']) ? date('d M Y, H:i', strtotime($prs['dispensed_at'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <div style="text-align:center;padding:20px;font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-mono);border-top:1px solid var(--border-color);">
        <i class="fas fa-store-alt" style="color:#0B5ED7;"></i>
        <strong><?= htmlspecialchars($visit['branch_name'] ?? $user_branch_name) ?></strong>
        <span style="margin:0 8px;">|</span>
        <span id="footerTime"><?= date('H:i:s') ?></span>
    </div>

</main>

<script>
function scrollCategoryTable(elementId, direction) {
    var wrapper = document.getElementById(elementId);
    if (!wrapper) return;
    var amount = 400;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var el = document.getElementById('footerTime');
    if (el) el.textContent = timeStr;
}, 1000);

console.log('%c🔍 AUDIT - View Visit V10 (BRANCH LOCKED CLEAN)', 'font-size:16px; font-weight:bold; color:#7C3AED;');
console.log('%c✅ AUDIT ANAONA BRANCH YAKE TU', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c✅ READ-ONLY notice na AUDIT MODE notice zimeondolewa', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c👥 Branch: <?= htmlspecialchars($visit['branch_name'] ?? $user_branch_name) ?>', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c💰 Billed: <?= $currency ?> <?= number_format($total_billed, 0) ?> | Paid: <?= $currency ?> <?= number_format($total_paid, 0) ?>', 'font-size:12px; color:#7C3AED; font-weight:bold;');
</script>

</body>
</html>