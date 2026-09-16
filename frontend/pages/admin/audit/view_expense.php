<?php
// ================================================================
// FILE: frontend/pages/admin/audit/view_expense.php
// ADMIN AUDIT - VIEW EXPENSE DETAILS
// ✅ View expense details with recorded by info
// ✅ RED THEME (matching expenses section)
// ✅ Print & PDF export
// ✅ Receipt preview
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

$expense_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($expense_id <= 0) {
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
// GET EXPENSE DETAILS
// ================================================================
$expense = null;
try {
    $sql = "SELECT 
        e.*,
        u.full_name as recorded_by_name,
        u.username as recorded_by_username,
        u.role as recorded_by_role,
        u.email as recorded_by_email,
        u.phone as recorded_by_phone,
        u.profile_pic as recorded_by_pic,
        br.name as branch_name,
        br.location as branch_location,
        br.phone as branch_phone,
        br.email as branch_email
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN branches br ON e.branch_id = br.id
    WHERE e.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Expense fetch error: " . $e->getMessage());
}

if (!$expense) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// CATEGORY ICONS & COLORS
// ================================================================
$category_meta = [
    'rent' => ['icon' => 'fa-home', 'color' => '#7C3AED', 'bg' => '#EDE9FE'],
    'salary' => ['icon' => 'fa-users', 'color' => '#059669', 'bg' => '#D1FAE5'],
    'utilities' => ['icon' => 'fa-bolt', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    'supplies' => ['icon' => 'fa-box', 'color' => '#0891B2', 'bg' => '#CFFAFE'],
    'equipment' => ['icon' => 'fa-tools', 'color' => '#3B82F6', 'bg' => '#DBEAFE'],
    'maintenance' => ['icon' => 'fa-wrench', 'color' => '#DC2626', 'bg' => '#FEE2E2'],
    'transport' => ['icon' => 'fa-car', 'color' => '#DB2777', 'bg' => '#FCE7F3'],
    'food' => ['icon' => 'fa-utensils', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    'marketing' => ['icon' => 'fa-bullhorn', 'color' => '#0D9488', 'bg' => '#CCFBF1'],
    'other' => ['icon' => 'fa-ellipsis-h', 'color' => '#64748B', 'bg' => '#F1F5F9'],
];

// Get category meta (case-insensitive)
$category_key = strtolower(trim($expense['category'] ?? 'other'));
$cat_meta = $category_meta[$category_key] ?? $category_meta['other'];

// STATUS INFO
$status_info = [
    'paid' => ['label' => 'PAID', 'icon' => 'fa-check-circle', 'color' => '#059669', 'bg' => '#D1FAE5'],
    'pending' => ['label' => 'PENDING', 'icon' => 'fa-clock', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    'cancelled' => ['label' => 'CANCELLED', 'icon' => 'fa-times-circle', 'color' => '#DC2626', 'bg' => '#FEE2E2'],
];

$current_status = $status_info[$expense['status'] ?? 'paid'] ?? $status_info['paid'];

// PAYMENT METHOD ICONS
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

$pm_key = $expense['payment_method'] ?? 'cash';
$pm_meta = $payment_icons[$pm_key] ?? $payment_icons['cash'];

// AMOUNT
$amount = (float)($expense['amount'] ?? 0);

// RECORDED BY
$recorder_name = $expense['recorded_by_name'] ?? 'N/A';
$recorder_parts = explode(' ', trim($recorder_name));
$recorder_initials = count($recorder_parts) >= 2 
    ? strtoupper(substr($recorder_parts[0], 0, 1) . substr($recorder_parts[1], 0, 1))
    : strtoupper(substr($recorder_name, 0, 2));

$recorder_pic_url = !empty($expense['recorded_by_pic'])
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $expense['recorded_by_pic']
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <title>View Expense - <?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?></title>
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
    
    /* ✅ RED THEME for Expenses */
    --expense-primary: #DC2626;
    --expense-dark: #B91C1C;
    --expense-light: #F87171;
    --expense-bg: #FEE2E2;
    --expense-soft: #FECACA;
    
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
    
    --expense-primary: #EF4444;
    --expense-dark: #DC2626;
    --expense-light: #FCA5A5;
    --expense-bg: #3A1A1A;
    --expense-soft: #7F1D1D;
    
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

/* ================================================================
   ✅ PAGE HEADER - RED THEME
   ================================================================ */
.page-header {
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
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

.page-header .page-title i { font-size: 1.5rem; color: #FCA5A5; }

.page-header .page-subtitle {
    color: rgba(255,255,255,0.9);
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
    background: rgba(255,255,255,0.18);
    color: white;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
}

.btn-header {
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.28);
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
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

/* ================================================================
   ✅ STATUS BANNER - RED THEME
   ================================================================ */
.status-banner {
    border-radius: 14px;
    padding: 18px 24px;
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
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
}

.status-banner.pending {
    background: linear-gradient(135deg, #D97706, #B45309);
    color: white;
}

.status-banner.cancelled {
    background: linear-gradient(135deg, #475569, #334155);
    color: white;
}

.status-banner::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    pointer-events: none;
}

.status-banner .status-left {
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    z-index: 1;
}

.status-banner .status-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    border: 2px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.status-banner .status-info .status-label {
    font-size: 1.35rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-banner .status-info .status-sub {
    font-size: 0.75rem;
    opacity: 0.9;
    font-weight: 500;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-banner .status-amount {
    text-align: right;
    position: relative;
    z-index: 1;
}

.status-banner .status-amount .label {
    font-size: 0.65rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    margin-bottom: 4px;
}

.status-banner .status-amount .value {
    font-size: 2rem;
    font-weight: 900;
    font-family: var(--font-mono);
    letter-spacing: -0.03em;
    line-height: 1;
    text-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.status-banner .status-amount .currency-prefix {
    font-size: 1rem;
    opacity: 0.85;
    font-family: var(--font-primary);
    font-weight: 700;
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
}

/* ✅ RED THEME INFO CARD */
.info-card.red-theme {
    border-color: var(--expense-primary);
}

.info-card.red-theme:hover {
    border-color: var(--expense-primary);
    box-shadow: 0 10px 30px rgba(220, 38, 38, 0.15);
}

.info-card.red-theme .card-header {
    background: linear-gradient(135deg, var(--expense-bg), var(--expense-soft));
    border-bottom-color: rgba(220, 38, 38, 0.2);
}

.info-card.red-theme .card-header i {
    color: var(--expense-primary);
}

.info-card .card-header {
    padding: 14px 18px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-primary);
}

.info-card .card-header .title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card .card-header i {
    color: var(--primary);
    font-size: 0.95rem;
}

.info-card .card-body {
    padding: 18px 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
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
    min-width: 100px;
}

.info-row .label i {
    font-size: 0.7rem;
    opacity: 0.8;
    color: var(--expense-primary);
}

.info-row .value {
    color: var(--text-primary);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
    flex: 1;
}

.info-row .value.mono {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    letter-spacing: -0.02em;
}

.info-row .value.amount {
    font-family: var(--font-mono);
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--expense-primary);
    letter-spacing: -0.03em;
}

.info-row .value.highlight {
    background: var(--expense-bg);
    color: var(--expense-primary);
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 800;
}

/* CATEGORY DISPLAY */
.category-display {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--expense-bg), var(--expense-soft));
    border-bottom: 2px solid var(--border-color);
}

.category-display .cat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: var(--expense-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
    border: 3px solid var(--bg-card);
}

.category-display .cat-info {
    flex: 1;
    min-width: 0;
}

.category-display .cat-name {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    text-transform: capitalize;
}

.category-display .cat-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 2px;
}

/* RECORDED BY PROFILE */
.recorder-profile {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: linear-gradient(135deg, var(--expense-bg), var(--expense-soft));
    border-bottom: 2px solid var(--border-color);
}

.recorder-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #DC2626, #F87171);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    font-weight: 900;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.35);
    border: 3px solid var(--bg-card);
    overflow: hidden;
}

.recorder-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.recorder-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 4px;
}

.recorder-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.recorder-meta-item {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-card);
    padding: 3px 9px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.recorder-meta-item i {
    color: var(--expense-primary);
    font-size: 0.62rem;
}

/* DESCRIPTION BOX */
.description-box {
    background: var(--bg-body);
    border-radius: 10px;
    padding: 14px 16px;
    border-left: 4px solid var(--expense-primary);
    margin-bottom: 12px;
}

.description-box .desc-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
    font-weight: 800;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.description-box .desc-label i {
    color: var(--expense-primary);
}

.description-box .desc-text {
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 500;
    line-height: 1.6;
    word-break: break-word;
}

/* NOTES BOX */
.notes-box {
    background: var(--warning-bg);
    border-radius: 10px;
    padding: 14px 16px;
    border-left: 4px solid var(--warning);
    margin-bottom: 12px;
}

.notes-box .notes-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--warning);
    font-weight: 800;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.notes-box .notes-text {
    font-size: 0.82rem;
    color: #78350F;
    font-weight: 500;
    line-height: 1.6;
}

[data-theme="dark"] .notes-box .notes-text {
    color: #FEF3C7;
}

/* RECEIPT BOX */
.receipt-box {
    background: var(--success-bg);
    border-radius: 10px;
    padding: 14px 16px;
    border-left: 4px solid var(--success);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.receipt-box .receipt-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.72rem;
    color: var(--success);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.receipt-box .receipt-label i {
    font-size: 1rem;
}

.receipt-box .receipt-value {
    font-family: var(--font-mono);
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--success);
    background: var(--bg-card);
    padding: 4px 12px;
    border-radius: 8px;
    border: 1px solid var(--success);
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
    background: var(--expense-bg);
    color: var(--expense-primary);
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

.btn-edit {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-edit:hover {
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
}

.btn-expense {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-expense:hover {
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
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

/* TIME INFO */
.time-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg-body);
    border-radius: 10px;
    margin-top: 10px;
}

.time-info-item {
    flex: 1;
    text-align: center;
    padding: 8px;
    border-right: 1px solid var(--border-color);
}

.time-info-item:last-child {
    border-right: none;
}

.time-info-item .ti-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    font-weight: 700;
    margin-bottom: 3px;
}

.time-info-item .ti-value {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: var(--font-mono);
    letter-spacing: -0.02em;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .view-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.5rem; }
    .status-banner .status-icon { width: 48px; height: 48px; font-size: 1.3rem; }
    .status-banner .status-info .status-label { font-size: 1.1rem; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .category-display .cat-icon { width: 44px; height: 44px; font-size: 1.15rem; }
    .category-display .cat-name { font-size: 1rem; }
    .recorder-avatar { width: 48px; height: 48px; font-size: 1.15rem; }
    .recorder-name { font-size: 0.95rem; }
}

@media (max-width: 480px) {
    .category-display { padding: 12px 14px; }
    .category-display .cat-icon { width: 40px; height: 40px; font-size: 1rem; }
    .info-row { font-size: 0.75rem; }
    .info-row .label { font-size: 0.68rem; min-width: 80px; }
}

/* PRINT */
@media print {
    .action-bar, .btn-header { display: none !important; }
    .page-header { 
        background: #DC2626 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .status-banner {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- ============================================================ -->
    <!-- PAGE HEADER - RED THEME -->
    <!-- ============================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Expense Details
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($expense['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($expense['payment_date'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="edit_expense.php?id=<?= $expense_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STATUS BANNER - RED THEME -->
    <!-- ============================================================ -->
    <div class="status-banner <?= htmlspecialchars($expense['status'] ?? 'paid') ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas <?= htmlspecialchars($current_status['icon']) ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label">
                    <?= htmlspecialchars($current_status['label']) ?>
                    <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-weight:700;letter-spacing:0.03em;">
                        <i class="fas <?= htmlspecialchars($cat_meta['icon']) ?>"></i>
                        <?= htmlspecialchars(ucfirst($expense['category'] ?? 'Other')) ?>
                    </span>
                </div>
                <div class="status-sub">
                    <i class="fas <?= htmlspecialchars($pm_meta['icon']) ?>"></i>
                    <?= htmlspecialchars($pm_meta['label']) ?>
                    <span style="opacity:0.6;">•</span>
                    <i class="fas fa-user-check"></i>
                    Recorded by <?= htmlspecialchars($recorder_name) ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label">Total Amount</div>
            <div class="value">
                <span class="currency-prefix"><?= $currency ?></span>
                <?= number_format($amount, 0) ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MAIN GRID -->
    <!-- ============================================================ -->
    <div class="view-grid">
        
        <!-- EXPENSE INFO -->
        <div class="info-card red-theme">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Expense Information
                </span>
                <span style="font-size:0.68rem;background:var(--expense-bg);color:var(--expense-primary);padding:3px 10px;border-radius:10px;font-weight:800;">
                    ID #<?= $expense['id'] ?>
                </span>
            </div>
            
            <!-- CATEGORY DISPLAY -->
            <div class="category-display">
                <div class="cat-icon">
                    <i class="fas <?= htmlspecialchars($cat_meta['icon']) ?>"></i>
                </div>
                <div class="cat-info">
                    <div class="cat-name"><?= htmlspecialchars(ucfirst($expense['category'] ?? 'Other')) ?></div>
                    <div class="cat-label">Expense Category</div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Expense #</span>
                    <span class="value mono" style="color:var(--expense-primary);">
                        <?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Amount</span>
                    <span class="value amount">
                        <span style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                        <?= number_format($amount, 0) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                    <span class="value highlight">
                        <i class="fas <?= htmlspecialchars($pm_meta['icon']) ?>"></i>
                        <?= htmlspecialchars($pm_meta['label']) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-day"></i> Payment Date</span>
                    <span class="value"><?= date('d M Y', strtotime($expense['payment_date'])) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-flag"></i> Status</span>
                    <span class="value">
                        <span class="status-badge <?= htmlspecialchars($expense['status'] ?? 'paid') ?>" 
                              style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:8px;font-size:0.68rem;font-weight:800;text-transform:uppercase;
                              background:<?= $expense['status'] === 'paid' ? 'var(--success-bg)' : ($expense['status'] === 'pending' ? 'var(--warning-bg)' : 'var(--danger-bg)') ?>;
                              color:<?= $expense['status'] === 'paid' ? 'var(--success)' : ($expense['status'] === 'pending' ? 'var(--warning)' : 'var(--danger)') ?>;">
                            <i class="fas fa-<?= $expense['status'] === 'paid' ? 'check-circle' : ($expense['status'] === 'pending' ? 'clock' : 'times-circle') ?>"></i>
                            <?= strtoupper($expense['status'] ?? 'PAID') ?>
                        </span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($expense['branch_name'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>

        <!-- RECORDED BY -->
        <div class="info-card red-theme">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-user-check"></i>
                    Recorded By
                </span>
                <span style="font-size:0.68rem;background:var(--expense-bg);color:var(--expense-primary);padding:3px 10px;border-radius:10px;font-weight:800;">
                    <i class="fas fa-clock"></i>
                    <?= date('H:i', strtotime($expense['created_at'])) ?>
                </span>
            </div>
            
            <!-- RECORDER PROFILE -->
            <div class="recorder-profile">
                <div class="recorder-avatar">
                    <?php if (!empty($expense['recorded_by_pic'])): ?>
                        <img src="<?= $recorder_pic_url ?>" 
                             alt="<?= htmlspecialchars($recorder_name) ?>"
                             onerror="this.style.display='none';this.parentNode.innerHTML='<?= htmlspecialchars($recorder_initials) ?>';">
                    <?php else: ?>
                        <?= htmlspecialchars($recorder_initials) ?>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="recorder-name"><?= htmlspecialchars($recorder_name) ?></div>
                    <div class="recorder-meta">
                        <?php if (!empty($expense['recorded_by_role'])): ?>
                            <span class="recorder-meta-item">
                                <i class="fas fa-user-tag"></i>
                                <?= htmlspecialchars(strtoupper($expense['recorded_by_role'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($expense['recorded_by_username'])): ?>
                            <span class="recorder-meta-item">
                                <i class="fas fa-at"></i>
                                <?= htmlspecialchars($expense['recorded_by_username']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-user"></i> Full Name</span>
                    <span class="value"><?= htmlspecialchars($recorder_name) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value" style="font-size:0.75rem;">
                        <?= htmlspecialchars($expense['recorded_by_email'] ?? 'N/A') ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono">
                        <?= htmlspecialchars($expense['recorded_by_phone'] ?? 'N/A') ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($expense['branch_name'] ?? 'N/A') ?></span>
                </div>
                
                <!-- TIME INFO -->
                <div class="time-info">
                    <div class="time-info-item">
                        <div class="ti-label">Recorded At</div>
                        <div class="ti-value"><?= date('d M Y', strtotime($expense['created_at'])) ?></div>
                    </div>
                    <div class="time-info-item">
                        <div class="ti-label">Time</div>
                        <div class="ti-value"><?= date('H:i:s', strtotime($expense['created_at'])) ?></div>
                    </div>
                    <?php if (!empty($expense['updated_at']) && $expense['updated_at'] !== $expense['created_at']): ?>
                    <div class="time-info-item">
                        <div class="ti-label">Updated</div>
                        <div class="ti-value"><?= date('d M Y', strtotime($expense['updated_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ============================================================ -->
    <!-- DESCRIPTION + RECEIPT + NOTES -->
    <!-- ============================================================ -->
    <div class="info-card red-theme" style="margin-bottom:18px;">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-align-left"></i>
                Description & Details
            </span>
        </div>
        <div class="card-body">
            
            <!-- DESCRIPTION -->
            <?php if (!empty($expense['description'])): ?>
            <div class="description-box">
                <div class="desc-label">
                    <i class="fas fa-file-alt"></i>
                    Description
                </div>
                <div class="desc-text">
                    <?= nl2br(htmlspecialchars($expense['description'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- RECEIPT -->
            <?php if (!empty($expense['receipt_number'])): ?>
            <div class="receipt-box">
                <div class="receipt-label">
                    <i class="fas fa-receipt"></i>
                    Receipt Number
                </div>
                <div class="receipt-value">
                    <?= htmlspecialchars($expense['receipt_number']) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- NOTES -->
            <?php if (!empty($expense['notes'])): ?>
            <div class="notes-box">
                <div class="notes-label">
                    <i class="fas fa-sticky-note"></i>
                    Additional Notes
                </div>
                <div class="notes-text">
                    <?= nl2br(htmlspecialchars($expense['notes'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- EMPTY STATE -->
            <?php if (empty($expense['description']) && empty($expense['receipt_number']) && empty($expense['notes'])): ?>
            <div class="empty-state" style="padding:30px 20px;">
                <i class="fas fa-info-circle"></i>
                <p>No additional details available</p>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ACTION BAR -->
    <!-- ============================================================ -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="info-text">
                <div class="info-title">Expense Actions</div>
                <div class="info-sub">View, edit, or manage this expense</div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="edit_expense.php?id=<?= $expense_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-edit">
                <i class="fas fa-edit"></i> Edit Expense
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn btn-expense">
                <i class="fas fa-arrow-left"></i> Back to Revenue
            </a>
        </div>
    </div>

</main>

<script>
console.log('%c🧾 View Expense - Audit (RED THEME)', 'font-size:18px; font-weight:bold; color:#DC2626;');
console.log('%c✅ Expense #<?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?>', 'font-size:13px; color:#DC2626;');
console.log('%c💰 Amount: <?= $currency ?> <?= number_format($amount, 0) ?>', 'font-size:13px; color:#DC2626; font-weight:bold;');
console.log('%c📁 Category: <?= htmlspecialchars($expense['category'] ?? 'N/A') ?>', 'font-size:13px; color:#D97706;');
console.log('%c👤 Recorded By: <?= htmlspecialchars($recorder_name) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>