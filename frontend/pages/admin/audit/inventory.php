<?php
// ================================================================
// FILE: frontend/pages/admin/audit/inventory.php
// AUDIT - INVENTORY REPORTS V2 (FIXED EQUIPMENT + PDF)
// ✅ Fixed: Equipment query syntax error
// ✅ Fixed: PDF Export using data attributes
// ✅ Reports-only: No Add/Edit/Delete buttons
// ✅ Font: Inter + JetBrains Mono (sawa na dashboard)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'audit': header('Location: /dispensary_system/frontend/pages/audit/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

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

function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') return '0';
    $amount = (float)$amount;
    if ($amount >= 1000000000) return number_format($amount / 1000000000, 1) . 'B';
    if ($amount >= 1000000) return number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . 'K';
    return number_format($amount, 0);
}

// BRANCH FILTER
$filter_by_branch = false;
$filter_branch_id = 0;
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $filter_by_branch = true;
    $filter_branch_id = (int)$selected_branch_id;
}

$branch_cond_med = $filter_by_branch ? " AND m.branch_id = ?" : "";
$branch_cond_eq  = $filter_by_branch ? " AND e.branch_id = ?" : "";
$branch_cond_bi  = $filter_by_branch ? " AND bi.branch_id = ?" : "";
$branch_params   = $filter_by_branch ? [$filter_branch_id] : [];

// BRANCHES LIST
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$display_branch_name = 'All Branches';
if ($filter_by_branch) {
    foreach ($branches as $b) {
        if ($b['id'] == $filter_branch_id) { $display_branch_name = $b['name']; break; }
    }
}

// DATE FILTERS
$quick_filter = $_GET['quick'] ?? '1m';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$date_label = "";
$date_cond_sales = "";

switch ($quick_filter) {
    case 'today':
        $date_cond_sales = " AND DATE(bi.created_at) = CURDATE()";
        $date_label = "Today";
        break;
    case '1w':
        $date_cond_sales = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 1 Week";
        break;
    case '1m':
        $date_cond_sales = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $date_cond_sales = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months";
        break;
    case '1y':
        $date_cond_sales = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year";
        break;
    case 'all':
        $date_label = "All Time";
        break;
    case 'custom':
        $date_cond_sales = " AND DATE(bi.created_at) BETWEEN ? AND ?";
        $date_label = date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to));
        break;
    default:
        $date_cond_sales = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
}

$date_params_sales = [];
if ($quick_filter === 'custom') $date_params_sales = [$date_from, $date_to];

// ================================================================
// MEDICINES INVENTORY
// ================================================================
$med_query = "
    SELECT 
        MIN(m.id) as id,
        m.medication_name, m.category, m.unit, m.branch_id,
        u.full_name as added_by_full_name, b.name as branch_name,
        SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN m.quantity ELSE 0 END) as total_quantity,
        MIN(m.reorder_level) as reorder_level,
        MIN(m.unit_cost) as unit_cost,
        MIN(m.selling_price) as selling_price,
        MIN(m.expiry_date) as expiry_date,
        GROUP_CONCAT(m.id) as batch_ids,
        GROUP_CONCAT(m.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(m.quantity SEPARATOR '|') as batch_quantities,
        GROUP_CONCAT(m.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(m.status SEPARATOR '|') as batch_statuses,
        MIN(DATEDIFF(CASE WHEN m.expiry_date = '0000-00-00' THEN NULL ELSE m.expiry_date END, CURDATE())) as days_remaining,
        CASE 
            WHEN SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN 1 ELSE 0 END) > 0 
            THEN 'active' ELSE 'inactive' 
        END as computed_status
    FROM medications_inventory m
    LEFT JOIN users u ON m.added_by = u.id
    LEFT JOIN branches b ON m.branch_id = b.id
    WHERE 1=1 $branch_cond_med
    GROUP BY m.medication_name, m.category, m.unit, m.branch_id
    ORDER BY m.medication_name ASC
";
$stmt = $db->prepare($med_query);
$stmt->execute($branch_params);
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ✅ FIXED: EQUIPMENT INVENTORY
// ================================================================
$equip_query = "
    SELECT 
        MIN(e.id) as id,
        e.equipment_name, e.category, e.unit, e.branch_id,
        u.full_name as added_by_full_name, b.name as branch_name,
        SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN e.quantity ELSE 0 END) as total_quantity,
        MIN(e.reorder_level) as reorder_level,
        MIN(e.unit_cost) as unit_cost,
        MIN(e.selling_price) as selling_price,
        MIN(e.expiry_date) as expiry_date,
        GROUP_CONCAT(e.id) as batch_ids,
        GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(e.quantity SEPARATOR '|') as batch_quantities,
        GROUP_CONCAT(e.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(e.status SEPARATOR '|') as batch_statuses,
        MIN(DATEDIFF(CASE WHEN e.expiry_date = '0000-00-00' THEN NULL ELSE e.expiry_date END, CURDATE())) as days_remaining,
        CASE 
            WHEN SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN 1 ELSE 0 END) > 0 
            THEN 'active' ELSE 'inactive' 
        END as computed_status
    FROM medical_equipment e
    LEFT JOIN users u ON e.added_by = u.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE 1=1 $branch_cond_eq
    GROUP BY e.equipment_name, e.category, e.unit, e.branch_id
    ORDER BY e.equipment_name ASC
";

$stmt = $db->prepare($equip_query);
if (!$stmt) {
    $equipment = [];
} else {
    $stmt->execute($branch_params);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// STATS - MEDICINES
// ================================================================
$med_total = 0; $med_in_stock = 0; $med_out_stock = 0; $med_low_stock = 0;
$med_value = 0; $med_total_qty = 0;

foreach ($medicines as $m) {
    $qty = (int)($m['total_quantity'] ?? 0);
    $med_total++;
    $med_total_qty += $qty;
    $med_value += $qty * (float)($m['selling_price'] ?? 0);
    if ($qty > 0) $med_in_stock++;
    else $med_out_stock++;
    if ($qty > 0 && $qty <= (int)($m['reorder_level'] ?? 0)) $med_low_stock++;
}

// ================================================================
// STATS - EQUIPMENT
// ================================================================
$eq_total = 0; $eq_in_stock = 0; $eq_out_stock = 0; $eq_low_stock = 0;
$eq_value = 0; $eq_total_qty = 0;

foreach ($equipment as $e) {
    $qty = (int)($e['total_quantity'] ?? 0);
    $eq_total++;
    $eq_total_qty += $qty;
    $eq_value += $qty * (float)($e['selling_price'] ?? 0);
    if ($qty > 0) $eq_in_stock++;
    else $eq_out_stock++;
    if ($qty > 0 && $qty <= (int)($e['reorder_level'] ?? 0)) $eq_low_stock++;
}

$total_inventory_value = $med_value + $eq_value;

// ================================================================
// SALES STATISTICS
// ================================================================
$med_sold_qty = 0; $med_sold_revenue = 0;
try {
    $sql = "SELECT COALESCE(SUM(bi.quantity), 0) as qty, COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as revenue
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.item_type = 'medication' AND bi.status = 'paid'
            AND b.patient_id IS NOT NULL
            $branch_cond_bi $date_cond_sales";
    $params = array_merge($branch_params, $date_params_sales);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $med_sold_qty = (int)($r['qty'] ?? 0);
    $med_sold_revenue = (float)($r['revenue'] ?? 0);
} catch (Exception $e) {}

$otc_sold_qty = 0; $otc_sold_revenue = 0;
try {
    $sql = "SELECT COALESCE(SUM(osi.quantity), 0) as qty, COALESCE(SUM(osi.total_price), 0) as revenue
            FROM otc_sale_items osi
            INNER JOIN otc_sales os ON osi.sale_id = os.id
            WHERE os.payment_status = 'paid'";
    if ($filter_by_branch) $sql .= " AND os.branch_id = ?";
    if ($quick_filter === 'today') $sql .= " AND DATE(os.created_at) = CURDATE()";
    elseif ($quick_filter === '1w') $sql .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    elseif ($quick_filter === '1m') $sql .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    elseif ($quick_filter === '3m') $sql .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
    elseif ($quick_filter === '1y') $sql .= " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    elseif ($quick_filter === 'custom') $sql .= " AND DATE(os.created_at) BETWEEN ? AND ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params, $date_params_sales));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_sold_qty = (int)($r['qty'] ?? 0);
    $otc_sold_revenue = (float)($r['revenue'] ?? 0);
} catch (Exception $e) {}

$total_sold_qty = $med_sold_qty + $otc_sold_qty;
$total_sold_revenue = $med_sold_revenue + $otc_sold_revenue;

// ================================================================
// EXPIRY STATS
// ================================================================
$med_expiring = 0; $med_expired = 0; $eq_expiring = 0; $eq_expired = 0;

foreach ($medicines as $m) {
    $exp = $m['expiry_date'] ?? '';
    if ($exp && $exp !== '0000-00-00') {
        $days = (int)($m['days_remaining'] ?? 0);
        if ($days < 0) $med_expired++;
        elseif ($days <= 30) $med_expiring++;
    }
}
foreach ($equipment as $e) {
    $exp = $e['expiry_date'] ?? '';
    if ($exp && $exp !== '0000-00-00') {
        $days = (int)($e['days_remaining'] ?? 0);
        if ($days < 0) $eq_expired++;
        elseif ($days <= 30) $eq_expiring++;
    }
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ PDF DATA AS JSON (BETTER APPROACH)
// ================================================================
$pdf_data = [
    'currency' => $currency,
    'branchName' => $display_branch_name,
    'filterLabel' => $date_label,
    'adminName' => $user_full_name,
    'logoUrl' => $logo_path,
    'medTotal' => (int)$med_total,
    'medInStock' => (int)$med_in_stock,
    'medOutStock' => (int)$med_out_stock,
    'medLowStock' => (int)$med_low_stock,
    'medTotalQty' => (int)$med_total_qty,
    'medValue' => (float)$med_value,
    'medExpiring' => (int)$med_expiring,
    'medExpired' => (int)$med_expired,
    'eqTotal' => (int)$eq_total,
    'eqInStock' => (int)$eq_in_stock,
    'eqOutStock' => (int)$eq_out_stock,
    'eqLowStock' => (int)$eq_low_stock,
    'eqTotalQty' => (int)$eq_total_qty,
    'eqValue' => (float)$eq_value,
    'eqExpiring' => (int)$eq_expiring,
    'eqExpired' => (int)$eq_expired,
    'totalValue' => (float)$total_inventory_value,
    'soldQty' => (int)$total_sold_qty,
    'soldRevenue' => (float)$total_sold_revenue,
    'medicines' => array_slice($medicines, 0, 100),
    'equipment' => array_slice($equipment, 0, 100),
];

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - Braick Audit</title>
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
    --highlight-bg: #FEF08A;
    --highlight-text: #713F12;
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
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
    --highlight-bg: #78350F;
    --highlight-text: #FEF3C7;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .stat-value, .money-cell, .card-value, .bill-number, .font-mono, .mono, .badge-number {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* PAGE HEADER */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px; padding: 20px 24px; margin-bottom: 20px;
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%; pointer-events: none;
}
.page-header .page-title {
    color: white; font-size: 1.4rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    position: relative; z-index: 1; letter-spacing: -0.02em;
}
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.78rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 4px; position: relative; z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 3px 10px; border-radius: 16px; font-size: 0.65rem;
    font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
    backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);
}
.branch-tag.filter-tag {
    background: linear-gradient(135deg, #10B981, #059669);
    font-weight: 700;
}
.btn-header {
    background: rgba(255,255,255,0.15); color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px; border-radius: 9px;
    font-weight: 600; font-size: 0.75rem;
    transition: all 0.3s ease; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer;
}
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }
.btn-pdf-export {
    background: linear-gradient(135deg, #DC2626, #B91C1C) !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}
.btn-pdf-export:hover {
    background: linear-gradient(135deg, #EF4444, #DC2626) !important;
    box-shadow: 0 8px 24px rgba(220, 38, 38, 0.6);
}

/* FILTER CARD */
.filter-card {
    background: var(--bg-card); border-radius: 14px; padding: 16px 18px;
    border: 2px solid var(--border-color); margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
}
.filter-section { margin-bottom: 14px; }
.filter-section:last-child { margin-bottom: 0; }
.filter-section-title {
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--text-secondary);
    margin-bottom: 8px; display: flex; align-items: center; gap: 5px;
}
.quick-filters { display: flex; gap: 6px; flex-wrap: wrap; }
.quick-btn {
    padding: 6px 12px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-secondary); font-weight: 700; font-size: 0.7rem;
    cursor: pointer; transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
}
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.quick-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border-color: transparent;
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.3);
}
.filter-form {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px; align-items: end; margin-top: 10px;
    padding-top: 12px; border-top: 2px dashed var(--border-color);
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label {
    font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-secondary);
    display: flex; align-items: center; gap: 4px;
}
.filter-group input, .filter-group select {
    padding: 8px 12px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-primary); font-size: 0.78rem; font-weight: 600;
    outline: none; transition: all 0.3s ease; height: 36px;
}
.filter-group input:focus, .filter-group select:focus {
    border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}
.filter-btn-primary {
    padding: 8px 16px; border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border: none; font-weight: 700; font-size: 0.75rem;
    cursor: pointer; transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 6px;
    justify-content: center; height: 36px;
}
.filter-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35); }
.filter-btn-secondary {
    padding: 8px 16px; border-radius: 8px;
    background: transparent; color: var(--text-secondary);
    border: 2px solid var(--border-color);
    font-weight: 700; font-size: 0.75rem; cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 6px;
    justify-content: center; height: 36px; text-decoration: none;
}
.filter-btn-secondary:hover { border-color: var(--danger); color: var(--danger); }

/* STATS GRID */
.stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 20px;
}
.stat-card {
    background: var(--bg-card); border-radius: 14px; padding: 14px 16px;
    border: 2px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
    min-height: 130px; display: flex; flex-direction: column;
    justify-content: space-between; cursor: pointer;
}
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 3px; transition: height 0.3s ease;
}
.stat-card:hover::before { height: 5px; }
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.stat-card .stat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: white; margin-bottom: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}
.stat-card:hover .stat-icon { transform: scale(1.1) rotate(-5deg); }
.stat-card .stat-label {
    font-size: 0.62rem; color: var(--text-secondary);
    font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; margin-bottom: 4px;
}
.stat-card .stat-value {
    font-size: 1.25rem; font-weight: 900; color: var(--text-primary);
    line-height: 1.1; letter-spacing: -0.03em;
    display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap;
}
.stat-card .stat-value .currency-symbol {
    font-size: 0.72rem; font-weight: 700;
    color: var(--text-secondary); font-family: var(--font-primary);
}
.stat-card .stat-sub {
    font-size: 0.6rem; color: var(--text-secondary);
    margin-top: 8px; padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    display: flex; align-items: center; gap: 4px; font-weight: 600;
}
.stat-card .stat-sub i { font-size: 0.55rem; }

.stat-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.blue:hover { border-color: #0B5ED7; }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-value .money-number { color: var(--primary); }

.stat-card.green::before { background: linear-gradient(90deg, #059669, #34D399, #059669); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.green:hover { border-color: #059669; }
.stat-card.green .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.green .stat-value .money-number { color: var(--success); }

.stat-card.orange::before { background: linear-gradient(90deg, #D97706, #FBBF24, #D97706); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.orange:hover { border-color: #D97706; }
.stat-card.orange .stat-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-card.orange .stat-value .money-number { color: var(--warning); }

.stat-card.red::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.red:hover { border-color: #DC2626; }
.stat-card.red .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.red .stat-value .money-number { color: var(--danger); }

.stat-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA, #7C3AED); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.purple:hover { border-color: #7C3AED; }
.stat-card.purple .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-value .money-number { color: var(--purple); }

.stat-card.cyan::before { background: linear-gradient(90deg, #0891B2, #06B6D4, #0891B2); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.cyan:hover { border-color: #0891B2; }
.stat-card.cyan .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.cyan .stat-value .money-number { color: var(--cyan); }

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* TABS */
.tabs-container {
    display: flex; gap: 4px; background: var(--bg-card);
    border-radius: 12px; padding: 4px;
    border: 2px solid var(--border-color); margin-bottom: 20px;
}
.tab-btn {
    padding: 10px 24px; border-radius: 10px;
    font-weight: 700; font-size: 0.85rem;
    border: none; cursor: pointer;
    background: transparent; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 8px;
    flex: 1; justify-content: center; transition: all 0.3s ease;
}
.tab-btn:hover { background: var(--primary-bg); color: var(--primary); }
.tab-btn.active { background: var(--primary); color: white; }
.tab-btn .badge {
    background: rgba(255,255,255,0.2); color: white;
    padding: 1px 8px; border-radius: 10px; font-size: 0.65rem;
    font-family: var(--font-mono); font-weight: 800;
}
.tab-btn:not(.active) .badge { background: var(--border-color); color: var(--text-secondary); }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* TABLE CARD */
.table-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); overflow: hidden;
    box-shadow: var(--shadow-sm); margin-bottom: 18px;
}
.table-card .table-header {
    padding: 12px 18px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 8px;
}
.table-card .table-header .title {
    color: white; font-size: 0.85rem; font-weight: 800;
    display: flex; align-items: center; gap: 8px;
}
.table-card .table-header .title i { color: #93C5FD; font-size: 0.95rem; }
.table-card .table-header .count {
    color: rgba(255,255,255,0.9); font-size: 0.68rem; font-weight: 700;
    background: rgba(255,255,255,0.15); padding: 3px 10px;
    border-radius: 10px; backdrop-filter: blur(4px);
}

.table-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; flex-wrap: wrap; padding: 10px 16px;
    background: var(--primary-bg); border-bottom: 2px solid var(--border-color);
}
[data-theme="dark"] .table-toolbar { background: rgba(10, 46, 92, 0.3); }
.table-toolbar-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px; }
.table-toolbar-right { display: flex; align-items: center; gap: 6px; }

.search-box { position: relative; flex: 1; max-width: 380px; }
.search-box input {
    width: 100%; padding: 8px 32px 8px 32px;
    border-radius: 8px; border: 2px solid var(--border-color);
    background: var(--bg-card); color: var(--text-primary);
    font-size: 0.78rem; font-weight: 500; outline: none;
    transition: all 0.3s ease; height: 36px;
}
.search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}
.search-box .search-icon {
    position: absolute; left: 10px; top: 50%;
    transform: translateY(-50%); color: var(--text-secondary);
    font-size: 0.75rem; pointer-events: none;
}
.search-box .search-clear {
    position: absolute; right: 6px; top: 50%;
    transform: translateY(-50%); background: transparent;
    border: none; color: var(--text-secondary); cursor: pointer;
    font-size: 0.75rem; padding: 4px 6px; border-radius: 5px;
    display: none;
}
.search-box .search-clear:hover { background: var(--border-color); color: var(--danger); }
.search-box.has-value .search-clear { display: block; }
.search-count {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 8px; font-size: 0.65rem;
    font-weight: 700; background: var(--primary); color: white;
    white-space: nowrap; height: 28px;
}
.search-count.has-results { background: var(--success); }
.search-count.no-results { background: var(--danger); }
.scroll-btn {
    width: 32px; height: 32px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-primary); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    transition: all 0.25s ease; flex-shrink: 0;
}
.scroll-btn:hover {
    background: var(--primary); color: white;
    border-color: var(--primary); transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.3);
}

.table-scroll-wrapper { overflow-x: auto; scroll-behavior: smooth; }
.table-scroll-wrapper::-webkit-scrollbar { height: 6px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

mark.search-highlight {
    background: var(--highlight-bg); color: var(--highlight-text);
    padding: 1px 3px; border-radius: 4px; font-weight: 800;
}

/* DATA TABLE */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th {
    text-align: left; padding: 9px 12px;
    font-weight: 800; font-size: 0.6rem; text-transform: uppercase;
    letter-spacing: 0.06em; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 9px 12px; border-bottom: 1px solid var(--border-color);
    color: var(--text-primary); vertical-align: middle; font-weight: 500;
}
.data-table tbody tr { transition: background 0.2s ease; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .data-table tbody tr:hover td { background: #0A2E5C; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.hidden-row { display: none !important; }

.money-cell {
    font-family: var(--font-mono); font-weight: 800;
    font-size: 0.8rem; color: var(--success);
    text-align: right; letter-spacing: -0.02em;
}
.money-cell .currency-prefix {
    font-size: 0.65rem; color: var(--text-secondary);
    margin-right: 2px; font-family: var(--font-primary); font-weight: 600;
}

/* BADGES */
.stock-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 8px;
    font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
}
.stock-badge.ok { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.stock-badge.low { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.stock-badge.out { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.status-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 8px;
    font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
}
.status-badge.active { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.inactive { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.expiry-badge {
    padding: 3px 8px; border-radius: 8px; font-size: 0.6rem;
    font-weight: 700; display: inline-flex; align-items: center; gap: 3px;
}
.expiry-badge.valid { background: var(--success-bg); color: var(--success); }
.expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); }
.expiry-badge.expired { background: var(--danger-bg); color: var(--danger); }
.expiry-badge.no-expiry { background: var(--border-color); color: var(--text-secondary); }

.batch-number {
    font-family: var(--font-mono); font-size: 0.6rem;
    font-weight: 700; padding: 2px 6px; border-radius: 4px;
    background: var(--primary-bg); color: var(--primary);
}

.added-by-tag {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 8px; border-radius: 10px;
    font-size: 0.6rem; font-weight: 700;
    background: var(--purple-bg); color: var(--purple);
}

.rank-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%;
    font-weight: 800; font-size: 0.65rem;
    background: var(--primary-bg); color: var(--primary);
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
    .stat-card .stat-value { font-size: 1.1rem; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th, .data-table tbody td { padding: 7px 8px; }
    .tabs-container { flex-direction: column; }
    .quick-btn { font-size: 0.65rem; padding: 5px 10px; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
}
    </style>
</head>
<body>

<!-- ✅ PDF DATA IN JSON (SAFE) -->
<script id="pdfDataScript" type="application/json">
<?= json_encode($pdf_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i>
                Inventory Report
                <span class="branch-tag"><i class="fas fa-user-shield"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-pills"></i> <?= number_format($med_total) ?> Medicines
                </span>
                <span class="branch-tag">
                    <i class="fas fa-tools"></i> <?= number_format($eq_total) ?> Equipment
                </span>
                <span class="branch-tag filter-tag">
                    <i class="fas fa-filter"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openPDFWindow()" class="btn-header btn-pdf-export" title="Export to PDF">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="/dispensary_system/frontend/pages/admin/audit/dashboard.php?branch=<?= $selected_branch_id ?>" 
               class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-bolt"></i> Quick Filters
            </div>
            <div class="quick-filters">
                <a href="?branch=<?= $selected_branch_id ?>&quick=today" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1w" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i> 1W
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1m" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 1M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=3m" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 3M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1y" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
                    <i class="fas fa-calendar"></i> 1Y
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=all" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-infinity"></i> All
                </a>
            </div>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <div class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Date Range</label>
                    <select name="quick" onchange="this.form.submit()">
                        <option value="today" <?= $quick_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="1w" <?= $quick_filter === '1w' ? 'selected' : '' ?>>Last 1 Week</option>
                        <option value="1m" <?= $quick_filter === '1m' ? 'selected' : '' ?>>Last 1 Month</option>
                        <option value="3m" <?= $quick_filter === '3m' ? 'selected' : '' ?>>Last 3 Months</option>
                        <option value="1y" <?= $quick_filter === '1y' ? 'selected' : '' ?>>Last 1 Year</option>
                        <option value="all" <?= $quick_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $quick_filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <?php if ($quick_filter === 'custom'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="?branch=<?= $selected_branch_id ?>" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- SUMMARY STATS GRID -->
    <div class="stats-grid">
        
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-label"><i class="fas fa-boxes"></i> Total Medicines</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($med_total) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-cubes"></i> <?= number_format($med_total_qty) ?> units
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label"><i class="fas fa-warehouse"></i> In Stock</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($med_in_stock) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-coins"></i> <?= $currency ?> <?= formatMoneyShort($med_value) ?>
            </div>
        </div>
        
        <div class="stat-card cyan">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-label"><i class="fas fa-cash-register"></i> Sold (Qty)</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_sold_qty) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-money-bill"></i> <?= $currency ?> <?= formatMoneyShort($total_sold_revenue) ?>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Out of Stock</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($med_out_stock) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-pills"></i> <?= number_format($med_low_stock) ?> low stock
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-tools"></i></div>
            <div class="stat-label"><i class="fas fa-cog"></i> Total Equipment</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($eq_total) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-cubes"></i> <?= number_format($eq_total_qty) ?> units
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
            <div class="stat-label"><i class="fas fa-check"></i> Equip In Stock</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($eq_in_stock) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-coins"></i> <?= $currency ?> <?= formatMoneyShort($eq_value) ?>
            </div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-label"><i class="fas fa-times"></i> Equip Out Stock</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($eq_out_stock) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-tools"></i> <?= number_format($eq_low_stock) ?> low stock
            </div>
        </div>
        
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label"><i class="fas fa-dollar-sign"></i> Total Value</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= formatMoneyShort($total_inventory_value) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-chart-line"></i> Inventory worth
            </div>
        </div>
        
    </div>

    <!-- EXPIRY ALERTS -->
    <div class="stats-grid">
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label"><i class="fas fa-hourglass-half"></i> Meds Expiring (30d)</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($med_expiring) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-calendar-times"></i> Within 30 days
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-skull-crossbones"></i></div>
            <div class="stat-label"><i class="fas fa-ban"></i> Meds Expired</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($med_expired) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-exclamation-triangle"></i> Needs disposal
            </div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-label"><i class="fas fa-hourglass-half"></i> Equip Expiring</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($eq_expiring) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-calendar-times"></i> Within 30 days
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-skull-crossbones"></i></div>
            <div class="stat-label"><i class="fas fa-ban"></i> Equip Expired</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($eq_expired) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-exclamation-triangle"></i> Needs attention
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <button class="tab-btn active" onclick="switchTab('medicines')" id="tabBtnMed">
            <i class="fas fa-pills"></i> Medicines Report <span class="badge"><?= $med_total ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('equipment')" id="tabBtnEq">
            <i class="fas fa-tools"></i> Equipment Report <span class="badge"><?= $eq_total ?></span>
        </button>
    </div>

    <!-- MEDICINES TAB -->
    <div id="tab-medicines" class="tab-content active">
        <div class="table-card">
            <div class="table-header">
                <span class="title"><i class="fas fa-pills"></i> Medicines Inventory Report</span>
                <span class="count"><?= count($medicines) ?> items</span>
            </div>
            
            <div class="table-toolbar">
                <div class="table-toolbar-left">
                    <div class="search-box" id="medSearchBox">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="medSearch" 
                               placeholder="Search medicine, category, batch, added by..."
                               oninput="filterTable('medTable', this.value, 'medCount')"
                               autocomplete="off">
                        <button type="button" class="search-clear" onclick="clearSearch('medTable', 'medSearch', 'medCount')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <span class="search-count" id="medCount">
                        <i class="fas fa-list"></i>
                        <span class="count-text"><?= count($medicines) ?> records</span>
                    </span>
                </div>
                <div class="table-toolbar-right">
                    <button type="button" class="scroll-btn" onclick="scrollTable('medWrapper', 'left')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn" onclick="scrollTable('medWrapper', 'right')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="table-scroll-wrapper" id="medWrapper">
                <table class="data-table" id="medTable" style="min-width:1300px;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:center;">Reorder</th>
                            <th style="text-align:center;">Stock</th>
                            <th style="text-align:right;">Price</th>
                            <th>Expiry</th>
                            <th style="text-align:center;">Days</th>
                            <th>Batch</th>
                            <th style="text-align:center;">Status</th>
                            <th>Added By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($medicines) > 0): ?>
                            <?php $row_num = 1; foreach ($medicines as $m): 
                                $qty = (int)($m['total_quantity'] ?? 0);
                                $reorder = (int)($m['reorder_level'] ?? 0);
                                $stock_class = 'ok'; $stock_label = 'In Stock';
                                if ($qty <= 0) { $stock_class = 'out'; $stock_label = 'Out'; }
                                elseif ($qty <= $reorder) { $stock_class = 'low'; $stock_label = 'Low'; }
                                
                                $exp = $m['expiry_date'] ?? '';
                                $exp_class = 'no-expiry'; $exp_display = 'No Expiry';
                                $days_display = '∞'; $days_class = 'forever';
                                
                                if ($exp && $exp !== '0000-00-00') {
                                    $days = (int)($m['days_remaining'] ?? 0);
                                    $exp_display = date('d/m/Y', strtotime($exp));
                                    if ($days < 0) { $exp_class = 'expired'; $days_display = 'EXP'; $days_class = 'danger'; }
                                    elseif ($days <= 30) { $exp_class = 'expiring'; $days_display = $days . 'd'; $days_class = 'warning'; }
                                    else { $exp_class = 'valid'; $days_display = $days . 'd'; $days_class = 'good'; }
                                }
                                
                                $batches = $m['batch_numbers'] ?? '';
                                $first_batch = $batches ? explode('|', $batches)[0] : '-';
                                $batch_count = $batches ? count(explode('|', $batches)) : 0;
                                
                                $status = $m['computed_status'] ?? 'active';
                                $added_by = $m['added_by_full_name'] ?? 'System';
                            ?>
                                <tr>
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                                    <td class="searchable-cell">
                                        <strong><?= htmlspecialchars($m['medication_name'] ?? 'N/A') ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:6px;margin-left:4px;font-family:var(--font-mono);font-weight:700;"><?= $batch_count ?> batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="searchable-cell"><?= htmlspecialchars($m['category'] ?? 'N/A') ?></td>
                                    <td class="searchable-cell">
                                        <span style="font-size:0.7rem;color:var(--primary);font-weight:600;">
                                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($m['branch_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--primary);" class="searchable-cell">
                                        <?= number_format($qty) ?>
                                    </td>
                                    <td style="text-align:center;color:var(--text-secondary);"><?= number_format($reorder) ?></td>
                                    <td style="text-align:center;">
                                        <span class="stock-badge <?= $stock_class ?>">
                                            <i class="fas <?= $stock_class === 'ok' ? 'fa-check-circle' : ($stock_class === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($m['selling_price'] ?? 0), 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="expiry-badge <?= $exp_class ?>"><?= $exp_display ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="expiry-badge <?= $days_class === 'danger' ? 'expired' : ($days_class === 'warning' ? 'expiring' : 'valid') ?>">
                                            <?= $days_display ?>
                                        </span>
                                    </td>
                                    <td class="searchable-cell">
                                        <?php if ($first_batch !== '-'): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.65rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $status ?>">
                                            <?= strtoupper($status) ?>
                                        </span>
                                    </td>
                                    <td class="searchable-cell">
                                        <span class="added-by-tag">
                                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" style="text-align:center;padding:50px 20px;color:var(--text-secondary);">
                                    <i class="fas fa-pills" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
                                    <p style="font-weight:600;">No medicines found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- EQUIPMENT TAB -->
    <div id="tab-equipment" class="tab-content">
        <div class="table-card">
            <div class="table-header">
                <span class="title"><i class="fas fa-tools"></i> Equipment Inventory Report</span>
                <span class="count"><?= count($equipment) ?> items</span>
            </div>
            
            <div class="table-toolbar">
                <div class="table-toolbar-left">
                    <div class="search-box" id="eqSearchBox">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="eqSearch" 
                               placeholder="Search equipment, category, batch, added by..."
                               oninput="filterTable('eqTable', this.value, 'eqCount')"
                               autocomplete="off">
                        <button type="button" class="search-clear" onclick="clearSearch('eqTable', 'eqSearch', 'eqCount')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <span class="search-count" id="eqCount">
                        <i class="fas fa-list"></i>
                        <span class="count-text"><?= count($equipment) ?> records</span>
                    </span>
                </div>
                <div class="table-toolbar-right">
                    <button type="button" class="scroll-btn" onclick="scrollTable('eqWrapper', 'left')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn" onclick="scrollTable('eqWrapper', 'right')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="table-scroll-wrapper" id="eqWrapper">
                <table class="data-table" id="eqTable" style="min-width:1300px;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Equipment Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:center;">Reorder</th>
                            <th style="text-align:center;">Stock</th>
                            <th style="text-align:right;">Price</th>
                            <th>Expiry</th>
                            <th style="text-align:center;">Days</th>
                            <th>Batch</th>
                            <th style="text-align:center;">Status</th>
                            <th>Added By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($equipment) > 0): ?>
                            <?php $row_num = 1; foreach ($equipment as $e): 
                                $qty = (int)($e['total_quantity'] ?? 0);
                                $reorder = (int)($e['reorder_level'] ?? 0);
                                $stock_class = 'ok'; $stock_label = 'In Stock';
                                if ($qty <= 0) { $stock_class = 'out'; $stock_label = 'Out'; }
                                elseif ($qty <= $reorder) { $stock_class = 'low'; $stock_label = 'Low'; }
                                
                                $exp = $e['expiry_date'] ?? '';
                                $exp_class = 'no-expiry'; $exp_display = 'No Expiry';
                                $days_display = '∞'; $days_class = 'forever';
                                
                                if ($exp && $exp !== '0000-00-00') {
                                    $days = (int)($e['days_remaining'] ?? 0);
                                    $exp_display = date('d/m/Y', strtotime($exp));
                                    if ($days < 0) { $exp_class = 'expired'; $days_display = 'EXP'; $days_class = 'danger'; }
                                    elseif ($days <= 30) { $exp_class = 'expiring'; $days_display = $days . 'd'; $days_class = 'warning'; }
                                    else { $exp_class = 'valid'; $days_display = $days . 'd'; $days_class = 'good'; }
                                }
                                
                                $batches = $e['batch_numbers'] ?? '';
                                $first_batch = $batches ? explode('|', $batches)[0] : '-';
                                $batch_count = $batches ? count(explode('|', $batches)) : 0;
                                
                                $status = $e['computed_status'] ?? 'active';
                                $added_by = $e['added_by_full_name'] ?? 'System';
                            ?>
                                <tr>
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                                    <td class="searchable-cell">
                                        <strong><?= htmlspecialchars($e['equipment_name'] ?? 'N/A') ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;background:var(--purple-bg);color:var(--purple);padding:1px 6px;border-radius:6px;margin-left:4px;font-family:var(--font-mono);font-weight:700;"><?= $batch_count ?> batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="searchable-cell"><?= htmlspecialchars($e['category'] ?? 'N/A') ?></td>
                                    <td class="searchable-cell">
                                        <span style="font-size:0.7rem;color:var(--primary);font-weight:600;">
                                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($e['branch_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--purple);" class="searchable-cell">
                                        <?= number_format($qty) ?>
                                    </td>
                                    <td style="text-align:center;color:var(--text-secondary);"><?= number_format($reorder) ?></td>
                                    <td style="text-align:center;">
                                        <span class="stock-badge <?= $stock_class ?>">
                                            <i class="fas <?= $stock_class === 'ok' ? 'fa-check-circle' : ($stock_class === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($e['selling_price'] ?? 0), 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="expiry-badge <?= $exp_class ?>"><?= $exp_display ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="expiry-badge <?= $days_class === 'danger' ? 'expired' : ($days_class === 'warning' ? 'expiring' : 'valid') ?>">
                                            <?= $days_display ?>
                                        </span>
                                    </td>
                                    <td class="searchable-cell">
                                        <?php if ($first_batch !== '-'): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.65rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $status ?>">
                                            <?= strtoupper($status) ?>
                                        </span>
                                    </td>
                                    <td class="searchable-cell">
                                        <span class="added-by-tag">
                                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" style="text-align:center;padding:50px 20px;color:var(--text-secondary);">
                                    <i class="fas fa-tools" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
                                    <p style="font-weight:600;">No equipment found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SUMMARY BY CATEGORY (MEDICINES) -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-chart-pie"></i> Medicines Summary by Category</span>
            <span class="count"><?= $med_total ?> items</span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="text-align:center;">Items</th>
                        <th style="text-align:center;">Total Qty</th>
                        <th style="text-align:center;">In Stock</th>
                        <th style="text-align:center;">Out of Stock</th>
                        <th style="text-align:right;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cat_summary = [];
                    foreach ($medicines as $m) {
                        $cat = $m['category'] ?? 'Other';
                        if (!isset($cat_summary[$cat])) {
                            $cat_summary[$cat] = ['items' => 0, 'qty' => 0, 'in_stock' => 0, 'out_stock' => 0, 'value' => 0];
                        }
                        $qty = (int)($m['total_quantity'] ?? 0);
                        $cat_summary[$cat]['items']++;
                        $cat_summary[$cat]['qty'] += $qty;
                        if ($qty > 0) $cat_summary[$cat]['in_stock']++;
                        else $cat_summary[$cat]['out_stock']++;
                        $cat_summary[$cat]['value'] += $qty * (float)($m['selling_price'] ?? 0);
                    }
                    arsort($cat_summary);
                    $rank = 1;
                    foreach ($cat_summary as $cat => $data):
                        $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                    ?>
                        <tr>
                            <td>
                                <span class="rank-badge <?= $rank_class ?>"><?= $rank++ ?></span>
                                <strong style="margin-left:6px;"><?= htmlspecialchars($cat) ?></strong>
                            </td>
                            <td style="text-align:center;font-weight:700;"><?= number_format($data['items']) ?></td>
                            <td style="text-align:center;font-weight:700;color:var(--primary);font-family:var(--font-mono);"><?= number_format($data['qty']) ?></td>
                            <td style="text-align:center;color:var(--success);font-weight:700;"><?= number_format($data['in_stock']) ?></td>
                            <td style="text-align:center;color:var(--danger);font-weight:700;"><?= number_format($data['out_stock']) ?></td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($data['value'], 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SUMMARY BY CATEGORY (EQUIPMENT) -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-chart-pie"></i> Equipment Summary by Category</span>
            <span class="count"><?= $eq_total ?> items</span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="text-align:center;">Items</th>
                        <th style="text-align:center;">Total Qty</th>
                        <th style="text-align:center;">In Stock</th>
                        <th style="text-align:center;">Out of Stock</th>
                        <th style="text-align:right;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $eq_cat_summary = [];
                    foreach ($equipment as $e) {
                        $cat = $e['category'] ?? 'Other';
                        if (!isset($eq_cat_summary[$cat])) {
                            $eq_cat_summary[$cat] = ['items' => 0, 'qty' => 0, 'in_stock' => 0, 'out_stock' => 0, 'value' => 0];
                        }
                        $qty = (int)($e['total_quantity'] ?? 0);
                        $eq_cat_summary[$cat]['items']++;
                        $eq_cat_summary[$cat]['qty'] += $qty;
                        if ($qty > 0) $eq_cat_summary[$cat]['in_stock']++;
                        else $eq_cat_summary[$cat]['out_stock']++;
                        $eq_cat_summary[$cat]['value'] += $qty * (float)($e['selling_price'] ?? 0);
                    }
                    arsort($eq_cat_summary);
                    $rank = 1;
                    foreach ($eq_cat_summary as $cat => $data):
                        $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                    ?>
                        <tr>
                            <td>
                                <span class="rank-badge <?= $rank_class ?>"><?= $rank++ ?></span>
                                <strong style="margin-left:6px;"><?= htmlspecialchars($cat) ?></strong>
                            </td>
                            <td style="text-align:center;font-weight:700;"><?= number_format($data['items']) ?></td>
                            <td style="text-align:center;font-weight:700;color:var(--purple);font-family:var(--font-mono);"><?= number_format($data['qty']) ?></td>
                            <td style="text-align:center;color:var(--success);font-weight:700;"><?= number_format($data['in_stock']) ?></td>
                            <td style="text-align:center;color:var(--danger);font-weight:700;"><?= number_format($data['out_stock']) ?></td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($data['value'], 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
// ================================================================
// ✅ PDF DATA - READ FROM JSON SCRIPT TAG (SAFE!)
// ================================================================
var PDF_DATA = {};
try {
    var pdfDataScript = document.getElementById('pdfDataScript');
    if (pdfDataScript) {
        PDF_DATA = JSON.parse(pdfDataScript.textContent);
    }
} catch (e) {
    console.error('Failed to parse PDF data:', e);
    PDF_DATA = {};
}

// ================================================================
// TAB SWITCHING
// ================================================================
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    
    var tabEl = document.getElementById('tab-' + tab);
    if (tabEl) tabEl.classList.add('active');
    
    if (tab === 'medicines') {
        var btn = document.getElementById('tabBtnMed');
        if (btn) btn.classList.add('active');
    } else {
        var btn = document.getElementById('tabBtnEq');
        if (btn) btn.classList.add('active');
    }
}

// ================================================================
// SEARCH FILTER
// ================================================================
function filterTable(tableId, searchTerm, countId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    var term = searchTerm.trim().toLowerCase();
    var visibleCount = 0;
    
    var searchBox = document.getElementById(tableId.replace('Table', 'SearchBox'));
    if (searchBox) searchBox.classList.toggle('has-value', term.length > 0);
    
    rows.forEach(function(row) {
        if (row.querySelector('td[colspan]')) return;
        var rowText = '';
        var cells = row.querySelectorAll('.searchable-cell');
        if (cells.length > 0) {
            cells.forEach(function(cell) { rowText += ' ' + cell.textContent; });
        } else {
            rowText = row.textContent;
        }
        rowText = rowText.toLowerCase();
        resetHighlights(row);
        
        if (term === '' || rowText.indexOf(term) !== -1) {
            row.classList.remove('hidden-row');
            visibleCount++;
            if (term !== '') highlightMatches(row, term);
        } else {
            row.classList.add('hidden-row');
        }
    });
    
    var countEl = document.getElementById(countId);
    if (countEl) {
        var countText = countEl.querySelector('.count-text');
        var icon = countEl.querySelector('i');
        if (term === '') {
            countEl.className = 'search-count';
            if (icon) icon.className = 'fas fa-list';
            if (countText) countText.textContent = visibleCount + ' records';
        } else if (visibleCount > 0) {
            countEl.className = 'search-count has-results';
            if (icon) icon.className = 'fas fa-check-circle';
            if (countText) countText.textContent = visibleCount + ' found';
        } else {
            countEl.className = 'search-count no-results';
            if (icon) icon.className = 'fas fa-times-circle';
            if (countText) countText.textContent = 'No results';
        }
    }
}

function highlightMatches(row, term) {
    var cells = row.querySelectorAll('.searchable-cell');
    cells.forEach(function(cell) {
        var html = cell.innerHTML;
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        var walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while (node = walker.nextNode()) textNodes.push(node);
        
        textNodes.forEach(function(textNode) {
            var text = textNode.nodeValue;
            var lowerText = text.toLowerCase();
            var lowerTerm = term.toLowerCase();
            if (lowerText.indexOf(lowerTerm) === -1) return;
            var fragments = [];
            var lastIndex = 0;
            var index;
            while ((index = lowerText.indexOf(lowerTerm, lastIndex)) !== -1) {
                if (index > lastIndex) fragments.push(document.createTextNode(text.substring(lastIndex, index)));
                var mark = document.createElement('mark');
                mark.className = 'search-highlight';
                mark.textContent = text.substring(index, index + term.length);
                fragments.push(mark);
                lastIndex = index + term.length;
            }
            if (lastIndex < text.length) fragments.push(document.createTextNode(text.substring(lastIndex)));
            if (fragments.length > 0) {
                var parent = textNode.parentNode;
                fragments.forEach(function(frag) { parent.insertBefore(frag, textNode); });
                parent.removeChild(textNode);
            }
        });
        cell.innerHTML = tempDiv.innerHTML;
    });
}

function resetHighlights(row) {
    var marks = row.querySelectorAll('mark.search-highlight');
    marks.forEach(function(mark) {
        var parent = mark.parentNode;
        parent.replaceChild(document.createTextNode(mark.textContent), mark);
        parent.normalize();
    });
}

function clearSearch(tableId, inputId, countId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.value = '';
        filterTable(tableId, '', countId);
        input.focus();
    }
}

function scrollTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    wrapper.scrollBy({ left: direction === 'left' ? -300 : 300, behavior: 'smooth' });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var activeInput = document.activeElement;
        if (activeInput && activeInput.id && activeInput.id.indexOf('Search') !== -1) {
            var tableId = activeInput.id.replace('Search', 'Table');
            var countId = activeInput.id.replace('Search', 'Count');
            clearSearch(tableId, activeInput.id, countId);
        }
    }
});

// ================================================================
// ✅ PDF EXPORT (FIXED)
// ================================================================
function openPDFWindow() {
    var data = PDF_DATA;
    
    if (!data || Object.keys(data).length === 0) {
        alert('PDF data not loaded. Please refresh the page.');
        return;
    }
    
    var now = new Date();
    var dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    
    data.generatedDate = dateStr;
    data.generatedTime = timeStr;
    
    var w = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    if (!w) {
        alert('Please allow popups to export PDF');
        return;
    }
    
    var html = '<!DOCTYPE html>' +
'<html lang="en">' +
'<head>' +
'    <meta charset="UTF-8">' +
'    <title>Inventory Report - ' + data.branchName + '</title>' +
'    <link rel="preconnect" href="https://fonts.googleapis.com">' +
'    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' +
'    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">' +
'    <style>' +
'        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Inter", sans-serif; }' +
'        body { background: #F1F5F9; padding: 30px 20px; color: #1E293B; line-height: 1.5; }' +
'        .container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(10, 46, 92, 0.15); }' +
'        .pdf-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); padding: 30px 36px; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }' +
'        .pdf-header-left { display: flex; align-items: center; gap: 18px; }' +
'        .pdf-logo { width: 64px; height: 64px; border-radius: 14px; background: white; padding: 6px; object-fit: contain; }' +
'        .pdf-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 6px; }' +
'        .pdf-subtitle { font-size: 0.82rem; color: rgba(255,255,255,0.85); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }' +
'        .pdf-subtitle .tag { background: rgba(255,255,255,0.15); padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(255,255,255,0.2); }' +
'        .pdf-header-right { text-align: right; }' +
'        .pdf-generated { font-size: 0.68rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; margin-bottom: 4px; }' +
'        .pdf-date { font-size: 0.95rem; font-weight: 700; font-family: "JetBrains Mono", monospace; }' +
'        .pdf-body { padding: 32px 36px; }' +
'        .section-title { font-size: 1rem; font-weight: 800; color: #0B5ED7; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 3px solid #E8F0FE; display: flex; align-items: center; gap: 10px; }' +
'        .section-title::before { content: ""; width: 5px; height: 20px; background: linear-gradient(180deg, #3B82F6, #0B5ED7); border-radius: 3px; }' +
'        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }' +
'        .summary-card { background: white; border-radius: 14px; padding: 16px 14px; border: 2px solid #E2E8F0; position: relative; overflow: hidden; }' +
'        .summary-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }' +
'        .summary-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }' +
'        .summary-card.green::before { background: linear-gradient(90deg, #059669, #34D399); }' +
'        .summary-card.orange::before { background: linear-gradient(90deg, #D97706, #FBBF24); }' +
'        .summary-card.red::before { background: linear-gradient(90deg, #DC2626, #F87171); }' +
'        .summary-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }' +
'        .summary-card.cyan::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }' +
'        .summary-card .card-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: white; margin-bottom: 10px; font-weight: 700; }' +
'        .summary-card.blue .card-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }' +
'        .summary-card.green .card-icon { background: linear-gradient(135deg, #059669, #34D399); }' +
'        .summary-card.orange .card-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }' +
'        .summary-card.red .card-icon { background: linear-gradient(135deg, #DC2626, #F87171); }' +
'        .summary-card.purple .card-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }' +
'        .summary-card.cyan .card-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }' +
'        .summary-card .card-label { font-size: 0.62rem; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }' +
'        .summary-card .card-value { font-size: 1.2rem; font-weight: 800; font-family: "JetBrains Mono", monospace; color: #0B5ED7; letter-spacing: -0.02em; line-height: 1.1; }' +
'        .summary-card.green .card-value { color: #059669; }' +
'        .summary-card.orange .card-value { color: #D97706; }' +
'        .summary-card.red .card-value { color: #DC2626; }' +
'        .summary-card.purple .card-value { color: #7C3AED; }' +
'        .summary-card.cyan .card-value { color: #0891B2; }' +
'        .summary-card .card-sub { font-size: 0.62rem; color: #94A3B8; font-weight: 600; margin-top: 4px; }' +
'        .pdf-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; margin-bottom: 28px; border-radius: 10px; overflow: hidden; }' +
'        .pdf-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }' +
'        .pdf-table tbody td { padding: 9px 12px; border-bottom: 1px solid #E2E8F0; color: #1E293B; }' +
'        .pdf-table tbody tr:nth-child(even) td { background: #F8FAFC; }' +
'        .pdf-table .money { font-family: "JetBrains Mono", monospace; font-weight: 800; color: #059669; text-align: right; }' +
'        .pdf-table .qty { font-family: "JetBrains Mono", monospace; font-weight: 800; color: #0B5ED7; text-align: right; }' +
'        .pdf-footer { background: #F8FAFC; padding: 18px 36px; border-top: 3px solid #E8F0FE; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.72rem; color: #64748B; }' +
'        .pdf-footer .brand { font-weight: 800; color: #0B5ED7; font-size: 0.82rem; }' +
'        .action-bar { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 9999; }' +
'        .action-btn { padding: 12px 22px; border-radius: 12px; border: none; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 20px rgba(0,0,0,0.15); }' +
'        .action-btn.print { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }' +
'        .action-btn.close { background: white; color: #DC2626; border: 2px solid #DC2626; }' +
'        @media print { body { background: white; padding: 0; } .container { box-shadow: none; } .action-bar { display: none !important; } }' +
'    </style>' +
'</head>' +
'<body>' +
'<div class="action-bar">' +
'    <button class="action-btn print" onclick="window.print()">🖨️ Print / Save PDF</button>' +
'    <button class="action-btn close" onclick="window.close()">✕ Close</button>' +
'</div>' +
'<div class="container">' +
'    <div class="pdf-header">' +
'        <div class="pdf-header-left">' +
'            <img src="' + (data.logoUrl || '') + '" class="pdf-logo" onerror="this.style.display=\'none\'">' +
'            <div>' +
'                <div class="pdf-title">Inventory Report</div>' +
'                <div class="pdf-subtitle">' +
'                    <strong>Braick Dispensary</strong>' +
'                    <span class="tag">📍 ' + (data.branchName || '') + '</span>' +
'                    <span class="tag">📅 ' + (data.filterLabel || '') + '</span>' +
'                    <span class="tag">🔒 AUDIT</span>' +
'                </div>' +
'            </div>' +
'        </div>' +
'        <div class="pdf-header-right">' +
'            <div class="pdf-generated">Generated On</div>' +
'            <div class="pdf-date">' + dateStr + ' • ' + timeStr + '</div>' +
'        </div>' +
'    </div>' +
'    <div class="pdf-body">' +
'        <div class="section-title">Medicines Summary</div>' +
'        <div class="summary-grid">' +
'            <div class="summary-card blue"><div class="card-icon">💊</div><div class="card-label">Total Medicines</div><div class="card-value">' + data.medTotal + '</div><div class="card-sub">' + data.medTotalQty + ' units</div></div>' +
'            <div class="summary-card green"><div class="card-icon">✅</div><div class="card-label">In Stock</div><div class="card-value">' + data.medInStock + '</div><div class="card-sub">' + data.currency + ' ' + Math.round(data.medValue).toLocaleString() + '</div></div>' +
'            <div class="summary-card cyan"><div class="card-icon">🛒</div><div class="card-label">Sold (Qty)</div><div class="card-value">' + data.soldQty + '</div><div class="card-sub">' + data.currency + ' ' + Math.round(data.soldRevenue).toLocaleString() + '</div></div>' +
'            <div class="summary-card red"><div class="card-icon">⚠️</div><div class="card-label">Out of Stock</div><div class="card-value">' + data.medOutStock + '</div><div class="card-sub">' + data.medLowStock + ' low stock</div></div>' +
'        </div>' +
'        <div class="section-title">Equipment Summary</div>' +
'        <div class="summary-grid">' +
'            <div class="summary-card purple"><div class="card-icon">🔧</div><div class="card-label">Total Equipment</div><div class="card-value">' + data.eqTotal + '</div><div class="card-sub">' + data.eqTotalQty + ' units</div></div>' +
'            <div class="summary-card green"><div class="card-icon">✅</div><div class="card-label">In Stock</div><div class="card-value">' + data.eqInStock + '</div><div class="card-sub">' + data.currency + ' ' + Math.round(data.eqValue).toLocaleString() + '</div></div>' +
'            <div class="summary-card orange"><div class="card-icon">⚠️</div><div class="card-label">Out of Stock</div><div class="card-value">' + data.eqOutStock + '</div><div class="card-sub">' + data.eqLowStock + ' low stock</div></div>' +
'            <div class="summary-card blue"><div class="card-icon">💰</div><div class="card-label">Total Value</div><div class="card-value">' + data.currency + ' ' + Math.round(data.totalValue).toLocaleString() + '</div><div class="card-sub">Inventory worth</div></div>' +
'        </div>' +
'        <div class="section-title">Expiry Alerts</div>' +
'        <div class="summary-grid">' +
'            <div class="summary-card orange"><div class="card-icon">⏰</div><div class="card-label">Meds Expiring</div><div class="card-value">' + data.medExpiring + '</div><div class="card-sub">Within 30 days</div></div>' +
'            <div class="summary-card red"><div class="card-icon">☠️</div><div class="card-label">Meds Expired</div><div class="card-value">' + data.medExpired + '</div><div class="card-sub">Needs disposal</div></div>' +
'            <div class="summary-card orange"><div class="card-icon">⏰</div><div class="card-label">Equip Expiring</div><div class="card-value">' + data.eqExpiring + '</div><div class="card-sub">Within 30 days</div></div>' +
'            <div class="summary-card red"><div class="card-icon">☠️</div><div class="card-label">Equip Expired</div><div class="card-value">' + data.eqExpired + '</div><div class="card-sub">Needs attention</div></div>' +
'        </div>' +
'        <div class="section-title">Medicines Inventory (Top 50)</div>' +
'        <table class="pdf-table"><thead><tr><th>Medicine</th><th>Category</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Price</th><th>Expiry</th><th>Status</th></tr></thead><tbody>';
    
    if (data.medicines && data.medicines.length > 0) {
        data.medicines.slice(0, 50).forEach(function(m) {
            var qty = parseInt(m.total_quantity) || 0;
            var reorder = parseInt(m.reorder_level) || 0;
            var stockLabel = qty <= 0 ? 'Out' : (qty <= reorder ? 'Low' : 'OK');
            var expDate = (m.expiry_date && m.expiry_date !== '0000-00-00') ? new Date(m.expiry_date).toLocaleDateString('en-GB') : 'No Expiry';
            html += '<tr>' +
                '<td><strong>' + (m.medication_name || 'N/A') + '</strong></td>' +
                '<td>' + (m.category || 'N/A') + '</td>' +
                '<td class="qty">' + qty + '</td>' +
                '<td class="money">' + data.currency + ' ' + Math.round(parseFloat(m.selling_price) || 0).toLocaleString() + '</td>' +
                '<td>' + expDate + '</td>' +
                '<td>' + stockLabel + '</td>' +
                '</tr>';
        });
    } else {
        html += '<tr><td colspan="6" style="text-align:center;padding:20px;">No medicines found</td></tr>';
    }
    
    html += '</tbody></table>';
    html += '</div>' +
'    <div class="pdf-footer">' +
'        <div class="brand">Braick Dispensary Management System</div>' +
'        <div>👤 <strong>' + (data.adminName || '') + '</strong> | 📍 ' + (data.branchName || '') + ' | 🕐 ' + timeStr + '</div>' +
'    </div>' +
'</div>' +
'</body>' +
'</html>';
    
    w.document.open();
    w.document.write(html);
    w.document.close();
}

console.log('%c📦 Audit Inventory Report V2', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Fixed: Equipment query', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Fixed: PDF Export', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>