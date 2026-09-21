<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_bill_item.php
// ADMIN AUDIT - EDIT BILL ITEM (V3 - Live Comma Format Inputs)
// ✅ Edit item details (name, code, description, qty, prices, discount)
// ✅ Auto recalculate bill totals after save
// ✅ Validation & error handling
// ✅ Activity logging
// ✅ Live comma formatting in money inputs (1,000,000,000)
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

if ($_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($item_id <= 0) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
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
// HELPER: Full money format with commas (1,000,000,000)
// ================================================================
function money($amount, $currency = '') {
    $formatted = number_format((float)$amount, 0, '.', ',');
    return $currency ? $currency . ' ' . $formatted : $formatted;
}

// Helper: Parse money from POST (remove commas)
function parseMoney($value) {
    if ($value === null || $value === '') return 0;
    $clean = str_replace([',', ' '], '', $value);
    return (float)$clean;
}

// ================================================================
// FETCH BILL ITEM
// ================================================================
$item = null;
try {
    $sql = "SELECT 
                bi.*,
                b.bill_number,
                b.visit_id,
                b.patient_id as bill_patient_id,
                b.total_amount as bill_total,
                b.paid_amount as bill_paid,
                b.balance as bill_balance,
                b.status as bill_status,
                b.payment_method as bill_payment_method,
                b.total_discount as bill_discount,
                b.premium_amount as bill_premium,
                b.pharmacy_discount,
                b.cashier_discount,
                b.pharmacy_premium,
                b.cashier_premium,
                b.created_at as bill_created_at,
                pat.patient_id as patient_code,
                pat.full_name as patient_name,
                pat.phone as patient_phone,
                v.visit_number,
                v.visit_date,
                u.full_name as bill_created_by_name,
                u.role as bill_created_by_role
            FROM bill_items bi
            LEFT JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON b.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users u ON b.created_by = u.id
            WHERE bi.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching bill item: " . $e->getMessage());
}

if (!$item) {
    die("Bill item not found.");
}

// ================================================================
// HANDLE UPDATE
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_item') {
    try {
        $item_name = trim($_POST['item_name'] ?? '');
        $item_code = trim($_POST['item_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $unit_price = max(0, parseMoney($_POST['unit_price'] ?? 0));
        $discount_amount = max(0, parseMoney($_POST['discount_amount'] ?? 0));
        $tax_amount = max(0, parseMoney($_POST['tax_amount'] ?? 0));
        $item_type = $_POST['item_type'] ?? $item['item_type'];
        $status = $_POST['status'] ?? $item['status'];

        // Validation
        if (empty($item_name)) {
            throw new Exception("Item name is required.");
        }

        $allowed_types = ['registration', 'consultation', 'lab_test', 'medication', 'procedure', 'equipment', 'tool', 'other'];
        if (!in_array($item_type, $allowed_types)) {
            $item_type = 'other';
        }

        $allowed_status = ['pending', 'paid', 'cancelled', 'refunded'];
        if (!in_array($status, $allowed_status)) {
            $status = 'pending';
        }

        // Calculate total_price
        $total_price = $quantity * $unit_price;
        $final_price = $total_price - $discount_amount + $tax_amount;

        // Update bill item
        $sql = "UPDATE bill_items SET 
                    item_name = ?, 
                    item_code = ?, 
                    description = ?, 
                    quantity = ?, 
                    unit_price = ?, 
                    total_price = ?, 
                    discount_amount = ?, 
                    tax_amount = ?, 
                    final_price = ?, 
                    item_type = ?, 
                    status = ?, 
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $item_name,
            $item_code ?: null,
            $description ?: null,
            $quantity,
            $unit_price,
            $total_price,
            $discount_amount,
            $tax_amount,
            $final_price,
            $item_type,
            $status,
            $item_id
        ]);

        // ============================================================
        // RECALCULATE BILL TOTALS
        // ============================================================
        $bill_id = (int)$item['bill_id'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) as subtotal, 
                                     COALESCE(SUM(discount_amount), 0) as items_discount,
                                     COALESCE(SUM(tax_amount), 0) as items_tax
                              FROM bill_items 
                              WHERE bill_id = ? AND status != 'cancelled'");
        $stmt->execute([$bill_id]);
        $calc = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_subtotal = (float)$calc['subtotal'];
        $items_discount = (float)$calc['items_discount'];
        $items_tax = (float)$calc['items_tax'];

        $stmt = $db->prepare("SELECT pharmacy_discount, cashier_discount, pharmacy_premium, cashier_premium FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $pharm_disc = (float)($bill_row['pharmacy_discount'] ?? 0);
        $cash_disc = (float)($bill_row['cashier_discount'] ?? 0);
        $pharm_prem = (float)($bill_row['pharmacy_premium'] ?? 0);
        $cash_prem = (float)($bill_row['cashier_premium'] ?? 0);

        $total_discount = $items_discount + $pharm_disc + $cash_disc;
        $premium_amount = $pharm_prem + $cash_prem;

        $new_total = $new_subtotal - $total_discount + $premium_amount + $items_tax;
        $new_total = max(0, $new_total);

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $new_paid = (float)$stmt->fetch(PDO::FETCH_ASSOC)['paid'];

        $new_balance = max(0, $new_total - $new_paid);

        $new_status = 'pending';
        if ($new_balance <= 0 && $new_total > 0 && $new_paid > 0) $new_status = 'paid';
        elseif ($new_paid > 0 && $new_balance > 0) $new_status = 'partial';
        elseif ($new_total == 0 && $new_paid == 0) $new_status = 'pending';

        $sql = "UPDATE bills SET 
                    subtotal = ?, 
                    total_discount = ?, 
                    premium_amount = ?, 
                    total_amount = ?, 
                    paid_amount = ?, 
                    balance = ?, 
                    status = ?, 
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $new_subtotal,
            $total_discount,
            $premium_amount,
            $new_total,
            $new_paid,
            $new_balance,
            $new_status,
            $bill_id
        ]);

        try {
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'edit_bill_item', ?, ?, NOW())")
               ->execute([
                   $user_id, 
                   $user_branch_id, 
                   "Edited bill item #{$item_id}: {$item_name} (Bill: {$item['bill_number']})", 
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}

        $alert_message = "Bill item updated successfully! Bill totals recalculated.";
        $alert_type = 'success';

        // Refresh item data
        $stmt = $db->prepare("SELECT 
                                bi.*,
                                b.bill_number,
                                b.visit_id,
                                b.patient_id as bill_patient_id,
                                b.total_amount as bill_total,
                                b.paid_amount as bill_paid,
                                b.balance as bill_balance,
                                b.status as bill_status,
                                b.payment_method as bill_payment_method,
                                b.total_discount as bill_discount,
                                b.premium_amount as bill_premium,
                                b.pharmacy_discount,
                                b.cashier_discount,
                                b.pharmacy_premium,
                                b.cashier_premium,
                                b.created_at as bill_created_at,
                                pat.patient_id as patient_code,
                                pat.full_name as patient_name,
                                pat.phone as patient_phone,
                                v.visit_number,
                                v.visit_date,
                                u.full_name as bill_created_by_name,
                                u.role as bill_created_by_role
                            FROM bill_items bi
                            LEFT JOIN bills b ON bi.bill_id = b.id
                            LEFT JOIN patients pat ON b.patient_id = pat.id
                            LEFT JOIN visits v ON b.visit_id = v.id
                            LEFT JOIN users u ON b.created_by = u.id
                            WHERE bi.id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $_POST = [];

    } catch (Exception $e) {
        $alert_message = "Error: " . $e->getMessage();
        $alert_type = 'error';
    }
}

// Current values (from POST if error, else from DB)
$val = function($key, $default = '') use ($item) {
    if (isset($_POST[$key]) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_item') {
        return $_POST[$key];
    }
    return $item[$key] ?? $default;
};

// Helper for money input value formatting (raw number, no commas for parsing)
$valMoney = function($key, $default = 0) use ($item) {
    if (isset($_POST[$key]) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_item') {
        return parseMoney($_POST[$key]);
    }
    return (float)($item[$key] ?? $default);
};

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Bill Item • <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></title>
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
    --primary-light: #3B82F6;
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
    --teal-bg: #CCFBF1;
    --pink: #DB2777;
    --pink-bg: #FCE7F3;
    --indigo: #6366F1;
    --indigo-bg: #E0E7FF;
    --slate: #94A3B8;
    --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --border-strong: #CBD5E1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.05);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15), 0 10px 20px rgba(0,0,0,0.08);
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
    --teal-bg: #0A2E2A;
    --pink-bg: #3A1A2A;
    --indigo-bg: #1E1B4B;
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

.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.85rem; animation: slideDown 0.4s ease; border-left: 4px solid; box-shadow: var(--shadow-sm); }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left-color: var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left-color: var(--danger); }

/* ================================================================ */
/* PAGE HEADER */
/* ================================================================ */
.page-header { 
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 50%, #DC2626 100%); 
    border-radius: var(--radius-lg); 
    padding: 24px 28px; 
    margin-bottom: 20px; 
    display: flex; 
    flex-wrap: wrap; 
    justify-content: space-between; 
    align-items: center; 
    gap: 16px; 
    box-shadow: 0 10px 40px rgba(245, 158, 11, 0.35); 
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
.page-header .page-title i { font-size: 1.6rem; color: #FEF3C7; }
.page-header .page-subtitle { 
    color: rgba(255,255,255,0.95); 
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
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

.btn-header { 
    background: rgba(255,255,255,0.2); 
    color: white; 
    border: 1px solid rgba(255,255,255,0.3); 
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
.btn-header:hover { background: rgba(255,255,255,0.35); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

/* ================================================================ */
/* CARDS */
/* ================================================================ */
.card { 
    background: var(--bg-card); 
    border-radius: var(--radius-lg); 
    border: 1px solid var(--border-color); 
    overflow: hidden; 
    box-shadow: var(--shadow-sm); 
    margin-bottom: 20px; 
}
.card-header { 
    padding: 14px 20px; 
    background: linear-gradient(135deg, #F59E0B, #D97706); 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    flex-wrap: wrap; 
    gap: 10px; 
}
.card-header.green { background: linear-gradient(135deg, #059669, #047857); }
.card-header.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.card-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.card-header.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
.card-header.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.card-header.dark { background: linear-gradient(135deg, #1E293B, #334155); }
.card-header .title { 
    color: white; 
    font-size: 0.88rem; 
    font-weight: 800; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}
.card-header .title i { color: #FEF3C7; }
.card-header .count { 
    color: rgba(255,255,255,0.95); 
    font-size: 0.7rem; 
    font-weight: 700; 
    background: rgba(255,255,255,0.18); 
    padding: 5px 12px; 
    border-radius: var(--radius-full); 
    backdrop-filter: blur(10px); 
}
.card-body { padding: 20px; }

/* ================================================================ */
/* FORM */
/* ================================================================ */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.form-grid.full { grid-template-columns: 1fr; }
.form-grid.three { grid-template-columns: repeat(3, 1fr); }
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-group.full-width { grid-column: 1 / -1; }
.form-label {
    font-size: 0.68rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 5px;
}
.form-label i { color: var(--primary); font-size: 0.72rem; }
.form-label .required { color: var(--danger); font-size: 0.8rem; }
.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 11px 14px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 600;
    outline: none;
    font-family: var(--font-primary);
    transition: all 0.2s;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    border-color: var(--warning);
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
}
.form-input.mono { 
    font-family: var(--font-mono); 
    font-weight: 700; 
    letter-spacing: -0.02em; 
    text-align: right;
}
.form-textarea {
    min-height: 90px;
    resize: vertical;
    font-family: var(--font-primary);
    line-height: 1.5;
}
.form-help {
    font-size: 0.65rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 2px;
}

/* Input with prefix */
.input-with-prefix {
    position: relative;
    display: flex;
    align-items: center;
}
.input-with-prefix .prefix {
    position: absolute;
    left: 12px;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--text-secondary);
    font-family: var(--font-mono);
    pointer-events: none;
    z-index: 2;
}
.input-with-prefix .form-input {
    padding-left: 55px;
}

/* ================================================================ */
/* CURRENT INFO PANEL */
/* ================================================================ */
.info-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0;
    background: var(--slate-bg);
    border-bottom: 1px solid var(--border-color);
}
.info-strip-item {
    padding: 14px 18px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}
.info-strip-item:last-child { border-right: none; }
.info-strip-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.info-strip-label i { color: var(--primary); font-size: 0.7rem; }
.info-strip-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    word-break: break-word;
}
.info-strip-value.money {
    font-family: var(--font-mono);
    font-weight: 900;
    letter-spacing: -0.02em;
}

/* ================================================================ */
/* FORM ACTIONS */
/* ================================================================ */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    padding: 20px 24px;
    background: linear-gradient(135deg, var(--warning-bg), transparent);
    border-top: 2px solid var(--warning);
}
.btn {
    padding: 12px 24px;
    border-radius: var(--radius-md);
    font-weight: 800;
    font-size: 0.82rem;
    border: none;
    cursor: pointer;
    transition: all 0.25s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    white-space: nowrap;
}
.btn-primary {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(245, 158, 11, 0.5);
}
.btn-secondary {
    background: var(--border-color);
    color: var(--text-primary);
}
.btn-secondary:hover {
    background: var(--border-strong);
    transform: translateY(-2px);
}

/* ================================================================ */
/* LIVE TOTAL PREVIEW */
/* ================================================================ */
.total-preview {
    background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    margin-top: 18px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    border: 1.5px dashed var(--primary);
}
.total-preview-item {
    text-align: center;
}
.total-preview-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    margin-bottom: 4px;
}
.total-preview-value {
    font-family: var(--font-mono);
    font-size: 1.1rem;
    font-weight: 900;
    color: var(--primary);
    letter-spacing: -0.03em;
}

/* ================================================================ */
/* RECALCULATE WARNING */
/* ================================================================ */
.recalc-notice {
    background: var(--warning-bg);
    border-left: 4px solid var(--warning);
    padding: 14px 18px;
    border-radius: var(--radius-md);
    margin-bottom: 18px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.82rem;
    color: #92400E;
    font-weight: 600;
}
.recalc-notice i {
    font-size: 1.2rem;
    color: var(--warning);
    flex-shrink: 0;
    margin-top: 2px;
}
.recalc-notice strong { font-weight: 900; }

/* ================================================================ */
/* RESPONSIVE */
/* ================================================================ */
@media (max-width: 1024px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid.three { grid-template-columns: 1fr 1fr; }
    .info-strip { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 18px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    .info-strip { grid-template-columns: 1fr; }
    .form-grid.three { grid-template-columns: 1fr; }
    .form-actions { justify-content: stretch; }
    .form-actions .btn { flex: 1; justify-content: center; }
    .total-preview { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .total-preview { grid-template-columns: 1fr; }
}
@media print {
    .btn-header, .form-actions { display: none !important; }
}
</style>
</head>
<body>

<main class="main-content">

    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Bill Item
            </h1>
            <p class="page-subtitle">
                <span class="header-badge"><i class="fas fa-hashtag"></i> Item #<?= $item_id ?></span>
                <span class="header-badge"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?></span>
                <?php if (!empty($item['patient_name'])): ?>
                <span class="header-badge"><i class="fas fa-user"></i> <?= htmlspecialchars($item['patient_name']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_bill_item.php?id=<?= $item_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View Item
            </a>
            <?php if (!empty($item['visit_id'])): ?>
            <a href="view_visit.php?id=<?= $item['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-stethoscope"></i> View Visit
            </a>
            <?php endif; ?>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- RECALCULATE NOTICE -->
    <div class="recalc-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Note:</strong> Saving changes will automatically recalculate the bill totals (subtotal, discounts, total amount, balance, and status) based on all items in the bill.
        </div>
    </div>

    <!-- CURRENT ITEM INFO STRIP -->
    <div class="card">
        <div class="info-strip">
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-tag"></i> Item Type</div>
                <div class="info-strip-value"><?= htmlspecialchars(str_replace('_', ' ', strtoupper($item['item_type'] ?? 'OTHER'))) ?></div>
            </div>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-info-circle"></i> Status</div>
                <div class="info-strip-value"><?= htmlspecialchars(strtoupper($item['status'] ?? 'PENDING')) ?></div>
            </div>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-money-bill-wave"></i> Current Total</div>
                <div class="info-strip-value money"><?= money((float)($item['total_price'] ?? 0), $currency) ?></div>
            </div>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-calculator"></i> Bill Total</div>
                <div class="info-strip-value money" style="color:var(--primary);"><?= money((float)($item['bill_total'] ?? 0), $currency) ?></div>
            </div>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-check-circle"></i> Bill Paid</div>
                <div class="info-strip-value money" style="color:var(--success);"><?= money((float)($item['bill_paid'] ?? 0), $currency) ?></div>
            </div>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-exclamation-circle"></i> Bill Balance</div>
                <div class="info-strip-value money" style="color:<?= (float)($item['bill_balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-secondary)' ?>;"><?= money((float)($item['bill_balance'] ?? 0), $currency) ?></div>
            </div>
        </div>
    </div>

    <!-- EDIT FORM -->
    <form method="POST" id="editForm" autocomplete="off">
        <input type="hidden" name="action" value="update_item">

        <div class="card">
            <div class="card-header">
                <span class="title"><i class="fas fa-edit"></i> Edit Item Details</span>
                <span class="count">#<?= $item_id ?></span>
            </div>
            <div class="card-body">

                <!-- Basic Info -->
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label"><i class="fas fa-tag"></i> Item Name <span class="required">*</span></label>
                        <input type="text" name="item_name" class="form-input" value="<?= htmlspecialchars($val('item_name')) ?>" required maxlength="255" placeholder="Enter item name" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-barcode"></i> Item Code</label>
                        <input type="text" name="item_code" class="form-input mono" value="<?= htmlspecialchars($val('item_code')) ?>" maxlength="50" placeholder="e.g., ITM-001" autocomplete="off" style="text-align:left;">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Item Type</label>
                        <select name="item_type" class="form-select">
                            <?php 
                            $types = [
                                'consultation' => 'Consultation',
                                'registration' => 'Registration',
                                'lab_test' => 'Lab Test',
                                'medication' => 'Medication',
                                'procedure' => 'Procedure',
                                'equipment' => 'Equipment',
                                'tool' => 'Tool',
                                'other' => 'Other'
                            ];
                            $current_type = $val('item_type');
                            foreach ($types as $key => $label):
                            ?>
                                <option value="<?= $key ?>" <?= $current_type === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Optional description / notes about this item"><?= htmlspecialchars($val('description')) ?></textarea>
                    </div>
                </div>

                <!-- Pricing -->
                <div style="margin-top:24px;padding-top:20px;border-top:1.5px dashed var(--border-color);">
                    <div style="font-size:0.75rem;font-weight:800;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-dollar-sign" style="color:var(--warning);"></i> Pricing & Quantity
                    </div>

                    <div class="form-grid three">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-sort-numeric-up"></i> Quantity <span class="required">*</span></label>
                            <input type="text" 
                                   name="quantity" 
                                   id="input_quantity" 
                                   class="form-input mono" 
                                   value="<?= (int)$valMoney('quantity', 1) ?>" 
                                   required 
                                   oninput="formatNumberInput(this); updatePreview();" 
                                   inputmode="numeric" 
                                   autocomplete="off"
                                   placeholder="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-tag"></i> Unit Price <span class="required">*</span></label>
                            <div class="input-with-prefix">
                                <span class="prefix"><?= $currency ?></span>
                                <input type="text" 
                                       name="unit_price" 
                                       id="input_unit_price" 
                                       class="form-input mono" 
                                       value="<?= number_format($valMoney('unit_price', 0), 0, '.', ',') ?>" 
                                       required 
                                       oninput="formatNumberInput(this); updatePreview();" 
                                       inputmode="numeric" 
                                       autocomplete="off"
                                       placeholder="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-percent"></i> Discount Amount</label>
                            <div class="input-with-prefix">
                                <span class="prefix"><?= $currency ?></span>
                                <input type="text" 
                                       name="discount_amount" 
                                       id="input_discount_amount" 
                                       class="form-input mono" 
                                       value="<?= number_format($valMoney('discount_amount', 0), 0, '.', ',') ?>" 
                                       oninput="formatNumberInput(this); updatePreview();" 
                                       inputmode="numeric" 
                                       autocomplete="off"
                                       placeholder="0">
                            </div>
                            <span class="form-help">Amount deducted from total price</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-receipt"></i> Tax Amount</label>
                            <div class="input-with-prefix">
                                <span class="prefix"><?= $currency ?></span>
                                <input type="text" 
                                       name="tax_amount" 
                                       id="input_tax_amount" 
                                       class="form-input mono" 
                                       value="<?= number_format($valMoney('tax_amount', 0), 0, '.', ',') ?>" 
                                       oninput="formatNumberInput(this); updatePreview();" 
                                       inputmode="numeric" 
                                       autocomplete="off"
                                       placeholder="0">
                            </div>
                            <span class="form-help">Additional tax (if any)</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-info-circle"></i> Status</label>
                            <select name="status" class="form-select">
                                <?php 
                                $statuses = [
                                    'pending' => 'Pending',
                                    'paid' => 'Paid',
                                    'cancelled' => 'Cancelled',
                                    'refunded' => 'Refunded'
                                ];
                                $current_status = $val('status');
                                foreach ($statuses as $key => $label):
                                ?>
                                    <option value="<?= $key ?>" <?= $current_status === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Live Preview -->
                <div class="total-preview">
                    <div class="total-preview-item">
                        <div class="total-preview-label"><i class="fas fa-times"></i> Subtotal (Qty × Unit)</div>
                        <div class="total-preview-value" id="preview_subtotal"><?= $currency ?> 0</div>
                    </div>
                    <div class="total-preview-item">
                        <div class="total-preview-label"><i class="fas fa-tag"></i> Discount</div>
                        <div class="total-preview-value" id="preview_discount" style="color:var(--warning);">- <?= $currency ?> 0</div>
                    </div>
                    <div class="total-preview-item">
                        <div class="total-preview-label"><i class="fas fa-receipt"></i> Tax</div>
                        <div class="total-preview-value" id="preview_tax" style="color:var(--purple);">+ <?= $currency ?> 0</div>
                    </div>
                    <div class="total-preview-item" style="background:var(--primary-bg);border-radius:var(--radius-sm);padding:10px;">
                        <div class="total-preview-label"><i class="fas fa-check-circle"></i> Final Price</div>
                        <div class="total-preview-value" id="preview_final" style="color:var(--success);font-size:1.25rem;"><?= $currency ?> 0</div>
                    </div>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="view_bill_item.php?id=<?= $item_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </form>

    <!-- BILL CONTEXT (read-only) -->
    <?php if (!empty($item['bill_number'])): ?>
    <div class="card">
        <div class="card-header blue">
            <span class="title"><i class="fas fa-file-invoice"></i> Bill Context</span>
            <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" style="color:white;font-size:0.7rem;font-weight:700;text-decoration:none;background:rgba(255,255,255,0.2);padding:5px 12px;border-radius:var(--radius-full);backdrop-filter:blur(10px);display:inline-flex;align-items:center;gap:5px;">
                <i class="fas fa-external-link-alt"></i> View Bill
            </a>
        </div>
        <div class="info-strip">
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-hashtag"></i> Bill Number</div>
                <div class="info-strip-value"><?= htmlspecialchars($item['bill_number']) ?></div>
            </div>
            <?php if (!empty($item['visit_number'])): ?>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-stethoscope"></i> Visit Number</div>
                <div class="info-strip-value"><?= htmlspecialchars($item['visit_number']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['patient_name'])): ?>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-user"></i> Patient</div>
                <div class="info-strip-value"><?= htmlspecialchars($item['patient_name']) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-info-circle"></i> Bill Status</div>
                <div class="info-strip-value"><?= htmlspecialchars(strtoupper($item['bill_status'] ?? 'PENDING')) ?></div>
            </div>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-credit-card"></i> Payment Method</div>
                <div class="info-strip-value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['bill_payment_method'] ?? 'Cash'))) ?></div>
            </div>
            <?php if (!empty($item['bill_created_by_name'])): ?>
            <div class="info-strip-item">
                <div class="info-strip-label"><i class="fas fa-user-tie"></i> Created By</div>
                <div class="info-strip-value">
                    <?= htmlspecialchars($item['bill_created_by_name']) ?>
                    <?php if (!empty($item['bill_created_by_role'])): ?>
                        <span style="font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:2px 8px;border-radius:4px;font-weight:800;margin-left:5px;"><?= htmlspecialchars(strtoupper($item['bill_created_by_role'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
const CURRENCY = '<?= $currency ?>';

// ================================================================
// ✅ Format number input with live commas (1,000,000)
// ================================================================
function formatNumberInput(input) {
    // Save cursor position
    let cursorPos = input.selectionStart;
    let oldVal = input.value;
    let oldLen = oldVal.length;
    
    // Remove everything except digits
    let rawValue = oldVal.replace(/[^\d]/g, '');
    
    // If empty, clear and exit
    if (rawValue === '') {
        input.value = '';
        return;
    }
    
    // Remove leading zeros (but keep single 0)
    rawValue = rawValue.replace(/^0+(?=\d)/, '');
    
    // If it's just "0", keep as "0"
    if (rawValue === '') rawValue = '0';
    
    // Format with commas every 3 digits
    let formatted = rawValue.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    
    // Set value
    input.value = formatted;
    
    // Adjust cursor position
    let newLen = formatted.length;
    let diff = newLen - oldLen;
    let newPos = cursorPos + diff;
    if (newPos < 0) newPos = 0;
    if (newPos > newLen) newPos = newLen;
    input.setSelectionRange(newPos, newPos);
}

// ================================================================
// ✅ Parse number from input (strip commas)
// ================================================================
function parseNumber(input) {
    if (!input) return 0;
    let val = typeof input === 'string' ? input : input.value;
    val = val.replace(/,/g, '');
    return parseFloat(val) || 0;
}

// ================================================================
// ✅ Format money for display
// ================================================================
function formatMoney(val) {
    const num = Math.round(parseFloat(val) || 0);
    return CURRENCY + ' ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ================================================================
// ✅ Update live preview
// ================================================================
function updatePreview() {
    const qty = parseNumber(document.getElementById('input_quantity'));
    const unit = parseNumber(document.getElementById('input_unit_price'));
    const disc = parseNumber(document.getElementById('input_discount_amount'));
    const tax = parseNumber(document.getElementById('input_tax_amount'));

    const subtotal = qty * unit;
    const finalPrice = subtotal - disc + tax;

    document.getElementById('preview_subtotal').textContent = formatMoney(subtotal);
    document.getElementById('preview_discount').textContent = '- ' + formatMoney(disc);
    document.getElementById('preview_tax').textContent = '+ ' + formatMoney(tax);
    document.getElementById('preview_final').textContent = formatMoney(Math.max(0, finalPrice));
}

// ================================================================
// ✅ Form submission - strip commas before submitting
// ================================================================
document.getElementById('editForm').addEventListener('submit', function(e) {
    const name = document.querySelector('input[name="item_name"]').value.trim();
    const qty = parseNumber(document.getElementById('input_quantity'));
    const unit = parseNumber(document.getElementById('input_unit_price'));

    if (!name) {
        e.preventDefault();
        alert('Item name is required.');
        return;
    }
    if (qty < 1) {
        e.preventDefault();
        alert('Quantity must be at least 1.');
        return;
    }
    if (unit < 0) {
        e.preventDefault();
        alert('Unit price cannot be negative.');
        return;
    }

    // ✅ Strip commas & format before submitting (raw numbers)
    document.querySelector('input[name="quantity"]').value = Math.max(1, Math.round(qty));
    document.querySelector('input[name="unit_price"]').value = unit;
    document.querySelector('input[name="discount_amount"]').value = parseNumber(document.getElementById('input_discount_amount'));
    document.querySelector('input[name="tax_amount"]').value = parseNumber(document.getElementById('input_tax_amount'));

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

// ================================================================
// ✅ Initialize preview on page load
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});

console.log('%c✏️ Edit Bill Item - #<?= $item_id ?>', 'font-size:16px; font-weight:bold; color:#F59E0B;');
console.log('%c📝 Item: <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c💰 Bill: <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c💵 Current Total: <?= money((float)($item['total_price'] ?? 0), $currency) ?>', 'font-size:12px; color:#7C3AED; font-weight:bold;');
console.log('%c🔢 Live comma formatting enabled on money inputs', 'font-size:12px; color:#0891B2; font-weight:bold;');
</script>

</body>
</html>