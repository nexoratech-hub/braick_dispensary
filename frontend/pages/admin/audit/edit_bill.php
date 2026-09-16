<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_bill.php
// ADMIN AUDIT - EDIT BILL (Admin Override)
// ✅ MONEY FORMAT: Live commas (1,000,000)
// ✅ SCROLL BUTTONS: <> kwenye Bill Items table
// ✅ Add/remove items, recalculate totals
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

$bill_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bill') {
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT bill_number, total_amount, paid_amount, status FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $old_bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
        $visit_id = !empty($_POST['visit_id']) ? (int)$_POST['visit_id'] : null;
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $status = $_POST['status'] ?? 'pending';
        $notes = trim($_POST['notes'] ?? '');
        
        $discount_amount = parseMoney($_POST['discount_amount'] ?? '0');
        $premium_amount = parseMoney($_POST['premium_amount'] ?? '0');
        $premium_note = trim($_POST['premium_note'] ?? '');
        
        $item_ids = $_POST['item_id'] ?? [];
        $item_names = $_POST['item_name'] ?? [];
        $item_types = $_POST['item_type'] ?? [];
        $item_quantities = $_POST['item_quantity'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];
        $item_discounts = $_POST['item_discount'] ?? [];
        $item_statuses = $_POST['item_status'] ?? [];
        
        $new_subtotal = 0;
        $new_total_discount = 0;
        
        foreach ($item_names as $index => $name) {
            if (empty(trim($name))) continue;
            
            $item_id = !empty($item_ids[$index]) ? (int)$item_ids[$index] : null;
            $item_type = $item_types[$index] ?? 'other';
            $qty = (int)($item_quantities[$index] ?? 1);
            $price = parseMoney($item_prices[$index] ?? '0');
            $disc = parseMoney($item_discounts[$index] ?? '0');
            $item_status = $item_statuses[$index] ?? 'pending';
            $total_price = $qty * $price;
            $final_price = $total_price - $disc;
            
            $new_subtotal += $total_price;
            $new_total_discount += $disc;
            
            if ($item_id) {
                $sql = "UPDATE bill_items SET 
                    item_name = ?, item_type = ?, quantity = ?, 
                    unit_price = ?, total_price = ?, discount_amount = ?, 
                    final_price = ?, status = ?, updated_at = NOW()
                    WHERE id = ? AND bill_id = ?";
                $db->prepare($sql)->execute([
                    $name, $item_type, $qty, $price, $total_price, 
                    $disc, $final_price, $item_status, $item_id, $bill_id
                ]);
            } else {
                $sql = "INSERT INTO bill_items 
                    (bill_id, patient_id, branch_id, item_type, item_name, 
                     quantity, unit_price, total_price, discount_amount, 
                     final_price, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $db->prepare($sql)->execute([
                    $bill_id, $patient_id, 
                    $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                    $item_type, $name, $qty, $price, $total_price, 
                    $disc, $final_price, $item_status
                ]);
            }
        }
        
        $deleted_items = $_POST['deleted_items'] ?? '';
        if (!empty($deleted_items)) {
            $del_ids = array_filter(array_map('intval', explode(',', $deleted_items)));
            if (!empty($del_ids)) {
                $placeholders = implode(',', array_fill(0, count($del_ids), '?'));
                $db->prepare("DELETE FROM bill_items WHERE id IN ($placeholders) AND bill_id = ?")
                   ->execute(array_merge($del_ids, [$bill_id]));
            }
        }
        
        $new_total_amount = $new_subtotal - $new_total_discount + $premium_amount;
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $paid = (float)($stmt->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);
        
        $new_balance = $new_total_amount - $paid;
        
        if ($new_balance <= 0 && $new_total_amount > 0) {
            if ($status !== 'cancelled') $status = 'paid';
        } elseif ($paid > 0 && $new_balance > 0) {
            if ($status !== 'cancelled') $status = 'partial';
        }
        
        $sql = "UPDATE bills SET 
            patient_id = ?, visit_id = ?, payment_method = ?, status = ?,
            subtotal = ?, discount_amount = ?, total_discount = ?, 
            premium_amount = ?, premium_note = ?, total_amount = ?,
            paid_amount = ?, balance = ?, notes = ?, updated_at = NOW()
            WHERE id = ?";
        $db->prepare($sql)->execute([
            $patient_id, $visit_id, $payment_method, $status,
            $new_subtotal, $discount_amount, $new_total_discount,
            $premium_amount, $premium_note, $new_total_amount,
            $paid, $new_balance, $notes, $bill_id
        ]);
        
        try {
            $changes = [];
            if ($old_bill) {
                if ((float)$old_bill['total_amount'] != $new_total_amount) {
                    $changes[] = "Total: " . $currency . " " . number_format($old_bill['total_amount'], 0) . " → " . $currency . " " . number_format($new_total_amount, 0);
                }
                if ($old_bill['status'] !== $status) {
                    $changes[] = "Status: " . $old_bill['status'] . " → " . $status;
                }
            }
            
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'edit_bill', ?, ?, NOW())")
               ->execute([
                   $user_id,
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                   "Edited bill: " . ($old_bill['bill_number'] ?? 'N/A') . " | " . implode(' | ', $changes),
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $alert_message = "Bill " . ($old_bill['bill_number'] ?? 'N/A') . " updated successfully!";
        $alert_type = 'success';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $alert_message = "Error updating bill: " . $e->getMessage();
        $alert_type = 'error';
    }
}

// ================================================================
// GET BILL DETAILS
// ================================================================
$bill = null;
try {
    $sql = "SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        v.visit_number,
        u.full_name as created_by_name,
        u.role as created_by_role,
        br.name as branch_name
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bill fetch error: " . $e->getMessage());
}

if (!$bill) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

// GET BILL ITEMS
$bill_items = [];
try {
    $sql = "SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// GET PATIENTS
$patients_list = [];
try {
    $sql = "SELECT id, patient_id, full_name, phone FROM patients ORDER BY full_name LIMIT 500";
    $stmt = $db->query($sql);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// GET VISITS
$visits_list = [];
try {
    $sql = "SELECT v.id, v.visit_number, p.full_name as patient_name 
            FROM visits v 
            LEFT JOIN patients p ON v.patient_id = p.id
            ORDER BY v.visit_date DESC LIMIT 500";
    $stmt = $db->query($sql);
    $visits_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// TYPE OPTIONS
$type_options = [
    'registration' => ['icon' => 'fa-id-card', 'label' => 'Registration', 'color' => '#64748B'],
    'consultation' => ['icon' => 'fa-stethoscope', 'label' => 'Consultation', 'color' => '#059669'],
    'lab_test' => ['icon' => 'fa-flask', 'label' => 'Lab Test', 'color' => '#3B82F6'],
    'medication' => ['icon' => 'fa-pills', 'label' => 'Medication', 'color' => '#D97706'],
    'procedure' => ['icon' => 'fa-syringe', 'label' => 'Procedure', 'color' => '#0D9488'],
    'equipment' => ['icon' => 'fa-tools', 'label' => 'Equipment', 'color' => '#7C3AED'],
    'tool' => ['icon' => 'fa-wrench', 'label' => 'Tool', 'color' => '#DB2777'],
    'other' => ['icon' => 'fa-ellipsis-h', 'label' => 'Other', 'color' => '#64748B'],
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
    <title>Edit Bill - <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></title>
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

/* PAGE HEADER */
.page-header {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.25);
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

.page-header .page-title i { font-size: 1.5rem; color: #FDE68A; }

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
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
}

.btn-header {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
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
    background: rgba(255,255,255,0.32);
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

.form-card .card-header {
    padding: 12px 18px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.form-card .card-header .title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-card .card-header .title i {
    color: var(--primary);
    font-size: 0.95rem;
}

.form-card .card-body {
    padding: 18px 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 5px;
}

.form-group label i {
    color: var(--primary);
    font-size: 0.65rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 9px 12px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.82rem;
    font-weight: 600;
    outline: none;
    transition: all 0.3s ease;
    font-family: var(--font-primary);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
    font-weight: 500;
}

.money-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.money-wrapper .currency-tag {
    position: absolute;
    left: 12px;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--text-secondary);
    pointer-events: none;
    font-family: var(--font-primary);
    z-index: 2;
}

.money-wrapper input {
    padding-left: 45px !important;
    text-align: right;
    font-family: var(--font-mono) !important;
    font-weight: 700;
    letter-spacing: 0.02em;
}

/* ITEMS TABLE CARD */
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
    border-radius: 8px;
    padding: 3px;
    border: 1px solid rgba(255,255,255,0.2);
}

.btn-scroll {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: transparent;
    color: white;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    transition: all 0.25s ease;
}

.btn-scroll:hover {
    background: rgba(255,255,255,0.25);
    transform: scale(1.08);
}

.btn-scroll:active {
    transform: scale(0.95);
    background: rgba(255,255,255,0.35);
}

.btn-add-item {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 8px 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-add-item:hover {
    background: rgba(255,255,255,0.35);
    transform: translateY(-2px);
}

/* TABLE WRAPPER WITH SCROLL */
.items-table-wrapper {
    overflow-x: auto;
    overflow-y: visible;
    scroll-behavior: smooth;
    position: relative;
    -webkit-overflow-scrolling: touch;
}

.items-table-wrapper::-webkit-scrollbar {
    height: 8px;
}

.items-table-wrapper::-webkit-scrollbar-track {
    background: var(--border-color);
    border-radius: 10px;
    margin: 0 8px;
}

.items-table-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(90deg, #0B5ED7, #3B82F6);
    border-radius: 10px;
    border: 2px solid var(--border-color);
}

.items-table-wrapper::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(90deg, #0A4CA8, #0B5ED7);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    min-width: 1200px;
}

.items-table thead th {
    text-align: left;
    padding: 10px 12px;
    font-weight: 800;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
}

.items-table tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    background: var(--bg-card);
}

.items-table tbody tr:hover td {
    background: var(--primary-bg);
}

.items-table tbody tr.item-row {
    transition: all 0.3s ease;
}

.items-table tbody tr.item-row.removing {
    opacity: 0.3;
    text-decoration: line-through;
}

.items-table input[type="text"],
.items-table input[type="number"],
.items-table select {
    width: 100%;
    padding: 6px 9px;
    border-radius: 6px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.78rem;
    font-weight: 600;
    outline: none;
    transition: all 0.2s ease;
    font-family: var(--font-primary);
}

.items-table input:focus,
.items-table select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}

.items-table .item-name-input {
    min-width: 200px;
}

.items-table .item-qty-input {
    width: 70px;
    text-align: center;
}

.items-table .item-price-input,
.items-table .item-disc-input {
    width: 140px;
    text-align: right;
    font-family: var(--font-mono) !important;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.items-table .item-total-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    color: var(--primary);
    text-align: right;
    font-size: 0.82rem;
    letter-spacing: -0.02em;
    white-space: nowrap;
}

.btn-remove-item {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: rgba(220, 38, 38, 0.12);
    color: #DC2626;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

.btn-remove-item:hover {
    background: #DC2626;
    color: white;
    transform: scale(1.1);
}

/* SCROLL HINT */
.scroll-hint {
    padding: 6px 16px;
    background: var(--primary-bg);
    border-top: 1px solid var(--border-color);
    text-align: center;
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.scroll-hint i {
    color: var(--primary);
    font-size: 0.7rem;
    animation: hintPulse 2s infinite;
}

@keyframes hintPulse {
    0%, 100% { opacity: 0.6; transform: translateX(0); }
    50% { opacity: 1; transform: translateX(4px); }
}

/* TOTALS */
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
    font-size: 0.9rem;
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

.total-row.balance-row {
    background: var(--danger-bg);
    border-top: 2px solid var(--danger);
}

.total-row.balance-row .label { color: var(--danger); font-weight: 800; }
.total-row.balance-row .value { color: var(--danger); }

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
    background: rgba(245, 158, 11, 0.12);
    color: #D97706;
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

.btn-save {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.btn-save:hover {
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5);
}

.btn-back {
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-back:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .items-table { font-size: 0.72rem; }
    .items-table thead th,
    .items-table tbody td { padding: 6px 8px; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .btn-scroll { width: 28px; height: 28px; font-size: 0.75rem; }
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
                Edit Bill
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-user-injured"></i> <?= htmlspecialchars($bill['patient_name'] ?? 'Walk-in') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- MAIN FORM -->
    <form method="POST" id="editBillForm">
        <input type="hidden" name="action" value="update_bill">
        <input type="hidden" name="deleted_items" id="deletedItems" value="">

        <!-- BILL INFO CARD -->
        <div class="form-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-info-circle"></i> Bill Information</span>
                <span style="font-size:0.68rem;color:var(--text-secondary);font-weight:700;">
                    <i class="fas fa-clock"></i> Created: <?= date('d M Y, H:i', strtotime($bill['created_at'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-injured"></i> Patient</label>
                        <select name="patient_id">
                            <option value="">Walk-in Customer</option>
                            <?php foreach ($patients_list as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $bill['patient_id'] == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['full_name']) ?> 
                                    <?= !empty($p['patient_id']) ? '(' . htmlspecialchars($p['patient_id']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clipboard-check"></i> Visit</label>
                        <select name="visit_id">
                            <option value="">No Visit</option>
                            <?php foreach ($visits_list as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $bill['visit_id'] == $v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['visit_number']) ?> - <?= htmlspecialchars($v['patient_name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Payment Method</label>
                        <select name="payment_method">
                            <option value="cash" <?= $bill['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="m-pesa" <?= $bill['payment_method'] === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                            <option value="airtel_money" <?= $bill['payment_method'] === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                            <option value="tigo_pesa" <?= $bill['payment_method'] === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                            <option value="halopesa" <?= $bill['payment_method'] === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                            <option value="bank" <?= $bill['payment_method'] === 'bank' ? 'selected' : '' ?>>Bank</option>
                            <option value="card" <?= $bill['payment_method'] === 'card' ? 'selected' : '' ?>>Card</option>
                            <option value="insurance" <?= $bill['payment_method'] === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                            <option value="other" <?= $bill['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Status</label>
                        <select name="status">
                            <option value="pending" <?= $bill['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="partial" <?= $bill['status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="paid" <?= $bill['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="cancelled" <?= $bill['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-percent"></i> Discount Amount</label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" 
                                   name="discount_amount" 
                                   id="discountAmount" 
                                   class="money-input"
                                   value="<?= number_format((float)($bill['discount_amount'] ?? 0), 0) ?>" 
                                   inputmode="numeric"
                                   autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Premium Charge</label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" 
                                   name="premium_amount" 
                                   id="premiumAmount" 
                                   class="money-input"
                                   value="<?= number_format((float)($bill['premium_amount'] ?? 0), 0) ?>" 
                                   inputmode="numeric"
                                   autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Premium Note</label>
                        <input type="text" name="premium_note" value="<?= htmlspecialchars($bill['premium_note'] ?? '') ?>" 
                               placeholder="e.g. Premium charge reason...">
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Notes</label>
                        <textarea name="notes" rows="2" placeholder="Additional notes..."><?= htmlspecialchars($bill['notes'] ?? '') ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- ✅ ITEMS TABLE WITH SCROLL BUTTONS -->
        <div class="items-table-card">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-list"></i>
                    Bill Items
                </span>
                <div class="header-actions">
                    <!-- ✅ SCROLL BUTTONS <> -->
                    <div class="scroll-buttons" title="Scroll left / right">
                        <button type="button" class="btn-scroll" onclick="scrollItems('left')" title="Scroll Left">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="btn-scroll" onclick="scrollItems('right')" title="Scroll Right">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button type="button" class="btn-add-item" onclick="addNewItem()">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
            
            <div class="items-table-wrapper" id="itemsWrapper">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="width:130px;">Type</th>
                            <th>Item Name</th>
                            <th style="width:80px;text-align:center;">Qty</th>
                            <th style="width:140px;text-align:right;">Unit Price</th>
                            <th style="width:140px;text-align:right;">Discount</th>
                            <th style="width:130px;text-align:right;">Total</th>
                            <th style="width:110px;text-align:center;">Status</th>
                            <th style="width:50px;text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php if (count($bill_items) > 0): ?>
                            <?php $item_num = 1; foreach ($bill_items as $item): ?>
                                <tr class="item-row" data-item-id="<?= $item['id'] ?>">
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);" class="row-num"><?= $item_num++ ?></td>
                                    <td>
                                        <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                                        <select name="item_type[]">
                                            <?php foreach ($type_options as $tkey => $tmeta): ?>
                                                <option value="<?= $tkey ?>" <?= ($item['item_type'] ?? 'other') === $tkey ? 'selected' : '' ?>>
                                                    <?= $tmeta['label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="item_name[]" class="item-name-input" 
                                               value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" 
                                               placeholder="Item name..." required>
                                    </td>
                                    <td>
                                        <input type="number" name="item_quantity[]" class="item-qty-input" 
                                               value="<?= (int)($item['quantity'] ?? 1) ?>" min="1"
                                               oninput="recalculate()">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="item_price[]" 
                                               class="item-price-input money-input" 
                                               value="<?= number_format((float)($item['unit_price'] ?? 0), 0) ?>" 
                                               inputmode="numeric"
                                               autocomplete="off">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="item_discount[]" 
                                               class="item-disc-input money-input" 
                                               value="<?= number_format((float)($item['discount_amount'] ?? 0), 0) ?>" 
                                               inputmode="numeric"
                                               autocomplete="off">
                                    </td>
                                    <td class="item-total-cell">
                                        <?= $currency ?> <span class="item-total-value"><?= number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 0) ?></span>
                                    </td>
                                    <td>
                                        <select name="item_status[]">
                                            <option value="pending" <?= ($item['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="paid" <?= ($item['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                            <option value="cancelled" <?= ($item['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            <option value="refunded" <?= ($item['status'] ?? '') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                        </select>
                                    </td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ✅ SCROLL HINT -->
            <div class="scroll-hint">
                <i class="fas fa-arrows-alt-h"></i>
                Tumia <strong>‹ ›</strong> buttons au scrollbar kuslide kushoto/kulia
            </div>
        </div>

        <!-- TOTALS -->
        <div class="totals-card">
            <div class="card-header">
                <i class="fas fa-calculator"></i> Bill Summary
            </div>
            <div class="totals-body">
                <div class="total-row">
                    <span class="label"><i class="fas fa-receipt"></i> Subtotal</span>
                    <span class="value blue"><span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="subtotalValue"><?= number_format($bill['subtotal'] ?? 0, 0) ?></span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-tags"></i> Items Discount</span>
                    <span class="value red">- <span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="itemsDiscountValue"><?= number_format($bill['total_discount'] ?? 0, 0) ?></span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-percent"></i> Bill Discount</span>
                    <span class="value red">- <span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="billDiscountValue"><?= number_format($bill['discount_amount'] ?? 0, 0) ?></span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-star"></i> Premium</span>
                    <span class="value purple">+ <span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="premiumValue"><?= number_format($bill['premium_amount'] ?? 0, 0) ?></span></span>
                </div>
                
                <div class="total-row grand">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                    <span class="value"><span style="font-size:0.85rem;"><?= $currency ?></span> <span id="grandTotalValue"><?= number_format($bill['total_amount'] ?? 0, 0) ?></span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-check-circle"></i> Already Paid</span>
                    <span class="value green"><span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span>
                </div>
                
                <div class="total-row balance-row">
                    <span class="label"><i class="fas fa-exclamation-circle"></i> Balance Due</span>
                    <span class="value red"><span style="font-size:0.72rem;"><?= $currency ?></span> <span id="balanceValue"><?= number_format(max(0, ($bill['total_amount'] ?? 0) - ($bill['paid_amount'] ?? 0)), 0) ?></span></span>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
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
                <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-back">
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
// GLOBAL VARIABLES
// ================================================================
var currency = '<?= $currency ?>';
var paidAmount = <?= (float)($bill['paid_amount'] ?? 0) ?>;
var deletedItems = [];

// ================================================================
// ✅ SCROLL ITEMS TABLE (Left / Right)
// ================================================================
function scrollItems(direction) {
    var wrapper = document.getElementById('itemsWrapper');
    if (!wrapper) return;
    
    var scrollAmount = 350; // pixels
    var currentScroll = wrapper.scrollLeft;
    var targetScroll = direction === 'left' 
        ? currentScroll - scrollAmount 
        : currentScroll + scrollAmount;
    
    wrapper.scrollTo({
        left: targetScroll,
        behavior: 'smooth'
    });
    
    // ✅ Visual feedback
    var btns = document.querySelectorAll('.btn-scroll');
    btns.forEach(function(btn) {
        btn.style.transform = 'scale(0.92)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

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

function parseMoney(value) {
    if (typeof value === 'number') return value;
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    return parseFloat(cleaned) || 0;
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
        
        recalculate();
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '' || this.value === '.') {
            this.value = '0';
        } else {
            this.value = formatMoney(this.value);
        }
        recalculate();
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

function initMoneyInputs() {
    document.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
}

function numberFormat(num) {
    num = Math.round(num || 0);
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// ================================================================
// RECALCULATE TOTALS
// ================================================================
function recalculate() {
    var rows = document.querySelectorAll('#itemsTableBody tr.item-row');
    var subtotal = 0;
    var itemsDiscount = 0;
    
    rows.forEach(function(row) {
        if (row.classList.contains('removing') || row.style.display === 'none') return;
        
        var qty = parseFloat(row.querySelector('.item-qty-input').value) || 0;
        var price = parseMoney(row.querySelector('.item-price-input').value);
        var disc = parseMoney(row.querySelector('.item-disc-input').value);
        
        var rowTotal = qty * price;
        var rowFinal = rowTotal - disc;
        
        subtotal += rowTotal;
        itemsDiscount += disc;
        
        row.querySelector('.item-total-value').textContent = numberFormat(rowFinal);
    });
    
    var billDiscount = parseMoney(document.getElementById('discountAmount').value);
    var premium = parseMoney(document.getElementById('premiumAmount').value);
    
    var grandTotal = subtotal - itemsDiscount - billDiscount + premium;
    if (grandTotal < 0) grandTotal = 0;
    
    var balance = grandTotal - paidAmount;
    if (balance < 0) balance = 0;
    
    document.getElementById('subtotalValue').textContent = numberFormat(subtotal);
    document.getElementById('itemsDiscountValue').textContent = numberFormat(itemsDiscount);
    document.getElementById('billDiscountValue').textContent = numberFormat(billDiscount);
    document.getElementById('premiumValue').textContent = numberFormat(premium);
    document.getElementById('grandTotalValue').textContent = numberFormat(grandTotal);
    document.getElementById('balanceValue').textContent = numberFormat(balance);
    
    var num = 1;
    rows.forEach(function(row) {
        if (!row.classList.contains('removing') && row.style.display !== 'none') {
            row.querySelector('.row-num').textContent = num++;
        }
    });
}

// ================================================================
// ADD NEW ITEM
// ================================================================
function addNewItem() {
    var tbody = document.getElementById('itemsTableBody');
    var newRow = document.createElement('tr');
    newRow.className = 'item-row';
    
    var typeOptionsHtml = `
        <?php foreach ($type_options as $tkey => $tmeta): ?>
            <option value="<?= $tkey ?>"><?= $tmeta['label'] ?></option>
        <?php endforeach; ?>
    `;
    
    newRow.innerHTML = `
        <td style="text-align:center;font-weight:700;color:var(--text-secondary);" class="row-num">-</td>
        <td>
            <input type="hidden" name="item_id[]" value="">
            <select name="item_type[]">
                ${typeOptionsHtml}
            </select>
        </td>
        <td>
            <input type="text" name="item_name[]" class="item-name-input" 
                   value="" placeholder="Item name..." required>
        </td>
        <td>
            <input type="number" name="item_quantity[]" class="item-qty-input" 
                   value="1" min="1" oninput="recalculate()">
        </td>
        <td>
            <input type="text" name="item_price[]" class="item-price-input money-input" 
                   value="0" inputmode="numeric" autocomplete="off">
        </td>
        <td>
            <input type="text" name="item_discount[]" class="item-disc-input money-input" 
                   value="0" inputmode="numeric" autocomplete="off">
        </td>
        <td class="item-total-cell">
            ${currency} <span class="item-total-value">0</span>
        </td>
        <td>
            <select name="item_status[]">
                <option value="pending" selected>Pending</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
            </select>
        </td>
        <td style="text-align:center;">
            <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    
    newRow.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
    
    newRow.querySelector('.item-name-input').focus();
    
    // ✅ Auto scroll to new row
    setTimeout(function() {
        newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }, 100);
    
    recalculate();
}

// ================================================================
// REMOVE ITEM
// ================================================================
function removeItem(btn) {
    var row = btn.closest('tr.item-row');
    var itemId = row.querySelector('input[name="item_id[]"]').value;
    var itemName = row.querySelector('.item-name-input').value;
    
    if (itemId) {
        if (!confirm('Are you sure you want to remove "' + itemName + '"?')) {
            return;
        }
        
        deletedItems.push(itemId);
        document.getElementById('deletedItems').value = deletedItems.join(',');
        row.classList.add('removing');
        
        row.querySelectorAll('input, select').forEach(function(el) {
            el.disabled = true;
        });
        
        setTimeout(function() {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.height = '0';
            setTimeout(function() {
                row.style.display = 'none';
                recalculate();
            }, 300);
        }, 100);
    } else {
        row.remove();
        recalculate();
    }
}

// ================================================================
// ✅ KEYBOARD SHORTCUTS (Arrow keys for scroll)
// ================================================================
document.addEventListener('keydown', function(e) {
    // Alt + Left/Right scroll table
    if (e.altKey && e.key === 'ArrowLeft') {
        e.preventDefault();
        scrollItems('left');
    }
    if (e.altKey && e.key === 'ArrowRight') {
        e.preventDefault();
        scrollItems('right');
    }
});

// ================================================================
// SUBMIT VALIDATION
// ================================================================
document.getElementById('editBillForm').addEventListener('submit', function(e) {
    var visibleItems = document.querySelectorAll('#itemsTableBody tr.item-row:not(.removing)');
    var hasValidItem = false;
    
    visibleItems.forEach(function(row) {
        if (row.style.display === 'none') return;
        var name = row.querySelector('.item-name-input').value.trim();
        if (name) hasValidItem = true;
    });
    
    if (!hasValidItem) {
        e.preventDefault();
        alert('Bill must have at least one item!');
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
    initMoneyInputs();
    recalculate();
    
    // ✅ Scroll indicator on load
    var wrapper = document.getElementById('itemsWrapper');
    if (wrapper) {
        // Check if scrollable
        setTimeout(function() {
            if (wrapper.scrollWidth > wrapper.clientWidth) {
                console.log('📜 Items table scrollable - use <> buttons or Alt+←/→');
            }
        }, 500);
    }
});

console.log('%c✏️ Edit Bill - Audit Override', 'font-size:18px; font-weight:bold; color:#F59E0B;');
console.log('%c✅ MONEY FORMAT: Live commas (1,000,000)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ SCROLL BUTTONS: <> kwenye header', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c⌨️ Alt + ← / → kwa keyboard scroll', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>