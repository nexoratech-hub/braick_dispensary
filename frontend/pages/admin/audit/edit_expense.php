<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_expense.php
// ADMIN AUDIT - EDIT EXPENSE (Admin Override)
// ✅ Edit expense details
// ✅ MONEY FORMAT: Live commas (1,000,000)
// ✅ RED THEME (matching expenses section)
// ✅ Audit trail logging
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
// HELPER: Parse money string
// ================================================================
function parseMoney($value) {
    if (is_numeric($value)) return (float)$value;
    $clean = preg_replace('/[^0-9.]/', '', (string)$value);
    return (float)($clean ?: 0);
}

// ================================================================
// HANDLE UPDATE
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_expense') {
    try {
        $db->beginTransaction();
        
        // GET OLD DATA for audit log
        $stmt = $db->prepare("SELECT expense_number, category, amount, status FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        $old_expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // GET FORM DATA
        $category = trim($_POST['category'] ?? 'Other');
        $description = trim($_POST['description'] ?? '');
        $amount = parseMoney($_POST['amount'] ?? '0');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'paid';
        $receipt_number = trim($_POST['receipt_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // UPDATE EXPENSE
        $sql = "UPDATE expenses SET 
            category = ?, description = ?, amount = ?, 
            payment_method = ?, payment_date = ?, status = ?, 
            receipt_number = ?, notes = ?, updated_at = NOW()
            WHERE id = ?";
        $db->prepare($sql)->execute([
            $category, $description, $amount, 
            $payment_method, $payment_date, $status, 
            $receipt_number, $notes, $expense_id
        ]);
        
        // AUDIT LOG
        try {
            $changes = [];
            if ($old_expense) {
                if ((float)$old_expense['amount'] != $amount) {
                    $changes[] = "Amount: " . $currency . " " . number_format($old_expense['amount'], 0) . " → " . $currency . " " . number_format($amount, 0);
                }
                if ($old_expense['category'] !== $category) {
                    $changes[] = "Category: " . $old_expense['category'] . " → " . $category;
                }
                if ($old_expense['status'] !== $status) {
                    $changes[] = "Status: " . $old_expense['status'] . " → " . $status;
                }
            }
            
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'edit_expense', ?, ?, NOW())")
               ->execute([
                   $user_id,
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                   "Edited expense: " . ($old_expense['expense_number'] ?? 'N/A') . " | " . implode(' | ', $changes),
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $alert_message = "Expense " . ($old_expense['expense_number'] ?? 'N/A') . " updated successfully!";
        $alert_type = 'success';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $alert_message = "Error updating expense: " . $e->getMessage();
        $alert_type = 'error';
    }
}

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
        br.name as branch_name
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

// RECORDED BY
$recorder_name = $expense['recorded_by_name'] ?? 'N/A';
$recorder_parts = explode(' ', trim($recorder_name));
$recorder_initials = count($recorder_parts) >= 2 
    ? strtoupper(substr($recorder_parts[0], 0, 1) . substr($recorder_parts[1], 0, 1))
    : strtoupper(substr($recorder_name, 0, 2));

// CATEGORY ICONS
$category_meta = [
    'rent' => ['icon' => 'fa-home', 'label' => 'Rent'],
    'salary' => ['icon' => 'fa-users', 'label' => 'Salary'],
    'utilities' => ['icon' => 'fa-bolt', 'label' => 'Utilities'],
    'supplies' => ['icon' => 'fa-box', 'label' => 'Supplies'],
    'equipment' => ['icon' => 'fa-tools', 'label' => 'Equipment'],
    'maintenance' => ['icon' => 'fa-wrench', 'label' => 'Maintenance'],
    'transport' => ['icon' => 'fa-car', 'label' => 'Transport'],
    'food' => ['icon' => 'fa-utensils', 'label' => 'Food'],
    'marketing' => ['icon' => 'fa-bullhorn', 'label' => 'Marketing'],
    'medicine' => ['icon' => 'fa-pills', 'label' => 'Medicine'],
    'utility' => ['icon' => 'fa-bolt', 'label' => 'Utility'],
    'other' => ['icon' => 'fa-ellipsis-h', 'label' => 'Other'],
];

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
    <title>Edit Expense - <?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?></title>
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

.money-number, .money-cell, .font-mono, .money-input {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ALERT */
.alert {
    padding: 12px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 0.82rem;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert i { font-size: 1.1rem; }

/* PAGE HEADER - RED THEME */
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

/* FORM CARD */
.form-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
}

.form-card.red-theme {
    border-color: var(--expense-primary);
}

.form-card.red-theme:hover {
    border-color: var(--expense-primary);
    box-shadow: 0 10px 30px rgba(220, 38, 38, 0.15);
}

.form-card .card-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.form-card .card-header .title {
    color: white;
    font-size: 0.9rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-card .card-header .title i {
    color: #FCA5A5;
    font-size: 1rem;
}

.form-card .card-header .meta {
    color: rgba(255,255,255,0.9);
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(255,255,255,0.15);
    padding: 3px 10px;
    border-radius: 8px;
}

.form-card .card-body {
    padding: 20px 22px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-group label i {
    color: var(--expense-primary);
    font-size: 0.7rem;
}

.form-group label .required {
    color: var(--expense-primary);
    font-weight: 900;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px 14px;
    border-radius: 9px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 600;
    outline: none;
    transition: all 0.3s ease;
    font-family: var(--font-primary);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--expense-primary);
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
}

.form-group textarea {
    resize: vertical;
    min-height: 70px;
    font-weight: 500;
    line-height: 1.5;
}

/* ✅ MONEY INPUT */
.money-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.money-wrapper .currency-tag {
    position: absolute;
    left: 14px;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--expense-primary);
    pointer-events: none;
    font-family: var(--font-primary);
    z-index: 2;
}

.money-wrapper input {
    padding-left: 52px !important;
    text-align: right;
    font-family: var(--font-mono) !important;
    font-weight: 800;
    letter-spacing: 0.02em;
    font-size: 0.95rem;
    color: var(--expense-primary);
}

/* META INFO */
.meta-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 14px 20px;
    background: var(--expense-bg);
    border-bottom: 2px solid var(--border-color);
}

[data-theme="dark"] .meta-info {
    background: #2A0F0F;
}

.meta-info-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 8px 12px;
    background: var(--bg-card);
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.meta-info-item .mi-label {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
}

.meta-info-item .mi-label i {
    color: var(--expense-primary);
    font-size: 0.62rem;
}

.meta-info-item .mi-value {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--text-primary);
    font-family: var(--font-mono);
    letter-spacing: -0.02em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.meta-info-item .mi-value.name {
    font-family: var(--font-primary);
    font-size: 0.8rem;
}

/* ACTION BAR */
.action-bar {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--expense-primary);
    padding: 16px 20px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
    position: sticky;
    bottom: 20px;
    z-index: 10;
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
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.82rem;
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

.btn-save {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}

.btn-save:hover {
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.6);
}

.btn-back {
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-back:hover {
    border-color: var(--expense-primary);
    color: var(--expense-primary);
}

.btn-view {
    background: var(--bg-card);
    color: var(--expense-primary);
    border: 2px solid var(--expense-primary);
}

.btn-view:hover {
    background: var(--expense-primary);
    color: white;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .meta-info { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .meta-info { grid-template-columns: 1fr; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .form-card .card-body { padding: 16px 18px; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- ALERT -->
    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Expense
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($expense['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($expense['created_at'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_expense.php?id=<?= $expense_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- MAIN FORM -->
    <form method="POST" id="editExpenseForm">
        <input type="hidden" name="action" value="update_expense">

        <!-- ✅ EXPENSE INFO CARD -->
        <div class="form-card red-theme">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Expense Information
                </span>
                <span class="meta">
                    <i class="fas fa-clock"></i>
                    Created: <?= date('d M Y, H:i', strtotime($expense['created_at'])) ?>
                </span>
            </div>
            
            <!-- META INFO -->
            <div class="meta-info">
                <div class="meta-info-item">
                    <span class="mi-label"><i class="fas fa-user-check"></i> Recorded By</span>
                    <span class="mi-value name"><?= htmlspecialchars($recorder_name) ?></span>
                </div>
                <div class="meta-info-item">
                    <span class="mi-label"><i class="fas fa-user-tag"></i> Role</span>
                    <span class="mi-value" style="text-transform:uppercase;">
                        <?= htmlspecialchars($expense['recorded_by_role'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="meta-info-item">
                    <span class="mi-label"><i class="fas fa-hashtag"></i> Expense ID</span>
                    <span class="mi-value">#<?= $expense['id'] ?></span>
                </div>
            </div>
            
            <div class="card-body">
                <div class="form-grid">
                    
                    <!-- CATEGORY -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-tag"></i> 
                            Category <span class="required">*</span>
                        </label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($category_meta as $key => $cat): ?>
                                <option value="<?= ucfirst($key) ?>" 
                                    <?= strtolower($expense['category'] ?? '') === $key ? 'selected' : '' ?>>
                                    <?= $cat['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- AMOUNT (MONEY FORMAT) -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-money-bill-wave"></i> 
                            Amount <span class="required">*</span>
                        </label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" 
                                   name="amount" 
                                   id="amountInput"
                                   class="money-input"
                                   value="<?= number_format((float)($expense['amount'] ?? 0), 0) ?>" 
                                   inputmode="numeric"
                                   autocomplete="off"
                                   required>
                        </div>
                    </div>
                    
                    <!-- PAYMENT METHOD -->
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Payment Method</label>
                        <select name="payment_method">
                            <option value="cash" <?= ($expense['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="m-pesa" <?= ($expense['payment_method'] ?? '') === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                            <option value="airtel_money" <?= ($expense['payment_method'] ?? '') === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                            <option value="tigo_pesa" <?= ($expense['payment_method'] ?? '') === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                            <option value="halopesa" <?= ($expense['payment_method'] ?? '') === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                            <option value="bank" <?= ($expense['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank</option>
                            <option value="card" <?= ($expense['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
                            <option value="other" <?= ($expense['payment_method'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <!-- PAYMENT DATE -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> Payment Date</label>
                        <input type="date" name="payment_date" 
                               value="<?= htmlspecialchars($expense['payment_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    
                    <!-- STATUS -->
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Status</label>
                        <select name="status">
                            <option value="paid" <?= ($expense['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="pending" <?= ($expense['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="cancelled" <?= ($expense['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- RECEIPT NUMBER -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-receipt"></i> Receipt Number</label>
                        <input type="text" name="receipt_number" 
                               value="<?= htmlspecialchars($expense['receipt_number'] ?? '') ?>" 
                               placeholder="e.g. RCP-20260917-0001">
                    </div>
                    
                    <!-- DESCRIPTION -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" 
                                  rows="3" 
                                  placeholder="Maelezo ya expense..."><?= htmlspecialchars($expense['description'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- NOTES -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                        <textarea name="notes" 
                                  rows="2" 
                                  placeholder="Notes za ziada (optional)..."><?= htmlspecialchars($expense['notes'] ?? '') ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- ✅ ACTION BAR -->
        <div class="action-bar">
            <div class="action-info">
                <div class="info-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="info-text">
                    <div class="info-title">Save Changes</div>
                    <div class="info-sub">Total changes will be logged in audit trail</div>
                </div>
            </div>
            <div class="action-buttons">
                <a href="view_expense.php?id=<?= $expense_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn btn-back">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>

    </form>

</main>

<script>
// ================================================================
// MONEY FORMATTING
// ================================================================
function formatMoney(value) {
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    var parts = cleaned.split('.');
    var integerPart = parts[0];
    var decimalPart = parts.length > 1 ? parts[1] : '';
    
    integerPart = integerPart.replace(/^0+/, '') || '0';
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    
    if (decimalPart) {
        return integerPart + '.' + decimalPart;
    }
    return integerPart;
}

function attachMoneyFormat(input) {
    if (!input || input.dataset.moneyAttached === '1') return;
    input.dataset.moneyAttached = '1';
    
    if (input.value && input.value !== '0') {
        input.value = formatMoney(input.value);
    }
    
    input.addEventListener('input', function(e) {
        var cursorPos = this.selectionStart;
        var oldValue = this.value;
        var oldLength = oldValue.length;
        
        var formatted = formatMoney(this.value);
        this.value = formatted;
        
        var newLength = formatted.length;
        var diff = newLength - oldLength;
        var newPos = cursorPos + diff;
        
        if (this.setSelectionRange) {
            this.setSelectionRange(newPos, newPos);
        }
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '' || this.value === '.') {
            this.value = '0';
        } else {
            this.value = formatMoney(this.value);
        }
    });
    
    input.addEventListener('focus', function() {
        var self = this;
        setTimeout(function() { self.select(); }, 10);
    });
    
    input.addEventListener('keypress', function(e) {
        var char = String.fromCharCode(e.which);
        if (!/[0-9.]/.test(char)) {
            e.preventDefault();
        }
    });
    
    input.addEventListener('paste', function(e) {
        var paste = (e.clipboardData || window.clipboardData).getData('text');
        if (!/^[0-9,.]+$/.test(paste)) {
            e.preventDefault();
        }
    });
}

// ================================================================
// SUBMIT VALIDATION
// ================================================================
document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
    var amount = document.getElementById('amountInput').value;
    var cleanAmount = amount.replace(/[^0-9.]/g, '');
    
    if (!cleanAmount || parseFloat(cleanAmount) <= 0) {
        e.preventDefault();
        alert('Tafadhali weka amount sahihi (zaidi ya 0)!');
        return false;
    }
    
    if (!confirm('Are you sure you want to save these changes?')) {
        e.preventDefault();
        return false;
    }
});

// ================================================================
// INITIALIZE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
});

console.log('%c✏️ Edit Expense - Audit (RED THEME)', 'font-size:18px; font-weight:bold; color:#DC2626;');
console.log('%c✅ Expense #<?= htmlspecialchars($expense['expense_number'] ?? 'N/A') ?>', 'font-size:13px; color:#DC2626;');
console.log('%c💰 Amount: <?= $currency ?> <?= number_format($expense['amount'] ?? 0, 0) ?>', 'font-size:13px; color:#DC2626; font-weight:bold;');
console.log('%c✅ MONEY FORMAT: Live commas', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>