<?php
// ================================================================
// FILE: frontend/pages/audit/view_medicine.php
// AUDIT ROLE - VIEW MEDICINE DETAILS
// ✅ Inaonyesha data za branch ya mtumiaji aliye login TU
// ✅ Jina la mtumiaji aliye login linaonekana kwenye header
// ✅ HAKUNA Edit/Delete buttons
// ✅ Blue theme (#0B5ED7)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// ✅ AUDIT ROLE CHECK
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/audit/view_medicine.php?id=' . ($_GET['id'] ?? 0)); break;
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
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$medicine_id = (int)($_GET['id'] ?? 0);

// ================================================================
// ✅ LAZIMISHA branch ya mtumiaji aliye login TU
// ================================================================
$selected_branch_id = (int)$user_branch_id;

if ($medicine_id <= 0) {
    header('Location: /dispensary_system/frontend/pages/audit/inventory.php');
    exit;
}

// ================================================================
// ✅ DATABASE CONNECTION
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
// ✅ GET MEDICINE DETAILS - LAZIMISHA BRANCH YA MTUMIAJI
// ================================================================
$medicine = null;
try {
    $sql = "SELECT 
                m.*,
                b.name AS branch_name,
                b.location AS branch_location,
                u.full_name AS added_by_name,
                u.role AS added_by_role,
                u.profile_pic AS added_by_pic
            FROM medications_inventory m
            LEFT JOIN branches b ON m.branch_id = b.id
            LEFT JOIN users u ON m.added_by = u.id
            WHERE m.id = ? AND m.branch_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$medicine_id, $selected_branch_id]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Medicine fetch error: " . $e->getMessage());
}

if (!$medicine) {
    header('Location: /dispensary_system/frontend/pages/audit/inventory.php');
    exit;
}

// ================================================================
// GET OTHER BATCHES (same medication_name) - LAZIMISHA BRANCH
// ================================================================
$other_batches = [];
try {
    $sql = "SELECT 
                m.id, m.batch_number, m.quantity, m.expiry_date, 
                m.status, m.branch_id, m.unit_cost, m.selling_price,
                b.name AS branch_name
            FROM medications_inventory m
            LEFT JOIN branches b ON m.branch_id = b.id
            WHERE m.medication_name = ? AND m.id != ? AND m.branch_id = ?
            ORDER BY m.expiry_date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$medicine['medication_name'], $medicine_id, $selected_branch_id]);
    $other_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// GET SALES HISTORY - LAZIMISHA BRANCH
// ================================================================
$sales_history = [];
try {
    $sql = "SELECT 
                bi.item_name,
                bi.quantity,
                bi.unit_price,
                bi.total_price,
                bi.discount_amount,
                bi.created_at,
                b.bill_number,
                b.status AS bill_status,
                p.full_name AS patient_name,
                b.branch_id
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients p ON b.patient_id = p.id
            WHERE bi.item_name = ?
            AND bi.item_type = 'medication'
            AND b.status = 'paid'
            AND b.branch_id = ?
            ORDER BY bi.created_at DESC
            LIMIT 20";
    $stmt = $db->prepare($sql);
    $stmt->execute([$medicine['medication_name'], $selected_branch_id]);
    $sales_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// CALCULATIONS
// ================================================================
$quantity = (int)($medicine['quantity'] ?? 0);
$reorder_level = (int)($medicine['reorder_level'] ?? 10);
$unit_cost = (float)($medicine['unit_cost'] ?? 0);
$selling_price = (float)($medicine['selling_price'] ?? 0);
$stock_value = $quantity * $selling_price;
$profit_margin = $selling_price > 0 ? (($selling_price - $unit_cost) / $selling_price) * 100 : 0;

// Stock status
$stock_status = 'in_stock';
$stock_label = 'IN STOCK';
$stock_color = 'success';
if ($quantity <= 0) {
    $stock_status = 'out_of_stock';
    $stock_label = 'OUT OF STOCK';
    $stock_color = 'danger';
} elseif ($quantity <= $reorder_level) {
    $stock_status = 'low_stock';
    $stock_label = 'LOW STOCK';
    $stock_color = 'warning';
}

// Expiry status
$expiry_status = 'no_expiry';
$expiry_label = 'No Expiry';
$expiry_color = 'secondary';
$days_remaining = null;

if (!empty($medicine['expiry_date']) && $medicine['expiry_date'] !== '0000-00-00') {
    $expiry_ts = strtotime($medicine['expiry_date']);
    $today_ts = strtotime(date('Y-m-d'));
    $days_remaining = (int)floor(($expiry_ts - $today_ts) / 86400);
    
    if ($days_remaining < 0) {
        $expiry_status = 'expired';
        $expiry_label = 'EXPIRED';
        $expiry_color = 'danger';
    } elseif ($days_remaining <= 30) {
        $expiry_status = 'expiring_soon';
        $expiry_label = 'EXPIRING SOON';
        $expiry_color = 'warning';
    } else {
        $expiry_status = 'valid';
        $expiry_label = 'VALID';
        $expiry_color = 'success';
    }
}

// Total sold
$total_sold_qty = 0;
$total_sold_revenue = 0;
foreach ($sales_history as $s) {
    $total_sold_qty += (int)($s['quantity'] ?? 0);
    $total_sold_revenue += (float)($s['total_price'] ?? 0);
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ INCLUDES
// ================================================================
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Medicine - <?= htmlspecialchars($medicine['medication_name'] ?? 'N/A') ?></title>
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
    border-radius: 16px; padding: 20px 24px; margin-bottom: 18px;
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
.branch-tag.user-tag {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3);
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

.info-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
    transition: all 0.3s ease;
}
.info-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.info-card .card-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}
.info-card .card-header .title {
    color: white;
    font-size: 0.9rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-card .card-header .title i { color: #93C5FD; font-size: 1rem; }
.info-card .card-header .meta {
    color: rgba(255,255,255,0.9);
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(255,255,255,0.15);
    padding: 3px 10px;
    border-radius: 8px;
    backdrop-filter: blur(4px);
}

.info-card .card-body { padding: 20px 22px; }

.medicine-profile {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 22px;
    background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft));
    border-bottom: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.medicine-avatar {
    width: 72px;
    height: 72px;
    border-radius: 16px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
    border: 3px solid var(--bg-card);
}
.medicine-avatar i { color: white; }

.medicine-info { flex: 1; min-width: 200px; }

.medicine-name {
    font-size: 1.35rem;
    font-weight: 900;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 6px;
}

.medicine-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.medicine-meta-item {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--bg-card);
    padding: 4px 10px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}
.medicine-meta-item i { color: var(--primary); font-size: 0.65rem; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.status-badge.success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.secondary { background: var(--border-color); color: var(--text-secondary); }
.status-badge.primary { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 16px 18px;
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}
.stat-card:hover::before { height: 5px; }

.stat-card .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    margin-bottom: 8px;
    transition: transform 0.3s ease;
}
.stat-card:hover .stat-icon { transform: scale(1.1) rotate(-5deg); }

.stat-card .stat-label {
    font-size: 0.62rem;
    color: var(--text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
}
.stat-card .stat-value {
    font-size: 1.25rem;
    font-weight: 900;
    color: var(--text-primary);
    line-height: 1.1;
    letter-spacing: -0.03em;
    font-family: var(--font-mono);
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex-wrap: wrap;
}
.stat-card .stat-value .currency-symbol {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-secondary);
    font-family: var(--font-primary);
}
.stat-card .stat-sub {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

.stat-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-value { color: var(--primary); }

.stat-card.green::before { background: linear-gradient(90deg, #059669, #34D399); }
.stat-card.green .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.green .stat-value { color: var(--success); }

.stat-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-value { color: var(--purple); }

.stat-card.cyan::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.stat-card.cyan .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.cyan .stat-value { color: var(--cyan); }

.stat-card.warning::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
.stat-card.warning .stat-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-card.warning .stat-value { color: var(--warning); }

.stat-card.danger::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.stat-card.danger .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.danger .stat-value { color: var(--danger); }

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.82rem;
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
    min-width: 140px;
}
.info-row .label i { color: var(--primary); font-size: 0.72rem; }

.info-row .value {
    color: var(--text-primary);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
    flex: 1;
}
.info-row .value.mono {
    font-family: var(--font-mono);
    letter-spacing: -0.02em;
}
.info-row .value.amount {
    font-family: var(--font-mono);
    font-size: 0.95rem;
    color: var(--success);
    font-weight: 900;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}
.data-table thead th {
    text-align: left;
    padding: 10px 14px;
    font-weight: 800;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}
.data-table tbody tr { transition: background 0.2s ease; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.78rem;
    color: var(--success);
    text-align: right;
    letter-spacing: -0.02em;
}
.money-cell .currency-prefix {
    font-size: 0.62rem;
    color: var(--text-secondary);
    margin-right: 2px;
    font-family: var(--font-primary);
    font-weight: 600;
}

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
    font-size: 0.82rem;
}

@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 110px; }
    .stat-card .stat-value { font-size: 1.1rem; }
    .medicine-avatar { width: 56px; height: 56px; font-size: 1.4rem; }
    .medicine-name { font-size: 1.1rem; }
    .info-row { flex-direction: column; align-items: stretch; gap: 4px; }
    .info-row .label { min-width: auto; }
    .info-row .value { text-align: left; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th,
    .data-table tbody td { padding: 8px 10px; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-pills"></i>
                Medicine Details
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-circle"></i>
                Karibu, <span class="branch-tag user-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?></span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i>
                    <strong><?= htmlspecialchars($medicine['branch_name'] ?? 'N/A') ?></strong>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hashtag"></i> ID: <?= (int)$medicine['id'] ?>
                </span>
                <?php if (!empty($medicine['batch_number'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-barcode"></i> Batch: <?= htmlspecialchars($medicine['batch_number']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="/dispensary_system/frontend/pages/audit/inventory.php" 
               class="btn-header">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- MEDICINE PROFILE CARD -->
    <div class="info-card">
        <div class="medicine-profile">
            <div class="medicine-avatar">
                <i class="fas fa-pills"></i>
            </div>
            <div class="medicine-info">
                <h2 class="medicine-name"><?= htmlspecialchars($medicine['medication_name'] ?? 'N/A') ?></h2>
                <div class="medicine-meta">
                    <span class="medicine-meta-item">
                        <i class="fas fa-tag"></i>
                        <?= htmlspecialchars($medicine['category'] ?? 'N/A') ?>
                    </span>
                    <span class="medicine-meta-item">
                        <i class="fas fa-balance-scale"></i>
                        <?= htmlspecialchars($medicine['unit'] ?? 'units') ?>
                    </span>
                    <?php if (!empty($medicine['batch_number'])): ?>
                        <span class="medicine-meta-item">
                            <i class="fas fa-barcode"></i>
                            <?= htmlspecialchars($medicine['batch_number']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <span class="status-badge <?= $stock_color ?>">
                        <i class="fas fa-<?= $stock_color === 'success' ? 'check-circle' : ($stock_color === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
                        <?= $stock_label ?>
                    </span>
                    
                    <span class="status-badge <?= $expiry_color ?>">
                        <i class="fas fa-clock"></i>
                        <?= $expiry_label ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-label">Current Quantity</div>
            <div class="stat-value">
                <?= number_format($quantity) ?>
            </div>
            <div class="stat-sub">
                <i class="fas fa-exclamation-circle"></i> Reorder at <?= number_format($reorder_level) ?>
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-label">Selling Price</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <?= number_format($selling_price, 0) ?>
            </div>
            <div class="stat-sub">
                <i class="fas fa-tag"></i> Per <?= htmlspecialchars($medicine['unit'] ?? 'unit') ?>
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
            <div class="stat-label">Stock Value</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <?= number_format($stock_value, 0) ?>
            </div>
            <div class="stat-sub">
                <i class="fas fa-calculator"></i> Qty × Price
            </div>
        </div>
        
        <div class="stat-card cyan">
            <div class="stat-icon"><i class="fas fa-percentage"></i></div>
            <div class="stat-label">Profit Margin</div>
            <div class="stat-value">
                <?= number_format($profit_margin, 1) ?>%
            </div>
            <div class="stat-sub">
                <i class="fas fa-arrow-up"></i> Markup: <?= $currency ?> <?= number_format($selling_price - $unit_cost, 0) ?>
            </div>
        </div>
    </div>

    <!-- DETAILS -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
        
        <!-- MEDICINE INFORMATION -->
        <div class="info-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-info-circle"></i> Medicine Information</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Medicine ID</span>
                    <span class="value mono">#<?= (int)$medicine['id'] ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-pills"></i> Name</span>
                    <span class="value"><?= htmlspecialchars($medicine['medication_name'] ?? 'N/A') ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-tag"></i> Category</span>
                    <span class="value"><?= htmlspecialchars($medicine['category'] ?? 'N/A') ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-balance-scale"></i> Unit Type</span>
                    <span class="value"><?= htmlspecialchars($medicine['unit'] ?? 'N/A') ?></span>
                </div>
                
                <?php if (!empty($medicine['batch_number'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-barcode"></i> Batch #</span>
                    <span class="value mono"><?= htmlspecialchars($medicine['batch_number']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-toggle-on"></i> Status</span>
                    <span class="value">
                        <span class="status-badge <?= ($medicine['status'] ?? 'active') === 'active' ? 'success' : 'danger' ?>">
                            <?= strtoupper($medicine['status'] ?? 'N/A') ?>
                        </span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($medicine['branch_name'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
        
        <!-- STOCK & PRICING -->
        <div class="info-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-coins"></i> Stock & Pricing</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-boxes"></i> Quantity</span>
                    <span class="value mono" style="color:var(--primary);"><?= number_format($quantity) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Reorder Level</span>
                    <span class="value mono"><?= number_format($reorder_level) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-shopping-cart"></i> Unit Cost</span>
                    <span class="value amount">
                        <span style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                        <?= number_format($unit_cost, 0) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-tag"></i> Selling Price</span>
                    <span class="value amount">
                        <span style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                        <?= number_format($selling_price, 0) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-warehouse"></i> Stock Value</span>
                    <span class="value amount">
                        <span style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                        <?= number_format($stock_value, 0) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-percentage"></i> Profit Margin</span>
                    <span class="value mono" style="color:var(--purple);"><?= number_format($profit_margin, 1) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- EXPIRY & ADDED INFO -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
        
        <!-- EXPIRY INFO -->
        <div class="info-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-calendar-times"></i> Expiry Information</span>
                <span class="meta">
                    <span class="status-badge <?= $expiry_color ?>"><?= $expiry_label ?></span>
                </span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar"></i> Expiry Date</span>
                    <span class="value mono">
                        <?php if (!empty($medicine['expiry_date']) && $medicine['expiry_date'] !== '0000-00-00'): ?>
                            <?= date('d M Y', strtotime($medicine['expiry_date'])) ?>
                        <?php else: ?>
                            <span style="color:var(--text-secondary);">No Expiry</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if ($days_remaining !== null): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-hourglass-half"></i> Days Remaining</span>
                    <span class="value mono" style="color:var(--<?= $expiry_color ?>);">
                        <?php if ($days_remaining < 0): ?>
                            EXPIRED <?= abs($days_remaining) ?> days ago
                        <?php else: ?>
                            <?= number_format($days_remaining) ?> days
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-info-circle"></i> Expiry Status</span>
                    <span class="value">
                        <span class="status-badge <?= $expiry_color ?>"><?= $expiry_label ?></span>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- ADDED INFO -->
        <div class="info-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-user-plus"></i> Added Information</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-user"></i> Added By</span>
                    <span class="value"><?= htmlspecialchars($medicine['added_by_name'] ?? 'System') ?></span>
                </div>
                
                <?php if (!empty($medicine['added_by_role'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-tag"></i> Role</span>
                    <span class="value">
                        <span class="status-badge primary"><?= strtoupper($medicine['added_by_role']) ?></span>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Added On</span>
                    <span class="value mono">
                        <?= !empty($medicine['created_at']) ? date('d M Y, H:i', strtotime($medicine['created_at'])) : 'N/A' ?>
                    </span>
                </div>
                
                <?php if (!empty($medicine['updated_at']) && $medicine['updated_at'] !== $medicine['created_at']): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-edit"></i> Last Updated</span>
                    <span class="value mono">
                        <?= date('d M Y, H:i', strtotime($medicine['updated_at'])) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- OTHER BATCHES -->
    <?php if (count($other_batches) > 0): ?>
    <div class="info-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-layer-group"></i>
                Other Batches
                <span style="font-size:0.68rem;font-weight:600;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:8px;margin-left:6px;">
                    <?= count($other_batches) ?> batches
                </span>
            </span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Batch #</th>
                        <th>Branch</th>
                        <th style="text-align:center;">Quantity</th>
                        <th>Expiry Date</th>
                        <th style="text-align:right;">Unit Cost</th>
                        <th style="text-align:right;">Selling Price</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_num = 1; foreach ($other_batches as $b): 
                        $b_qty = (int)($b['quantity'] ?? 0);
                        $b_exp = $b['expiry_date'] ?? '';
                        $b_exp_label = 'No Expiry';
                        $b_exp_color = 'secondary';
                        
                        if ($b_exp && $b_exp !== '0000-00-00') {
                            $b_days = (int)floor((strtotime($b_exp) - strtotime(date('Y-m-d'))) / 86400);
                            if ($b_days < 0) {
                                $b_exp_label = 'Expired';
                                $b_exp_color = 'danger';
                            } elseif ($b_days <= 30) {
                                $b_exp_label = $b_days . ' days';
                                $b_exp_color = 'warning';
                            } else {
                                $b_exp_label = $b_days . ' days';
                                $b_exp_color = 'success';
                            }
                        }
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                            <td>
                                <span style="font-family:var(--font-mono);font-size:0.7rem;color:var(--primary);font-weight:800;background:var(--primary-bg);padding:3px 7px;border-radius:5px;">
                                    <?= htmlspecialchars($b['batch_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size:0.72rem;color:var(--text-secondary);">
                                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($b['branch_name'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                                <?= number_format($b_qty) ?>
                            </td>
                            <td style="font-size:0.72rem;font-family:var(--font-mono);">
                                <?= $b_exp && $b_exp !== '0000-00-00' ? date('d M Y', strtotime($b_exp)) : 'No Expiry' ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($b['unit_cost'] ?? 0, 0) ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($b['selling_price'] ?? 0, 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= $b_exp_color ?>">
                                    <i class="fas fa-clock"></i> <?= $b_exp_label ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- SALES HISTORY -->
    <div class="info-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-history"></i>
                Sales History
                <span style="font-size:0.68rem;font-weight:600;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:8px;margin-left:6px;">
                    <?= count($sales_history) ?> records
                </span>
            </span>
            <span class="meta">
                <i class="fas fa-box"></i> Total Sold: <?= number_format($total_sold_qty) ?> units
                &nbsp;|&nbsp;
                <i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($total_sold_revenue, 0) ?>
            </span>
        </div>
        <?php if (count($sales_history) > 0): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th style="text-align:center;">Quantity</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $s_num = 1; foreach ($sales_history as $s): ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $s_num++ ?></td>
                            <td>
                                <span style="font-family:var(--font-mono);font-size:0.68rem;color:var(--primary);font-weight:800;background:var(--primary-bg);padding:3px 7px;border-radius:5px;">
                                    <?= htmlspecialchars($s['bill_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;font-weight:600;">
                                <?= htmlspecialchars($s['patient_name'] ?? 'Walk-in') ?>
                            </td>
                            <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                                <?= number_format($s['quantity'] ?? 0) ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($s['unit_price'] ?? 0, 0) ?>
                            </td>
                            <td class="money-cell" style="color:var(--danger);">
                                <?php if (($s['discount_amount'] ?? 0) > 0): ?>
                                    - <span class="currency-prefix"><?= $currency ?></span><?= number_format($s['discount_amount'], 0) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($s['total_price'] ?? 0, 0) ?>
                            </td>
                            <td>
                                <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($s['created_at'])) ?></div>
                                <div style="font-size:0.58rem;color:var(--text-secondary);font-family:var(--font-mono);"><?= date('H:i', strtotime($s['created_at'])) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No sales recorded for this medicine yet</p>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
console.log('%c💊 AUDIT View Medicine', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#F59E0B; font-weight:bold;');
console.log('%c✅ Inaonyesha data za branch: <?= htmlspecialchars($user_branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c💊 Medicine: <?= htmlspecialchars($medicine['medication_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c📦 Quantity: <?= number_format($quantity) ?>', 'font-size:13px; color:#3B82F6;');
console.log('%c💰 Stock Value: <?= $currency ?> <?= number_format($stock_value, 0) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>