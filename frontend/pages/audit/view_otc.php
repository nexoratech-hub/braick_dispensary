<?php
// ================================================================
// FILE: frontend/pages/audit/view_otc.php
// AUDIT ROLE - VIEW OTC SALE DETAILS (V2 - BRANCH LOCKED)
// ✅ AUDIT ANAONA OTC SALE ZA BRANCH YAKE TU
// ✅ View OTC sale details
// ✅ Blue theme #0B5ED7
// ✅ Print & PDF export
// ✅ Kwa AUDIT role tu
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// ✅ AUDIT ROLE TU
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

$sale_id = (int)($_GET['id'] ?? 0);

// ✅ AUDIT ANAONA BRANCH YAKE TU
$selected_branch_id = $user_branch_id;

if ($sale_id <= 0) {
    header('Location: other_services.php?tab=otc_bills');
    exit;
}

// ================================================================
// ✅ DATABASE PATH - juu mara 3 kutoka pages/audit/
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
// GET OTC SALE DETAILS - ✅ BRANCH YA ALIYE LOGIN TU
// ================================================================
$sale = null;
try {
    $sql = "SELECT 
        o.*,
        u.full_name as sold_by_name,
        u.username as sold_by_username,
        u.role as sold_by_role,
        u.profile_pic as sold_by_pic,
        br.name as branch_name,
        br.location as branch_location,
        br.phone as branch_phone
    FROM otc_sales o
    LEFT JOIN users u ON o.sold_by = u.id
    LEFT JOIN branches br ON o.branch_id = br.id
    WHERE o.id = ? AND o.branch_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$sale_id, $user_branch_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("OTC fetch error: " . $e->getMessage());
}

if (!$sale) {
    $_SESSION['error_message'] = "OTC sale not found or you don't have permission to view this sale (different branch).";
    header('Location: other_services.php?tab=otc_bills');
    exit;
}

// ================================================================
// GET OTC SALE ITEMS
// ================================================================
$sale_items = [];
try {
    $sql = "SELECT * FROM otc_sale_items 
            WHERE sale_id = ? 
            ORDER BY id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("OTC items error: " . $e->getMessage());
}

// ================================================================
// CALCULATIONS
// ================================================================
$subtotal = (float)($sale['subtotal'] ?? 0);
$discount = (float)($sale['discount_amount'] ?? 0);
$tax = (float)($sale['tax_amount'] ?? 0);
$total_amount = (float)($sale['total_amount'] ?? 0);

// If subtotal is 0, calculate from items
if ($subtotal == 0 && count($sale_items) > 0) {
    foreach ($sale_items as $item) {
        $subtotal += (float)($item['total_price'] ?? 0);
    }
}

// Payment status
$status_info = [
    'paid' => ['label' => 'PAID', 'icon' => 'fa-check-circle', 'color' => '#059669', 'bg' => '#D1FAE5'],
    'pending' => ['label' => 'PENDING', 'icon' => 'fa-clock', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    'partial' => ['label' => 'PARTIAL', 'icon' => 'fa-hourglass-half', 'color' => '#0891B2', 'bg' => '#CFFAFE'],
    'cancelled' => ['label' => 'CANCELLED', 'icon' => 'fa-times-circle', 'color' => '#DC2626', 'bg' => '#FEE2E2'],
];

$payment_status = strtolower($sale['payment_status'] ?? 'pending');
$current_status = $status_info[$payment_status] ?? $status_info['pending'];

// Payment method icons
$payment_icons = [
    'cash' => ['icon' => 'fa-money-bill-wave', 'label' => 'Cash'],
    'm-pesa' => ['icon' => 'fa-mobile-alt', 'label' => 'M-Pesa'],
    'airtel_money' => ['icon' => 'fa-mobile-alt', 'label' => 'Airtel Money'],
    'tigo_pesa' => ['icon' => 'fa-mobile-alt', 'label' => 'Tigo Pesa'],
    'halopesa' => ['icon' => 'fa-mobile-alt', 'label' => 'Halopesa'],
    'bank' => ['icon' => 'fa-university', 'label' => 'Bank Transfer'],
    'card' => ['icon' => 'fa-credit-card', 'label' => 'Card'],
    'other' => ['icon' => 'fa-wallet', 'label' => 'Other'],
];

$pm_key = $sale['payment_method'] ?? 'cash';
$pm_meta = $payment_icons[$pm_key] ?? $payment_icons['cash'];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$sold_by_pic_url = !empty($sale['sold_by_pic'])
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $sale['sold_by_pic']
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ HEADER NA SIDEBAR (path: juu mara 2 kutoka pages/audit/)
// ================================================================
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View OTC Sale - <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></title>
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

* {
    font-family: var(--font-primary);
    -webkit-font-smoothing: antialiased;
}

html, body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    color: var(--text-primary);
}

.money-number, .money-cell, .font-mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header {
    background: linear-gradient(135deg, #0891B2 0%, #0E7490 100%);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(8, 145, 178, 0.25);
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

.page-header .page-title i { font-size: 1.5rem; color: #67E8F9; }

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

/* CUSTOMER PROFILE */
.customer-profile {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--cyan-bg), rgba(8, 145, 178, 0.1));
    border-bottom: 2px solid var(--border-color);
}

.customer-avatar {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0891B2, #06B6D4);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(8, 145, 178, 0.3);
    border: 3px solid var(--bg-card);
}

.customer-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 4px;
}

.customer-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.customer-meta-item {
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

.customer-meta-item i {
    color: var(--cyan);
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
    background: linear-gradient(135deg, #0891B2, #0E7490);
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
    color: #67E8F9;
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
    background: linear-gradient(135deg, #0891B2, #0E7490);
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
.items-table tbody tr:hover td { background: var(--cyan-bg); }
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
    background: var(--cyan-bg);
    color: var(--cyan);
}

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
.money-cell.cyan { color: var(--cyan); }

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
.total-row .value.cyan { color: var(--cyan); }

.total-row.grand {
    background: linear-gradient(135deg, var(--cyan-bg), rgba(8, 145, 178, 0.15));
    border-top: 3px solid var(--cyan);
    border-bottom: 3px solid var(--cyan);
    padding: 16px 20px;
    margin: 6px 0;
}

.total-row.grand .label {
    color: var(--cyan);
    font-size: 1rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.total-row.grand .value {
    color: var(--cyan);
    font-size: 1.35rem;
    font-weight: 900;
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
    background: var(--cyan-bg);
    color: var(--cyan);
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
    background: linear-gradient(135deg, #0891B2, #0E7490);
    color: white;
    box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
}

.btn-primary:hover {
    box-shadow: 0 6px 20px rgba(8, 145, 178, 0.5);
}

.btn-secondary {
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    border-color: var(--cyan);
    color: var(--cyan);
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
    .customer-profile { padding: 12px 14px; }
    .customer-avatar { width: 44px; height: 44px; font-size: 1.15rem; }
    .customer-name { font-size: 0.95rem; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                OTC Sale Details
                <span class="branch-tag" style="background:linear-gradient(135deg,#3B82F6,#2563EB);font-weight:800;">
                    <i class="fas fa-shield-alt"></i> AUDIT
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($sale['created_at'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?tab=otc_bills" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <div class="status-banner <?= htmlspecialchars($payment_status) ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas <?= htmlspecialchars($current_status['icon']) ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label"><?= htmlspecialchars($current_status['label']) ?></div>
                <div class="status-sub">
                    <?php if ($payment_status === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Sale has been fully paid
                    <?php elseif ($payment_status === 'partial'): ?>
                        <i class="fas fa-hourglass-half"></i> Partial payment received
                    <?php elseif ($payment_status === 'cancelled'): ?>
                        <i class="fas fa-times-circle"></i> Sale was cancelled
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

    <!-- CUSTOMER + SALE INFO GRID -->
    <div class="view-grid">
        
        <!-- CUSTOMER INFO -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-user"></i> Customer Information
            </div>
            
            <div class="customer-profile">
                <div class="customer-avatar">
                    <?= strtoupper(substr($sale['customer_name'] ?? 'W', 0, 1)) ?>
                </div>
                <div>
                    <div class="customer-name"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                    <div class="customer-meta">
                        <span class="customer-meta-item">
                            <i class="fas fa-shopping-bag"></i> OTC Sale
                        </span>
                        <?php if (!empty($sale['customer_phone'])): ?>
                        <span class="customer-meta-item">
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($sale['customer_phone']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-user"></i> Customer Name</span>
                    <span class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                    <span class="value">
                        <i class="fas <?= htmlspecialchars($pm_meta['icon']) ?>"></i>
                        <?= htmlspecialchars($pm_meta['label']) ?>
                    </span>
                </div>
                <?php if (!empty($sale['notes'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                    <span class="value" style="font-size:0.72rem;"><?= htmlspecialchars($sale['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SALE INFO -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Sale Information
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Sale Number</span>
                    <span class="value mono" style="color:var(--cyan);"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Created</span>
                    <span class="value"><?= date('d M Y, H:i', strtotime($sale['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-check"></i> Last Updated</span>
                    <span class="value"><?= date('d M Y, H:i', strtotime($sale['updated_at'] ?? $sale['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($sale['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-tie"></i> Sold By</span>
                    <span class="value">
                        <?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?>
                        <?php if (!empty($sale['sold_by_role'])): ?>
                            <span style="font-size:0.6rem;background:var(--cyan-bg);color:var(--cyan);padding:1px 6px;border-radius:4px;font-weight:800;text-transform:uppercase;margin-left:4px;">
                                <?= strtoupper($sale['sold_by_role']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-boxes"></i> Total Items</span>
                    <span class="value mono"><?= count($sale_items) ?></span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ITEMS TABLE -->
    <div class="items-table-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-list"></i>
                Sale Items
            </span>
            <span class="count"><?= count($sale_items) ?> items</span>
        </div>
        
        <?php if (count($sale_items) > 0): ?>
        <div style="overflow-x:auto;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:50px;text-align:center;">Icon</th>
                        <th>Item Name</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $item_num = 1; foreach ($sale_items as $item): 
                        $item_total = (float)($item['total_price'] ?? 0);
                        $unit_price = (float)($item['unit_price'] ?? 0);
                        $qty = (int)($item['quantity'] ?? 0);
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_num++ ?></td>
                            <td style="text-align:center;">
                                <div class="item-icon" title="OTC Item">
                                    <i class="fas fa-pills"></i>
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
                            <td style="text-align:center;font-weight:800;color:var(--cyan);font-family:var(--font-mono);">
                                <?= number_format($qty) ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($unit_price, 0) ?>
                            </td>
                            <td class="money-cell cyan">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_total, 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No items in this sale</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TOTALS -->
    <div class="totals-card">
        <div class="card-header">
            <i class="fas fa-calculator"></i> Sale Summary
        </div>
        <div class="totals-body">
            <div class="total-row">
                <span class="label"><i class="fas fa-receipt"></i> Subtotal</span>
                <span class="value blue">
                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($subtotal, 0) ?>
                </span>
            </div>
            
            <?php if ($discount > 0): ?>
            <div class="total-row">
                <span class="label"><i class="fas fa-tags"></i> Discount</span>
                <span class="value red">
                    - <span class="currency-prefix"><?= $currency ?></span><?= number_format($discount, 0) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if ($tax > 0): ?>
            <div class="total-row">
                <span class="label"><i class="fas fa-percent"></i> Tax</span>
                <span class="value">
                    + <span class="currency-prefix"><?= $currency ?></span><?= number_format($tax, 0) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <div class="total-row grand">
                <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                <span class="value">
                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($total_amount, 0) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="info-text">
                <div class="info-title">OTC Sale Actions</div>
                <div class="info-sub">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?>
                </div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?tab=otc_bills" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to OTC Bills
            </a>
        </div>
    </div>

</main>

<script>
console.log('%c🛒 View OTC Sale - Audit (BRANCH LOCKED)', 'font-size:18px; font-weight:bold; color:#0891B2;');
console.log('%c✅ AUDIT ANAONA BRANCH YAKE TU', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c👥 Branch: <?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?>', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c✅ Sale #<?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#34D399;');
console.log('%c💰 Total: <?= $currency ?> <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#0891B2; font-weight:bold;');
console.log('%c📦 Items: <?= count($sale_items) ?>', 'font-size:13px; color:#0891B2;');
</script>

</body>
</html>