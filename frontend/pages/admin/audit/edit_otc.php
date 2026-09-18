<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_otc.php
// ADMIN - EDIT OTC SALE (BLUE THEME) - V2
// ✅ Admin only
// ✅ Edit sale + items
// ✅ NO DELETE buttons (edit only)
// ✅ Medications dropdown (all medicines)
// ✅ Add items
// ✅ Live money format
// ✅ Scroll buttons <>
// ✅ BLUE THEME (#0B5ED7)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ✅ ADMIN ONLY
if ($_SESSION['role'] !== 'admin') {
    $otc_id = (int)($_GET['id'] ?? 0);
    $b_id = $_GET['branch'] ?? 'all';
    header("Location: view_otc.php?id=$otc_id&branch=$b_id");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$sale_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($sale_id <= 0) {
    header('Location: inventory.php?branch=' . urlencode($selected_branch_id));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_otc') {
    try {
        $db->beginTransaction();
        
        // GET OLD DATA for audit log
        $stmt = $db->prepare("SELECT sale_number, total_amount, payment_status FROM otc_sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $old_sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // SALE HEADER DATA
        $customer_name = trim($_POST['customer_name'] ?? 'Walk-in Customer');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_status = $_POST['payment_status'] ?? 'paid';
        $discount_amount = parseMoney($_POST['discount_amount'] ?? '0');
        $premium_amount = parseMoney($_POST['premium_amount'] ?? '0');
        $premium_note = trim($_POST['premium_note'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // ITEMS
        $item_ids = $_POST['item_id'] ?? [];
        $item_inventory_ids = $_POST['item_inventory_id'] ?? [];
        $item_names = $_POST['item_name'] ?? [];
        $item_quantities = $_POST['item_quantity'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];
        $item_dosages = $_POST['item_dosage'] ?? [];
        $item_frequencies = $_POST['item_frequency'] ?? [];
        $item_routes = $_POST['item_route'] ?? [];
        $item_instructions = $_POST['item_instructions'] ?? [];
        
        $new_subtotal = 0;
        
        foreach ($item_names as $index => $name) {
            if (empty(trim($name))) continue;
            
            $item_id = !empty($item_ids[$index]) ? (int)$item_ids[$index] : null;
            $inventory_id = !empty($item_inventory_ids[$index]) ? (int)$item_inventory_ids[$index] : null;
            $qty = (int)($item_quantities[$index] ?? 1);
            $price = parseMoney($item_prices[$index] ?? '0');
            $dosage = trim($item_dosages[$index] ?? '');
            $frequency = trim($item_frequencies[$index] ?? '');
            $route = trim($item_routes[$index] ?? '');
            $instructions = trim($item_instructions[$index] ?? '');
            $total_price = $qty * $price;
            
            $new_subtotal += $total_price;
            
            if ($item_id) {
                // UPDATE
                $sql = "UPDATE otc_sale_items SET 
                    inventory_id = ?, item_name = ?, medicine_name = ?, 
                    quantity = ?, unit_price = ?, total_price = ?, 
                    dosage = ?, frequency = ?, route = ?, instructions = ?
                    WHERE id = ? AND sale_id = ?";
                $db->prepare($sql)->execute([
                    $inventory_id, $name, $name, $qty, $price, $total_price,
                    $dosage, $frequency, $route, $instructions,
                    $item_id, $sale_id
                ]);
            } else {
                // INSERT
                $sql = "INSERT INTO otc_sale_items 
                    (sale_id, inventory_id, item_name, medicine_name, quantity, 
                     unit_price, total_price, dosage, frequency, route, 
                     instructions, branch_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $db->prepare($sql)->execute([
                    $sale_id, $inventory_id, $name, $name, $qty, $price, 
                    $total_price, $dosage, $frequency, $route, $instructions,
                    $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1
                ]);
            }
        }
        
        // ✅ DELETE only items removed by user (via X button) - it's ok for editing
        $deleted_items = $_POST['deleted_items'] ?? '';
        if (!empty($deleted_items)) {
            $del_ids = array_filter(array_map('intval', explode(',', $deleted_items)));
            if (!empty($del_ids)) {
                $placeholders = implode(',', array_fill(0, count($del_ids), '?'));
                $db->prepare("DELETE FROM otc_sale_items WHERE id IN ($placeholders) AND sale_id = ?")
                   ->execute(array_merge($del_ids, [$sale_id]));
            }
        }
        
        // CALCULATE NEW TOTAL
        $new_total = $new_subtotal - $discount_amount + $premium_amount;
        if ($new_total < 0) $new_total = 0;
        
        // UPDATE SALE
        $sql = "UPDATE otc_sales SET 
            customer_name = ?, customer_phone = ?, payment_method = ?, 
            payment_status = ?, subtotal = ?, discount_amount = ?, 
            premium_amount = ?, premium_note = ?, total_amount = ?, 
            notes = ?, updated_at = NOW()
            WHERE id = ?";
        $db->prepare($sql)->execute([
            $customer_name, $customer_phone, $payment_method, 
            $payment_status, $new_subtotal, $discount_amount,
            $premium_amount, $premium_note, $new_total,
            $notes, $sale_id
        ]);
        
        // AUDIT LOG
        try {
            $changes = [];
            if ($old_sale) {
                if ((float)$old_sale['total_amount'] != $new_total) {
                    $changes[] = "Total: " . $currency . " " . number_format($old_sale['total_amount'], 0) . " → " . $currency . " " . number_format($new_total, 0);
                }
                if ($old_sale['payment_status'] !== $payment_status) {
                    $changes[] = "Status: " . $old_sale['payment_status'] . " → " . $payment_status;
                }
            }
            
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'edit_otc_sale', ?, ?, NOW())")
               ->execute([
                   $user_id,
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                   "Edited OTC sale: " . ($old_sale['sale_number'] ?? 'N/A') . " | " . implode(' | ', $changes),
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $alert_message = "OTC Sale updated successfully!";
        $alert_type = 'success';
        
        header("refresh:2;url=view_otc.php?id=$sale_id&branch=" . urlencode($selected_branch_id));
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $alert_message = "Error updating sale: " . $e->getMessage();
        $alert_type = 'error';
    }
}

// ================================================================
// GET SALE DETAILS
// ================================================================
$sale = null;
try {
    $sql = "SELECT 
        os.*,
        COALESCE(u.full_name, 'N/A') as sold_by_name,
        COALESCE(u.role, 'user') as sold_by_role,
        COALESCE(b.name, 'N/A') as branch_name
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches b ON os.branch_id = b.id
    WHERE os.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Sale fetch error: " . $e->getMessage());
}

if (!$sale) {
    header('Location: inventory.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// GET SALE ITEMS
// ================================================================
$sale_items = [];
try {
    $sql = "SELECT 
        osi.*,
        COALESCE(osi.item_name, osi.medicine_name, '') as display_name
    FROM otc_sale_items osi
    WHERE osi.sale_id = ?
    ORDER BY osi.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Items error: " . $e->getMessage());
}

// ================================================================
// GET MEDICATIONS (all)
// ================================================================
$medications_list = [];
try {
    $sql = "SELECT 
        MIN(id) as id,
        medication_name,
        category,
        unit,
        MIN(selling_price) as selling_price,
        SUM(CASE WHEN status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') THEN quantity ELSE 0 END) as total_quantity
    FROM medications_inventory 
    WHERE status = 'active'
    GROUP BY medication_name, category, unit
    ORDER BY medication_name ASC
    LIMIT 1000";
    $stmt = $db->query($sql);
    $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Medications error: " . $e->getMessage());
}

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
    <title>Edit OTC Sale - <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></title>
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
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
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
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .money-cell, .font-mono, .money-input { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; letter-spacing: -0.02em; }

.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.admin-tag { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; }

.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

.form-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.form-card .card-header { padding: 12px 18px; background: var(--primary-bg); border-bottom: 2px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.form-card .card-header .title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.form-card .card-header .title i { color: var(--primary); font-size: 0.95rem; }
.form-card .card-body { padding: 18px 20px; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; }
.form-group label i { color: var(--primary); font-size: 0.65rem; }
.form-group input, .form-group select, .form-group textarea { padding: 9px 12px; border-radius: 8px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.82rem; font-weight: 600; outline: none; transition: all 0.3s ease; font-family: var(--font-primary); }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }
.form-group textarea { resize: vertical; min-height: 60px; font-weight: 500; }

.money-wrapper { position: relative; display: flex; align-items: center; }
.money-wrapper .currency-tag { position: absolute; left: 12px; font-size: 0.72rem; font-weight: 800; color: var(--primary); pointer-events: none; font-family: var(--font-primary); z-index: 2; }
.money-wrapper input { padding-left: 45px !important; text-align: right; font-family: var(--font-mono) !important; font-weight: 700; letter-spacing: 0.02em; color: var(--primary); }

.items-table-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.items-table-card .card-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.items-table-card .card-header .title { color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.items-table-card .card-header .title i { color: #93C5FD; font-size: 1rem; }
.items-table-card .card-header .header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.scroll-buttons { display: inline-flex; gap: 4px; background: rgba(255,255,255,0.15); border-radius: 10px; padding: 4px; border: 1px solid rgba(255,255,255,0.25); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-scroll { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.15); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; transition: all 0.25s ease; }
.btn-scroll:hover { background: rgba(255,255,255,0.35); transform: scale(1.1); border-color: rgba(255,255,255,0.5); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.btn-scroll:active { transform: scale(0.92); }

.btn-add-item { background: linear-gradient(135deg, #10B981, #059669); color: white; border: 2px solid rgba(255,255,255,0.3); padding: 8px 16px; border-radius: 10px; font-weight: 800; font-size: 0.75rem; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); text-transform: uppercase; letter-spacing: 0.03em; }
.btn-add-item:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6); }

.items-table-wrapper { overflow-x: auto; scroll-behavior: smooth; position: relative; }
.items-table-wrapper::-webkit-scrollbar { height: 10px; }
.items-table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; margin: 0 8px; }
.items-table-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(90deg, #0B5ED7, #3B82F6); border-radius: 10px; border: 2px solid var(--border-color); }

.items-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 1600px; }
.items-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; position: sticky; top: 0; z-index: 2; }
.items-table tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; background: var(--bg-card); }
.items-table tbody tr:hover td { background: var(--primary-bg); }
.items-table tbody tr.item-row { transition: all 0.3s ease; }
.items-table tbody tr.item-row.removing { opacity: 0.3; text-decoration: line-through; }

.items-table input[type="text"], .items-table input[type="number"], .items-table select { width: 100%; padding: 6px 9px; border-radius: 6px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.78rem; font-weight: 600; outline: none; transition: all 0.2s ease; font-family: var(--font-primary); }
.items-table input:focus, .items-table select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }

.items-table .item-medication-select { min-width: 220px; background: var(--primary-bg) !important; color: var(--primary) !important; font-weight: 700 !important; border-color: var(--primary-light) !important; }
.items-table .item-medication-select:focus { background: var(--bg-card) !important; color: var(--text-primary) !important; }
.items-table .item-qty-input { width: 70px; text-align: center; }
.items-table .item-price-input { width: 120px; text-align: right; font-family: var(--font-mono) !important; font-weight: 700; letter-spacing: 0.02em; }
.items-table .item-total-cell { font-family: var(--font-mono); font-weight: 800; color: var(--primary); text-align: right; font-size: 0.82rem; letter-spacing: -0.02em; white-space: nowrap; }

/* ✅ REMOVE ITEM BUTTON (only for editing current session) */
.btn-remove-item { width: 30px; height: 30px; border-radius: 8px; background: rgba(220, 38, 38, 0.12); color: #DC2626; border: 2px solid transparent; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.78rem; transition: all 0.2s ease; }
.btn-remove-item:hover { background: #DC2626; color: white; transform: scale(1.1); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); }

.scroll-hint { padding: 8px 16px; background: var(--primary-bg); border-top: 1px solid var(--border-color); text-align: center; font-size: 0.68rem; color: var(--text-secondary); font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; }
.scroll-hint i { color: var(--primary); font-size: 0.75rem; }
.scroll-hint kbd { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; padding: 1px 6px; font-family: var(--font-mono); font-size: 0.65rem; color: var(--primary); font-weight: 800; }

.totals-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.totals-card .card-header { padding: 12px 20px; background: linear-gradient(135deg, #059669, #047857); color: white; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.totals-card .card-header i { color: #A7F3D0; }
.totals-body { padding: 8px 0; }
.total-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; font-size: 0.85rem; border-bottom: 1px solid var(--border-color); }
.total-row:last-child { border-bottom: none; }
.total-row .label { color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; gap: 8px; }
.total-row .label i { color: var(--primary); font-size: 0.8rem; width: 16px; text-align: center; }
.total-row .value { font-family: var(--font-mono); font-weight: 800; font-size: 0.9rem; color: var(--text-primary); letter-spacing: -0.02em; }
.total-row .value.blue { color: var(--primary); }
.total-row .value.red { color: var(--danger); }
.total-row .value.green { color: var(--success); }
.total-row .value.purple { color: var(--purple); }
.total-row.grand { background: linear-gradient(135deg, var(--primary-bg), var(--primary-bg)); border-top: 3px solid var(--primary); border-bottom: 3px solid var(--primary); padding: 16px 20px; margin: 6px 0; }
.total-row.grand .label { color: var(--primary); font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.total-row.grand .value { color: var(--primary); font-size: 1.35rem; font-weight: 900; }

.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: sticky; bottom: 20px; z-index: 10; }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-save { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-save:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); }
.btn-back { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-back:hover { border-color: var(--primary); color: var(--primary); }
.btn-view { background: var(--bg-card); color: var(--primary); border: 2px solid var(--primary); }
.btn-view:hover { background: var(--primary); color: white; }

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .items-table { font-size: 0.72rem; }
    .items-table thead th, .items-table tbody td { padding: 6px 8px; }
    .action-bar { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .btn-scroll { width: 32px; height: 32px; font-size: 0.8rem; }
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
        <?php if ($alert_type === 'success'): ?>
        <script>
            setTimeout(function() {
                window.location.href = 'view_otc.php?id=<?= $sale_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            }, 2000);
        </script>
        <?php else: ?>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 4000);
        </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit OTC Sale
                <span class="branch-tag admin-tag"><i class="fas fa-crown"></i> ADMIN</span>
                <span class="branch-tag" style="background:linear-gradient(135deg,#10B981,#059669);">
                    <i class="fas fa-shopping-cart"></i> <?= count($sale_items) ?> Items
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <strong><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($sale['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_otc.php?id=<?= $sale_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- MAIN FORM -->
    <form method="POST" id="editOtcForm">
        <input type="hidden" name="action" value="update_otc">
        <input type="hidden" name="deleted_items" id="deletedItems" value="">

        <!-- SALE INFO -->
        <div class="form-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-info-circle"></i> Sale Information</span>
                <span style="font-size:0.68rem;color:var(--text-secondary);font-weight:700;">
                    <i class="fas fa-clock"></i> Created: <?= date('d M Y, H:i', strtotime($sale['created_at'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Customer Name</label>
                        <input type="text" name="customer_name" value="<?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>" placeholder="Customer name...">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="text" name="customer_phone" value="<?= htmlspecialchars($sale['customer_phone'] ?? '') ?>" placeholder="Phone number...">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Payment Method</label>
                        <select name="payment_method">
                            <option value="cash" <?= ($sale['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="m-pesa" <?= ($sale['payment_method'] ?? '') === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                            <option value="airtel_money" <?= ($sale['payment_method'] ?? '') === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                            <option value="tigo_pesa" <?= ($sale['payment_method'] ?? '') === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                            <option value="halopesa" <?= ($sale['payment_method'] ?? '') === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                            <option value="bank" <?= ($sale['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank</option>
                            <option value="card" <?= ($sale['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
                            <option value="other" <?= ($sale['payment_method'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Payment Status</label>
                        <select name="payment_status">
                            <option value="paid" <?= ($sale['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="pending" <?= ($sale['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="partial" <?= ($sale['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="cancelled" <?= ($sale['payment_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-percent"></i> Discount Amount</label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" name="discount_amount" id="discountAmount" class="money-input"
                                   value="<?= number_format((float)($sale['discount_amount'] ?? 0), 0) ?>" 
                                   inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Premium Charge</label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" name="premium_amount" id="premiumAmount" class="money-input"
                                   value="<?= number_format((float)($sale['premium_amount'] ?? 0), 0) ?>" 
                                   inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Premium Note</label>
                        <input type="text" name="premium_note" value="<?= htmlspecialchars($sale['premium_note'] ?? '') ?>" placeholder="e.g. Premium charge reason...">
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Notes</label>
                        <textarea name="notes" rows="2" placeholder="Additional notes..."><?= htmlspecialchars($sale['notes'] ?? '') ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <div class="items-table-card">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-pills"></i>
                    Medications
                    <span style="font-size:0.65rem;font-weight:600;background:rgba(16,185,129,0.3);padding:2px 10px;border-radius:8px;margin-left:6px;">
                        <?= count($sale_items) ?> items
                    </span>
                </span>
                <div class="header-actions">
                    <div class="scroll-buttons" title="Scroll left / right">
                        <button type="button" class="btn-scroll" onclick="scrollItems('left')" title="Scroll Left (Alt+←)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="btn-scroll" onclick="scrollItems('right')" title="Scroll Right (Alt+→)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button type="button" class="btn-add-item" onclick="addNewItem()">
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                </div>
            </div>
            
            <div class="items-table-wrapper" id="itemsWrapper">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="width:230px;">💊 Medication (Change)</th>
                            <th style="width:80px;text-align:center;">Qty</th>
                            <th style="width:120px;text-align:right;">Unit Price</th>
                            <th style="width:110px;text-align:right;">Total</th>
                            <th style="width:130px;">📋 Dosage</th>
                            <th style="width:130px;">⏰ Frequency</th>
                            <th style="width:110px;">🛣️ Route</th>
                            <th style="width:160px;">📝 Instructions</th>
                            <th style="width:50px;text-align:center;">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php if (count($sale_items) > 0): ?>
                            <?php $item_num = 1; foreach ($sale_items as $item): 
                                $current_med_id = (int)($item['inventory_id'] ?? 0);
                                $current_med_name = $item['display_name'] ?? ($item['item_name'] ?? ($item['medicine_name'] ?? ''));
                                $item_qty = (int)($item['quantity'] ?? 0);
                                $item_price = (float)($item['unit_price'] ?? 0);
                                $item_total = (float)($item['total_price'] ?? 0);
                            ?>
                                <tr class="item-row" data-item-id="<?= $item['id'] ?>">
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);" class="row-num"><?= $item_num++ ?></td>
                                    <td>
                                        <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="item_name[]" class="item-name-hidden" value="<?= htmlspecialchars($current_med_name) ?>">
                                        <select name="item_inventory_id[]" class="item-medication-select" onchange="onMedicationChange(this)">
                                            <option value="">-- Select Medication --</option>
                                            <?php foreach ($medications_list as $med): 
                                                $med_id = (int)$med['id'];
                                                $med_name = $med['medication_name'] ?? '';
                                                $med_price = (float)($med['selling_price'] ?? 0);
                                                $med_qty = (int)($med['total_quantity'] ?? 0);
                                                $med_unit = $med['unit'] ?? '';
                                                $is_selected = ($current_med_id == $med_id) || ($current_med_id == 0 && $current_med_name === $med_name);
                                            ?>
                                                <option value="<?= $med_id ?>" 
                                                        data-name="<?= htmlspecialchars($med_name) ?>"
                                                        data-price="<?= $med_price ?>"
                                                        data-unit="<?= htmlspecialchars($med_unit) ?>"
                                                        data-stock="<?= $med_qty ?>"
                                                        <?= $is_selected ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($med_name) ?> 
                                                    (<?= number_format($med_qty) ?> <?= htmlspecialchars($med_unit) ?>) 
                                                    - <?= $currency ?> <?= number_format($med_price, 0) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="item_quantity[]" class="item-qty-input" 
                                               value="<?= $item_qty ?>" min="1" oninput="recalculate()">
                                    </td>
                                    <td>
                                        <input type="text" name="item_price[]" class="item-price-input money-input" 
                                               value="<?= number_format($item_price, 0) ?>" 
                                               inputmode="numeric" autocomplete="off">
                                    </td>
                                    <td class="item-total-cell">
                                        <?= $currency ?> <span class="item-total-value"><?= number_format($item_total, 0) ?></span>
                                    </td>
                                    <td>
                                        <input type="text" name="item_dosage[]" value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" placeholder="e.g. 500mg">
                                    </td>
                                    <td>
                                        <input type="text" name="item_frequency[]" value="<?= htmlspecialchars($item['frequency'] ?? '') ?>" placeholder="e.g. 2x daily">
                                    </td>
                                    <td>
                                        <input type="text" name="item_route[]" value="<?= htmlspecialchars($item['route'] ?? '') ?>" placeholder="e.g. Oral">
                                    </td>
                                    <td>
                                        <input type="text" name="item_instructions[]" value="<?= htmlspecialchars($item['instructions'] ?? '') ?>" placeholder="Instructions...">
                                    </td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove from edit">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="scroll-hint">
                <i class="fas fa-arrows-alt-h"></i>
                Tumia <kbd>‹</kbd> <kbd>›</kbd> buttons kuslide kushoto/kulia 
                <span style="opacity:0.5;">•</span>
                Keyboard: <kbd>Alt</kbd> + <kbd>←</kbd> / <kbd>→</kbd>
                <span style="opacity:0.5;">•</span>
                <strong>💊 Badilisha dawa kwenye dropdown</strong>
            </div>
        </div>

        <!-- TOTALS -->
        <div class="totals-card">
            <div class="card-header">
                <i class="fas fa-calculator"></i> Sale Summary
            </div>
            <div class="totals-body">
                <div class="total-row">
                    <span class="label"><i class="fas fa-cubes"></i> Total Items</span>
                    <span class="value blue" id="totalItemsValue"><?= count($sale_items) ?></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-sort-numeric-up"></i> Total Quantity</span>
                    <span class="value blue" id="totalQtyValue">0</span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-receipt"></i> Subtotal</span>
                    <span class="value"><span class="currency-prefix" style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="subtotalValue">0</span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-tags"></i> Discount</span>
                    <span class="value red">- <span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="discountValue">0</span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-star"></i> Premium</span>
                    <span class="value purple">+ <span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="premiumValue">0</span></span>
                </div>
                
                <div class="total-row grand">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                    <span class="value"><span style="font-size:0.85rem;"><?= $currency ?></span> <span id="grandTotalValue">0</span></span>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="action-info">
                <div class="info-icon"><i class="fas fa-edit"></i></div>
                <div class="info-text">
                    <div class="info-title">Save Changes</div>
                    <div class="info-sub">Changes will be logged in audit trail</div>
                </div>
            </div>
            <div class="action-buttons">
                <a href="view_otc.php?id=<?= $sale_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-back">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>

    </form>

</main>

<!-- TEMPLATE FOR NEW ITEM -->
<template id="newItemTemplate">
    <tr class="item-row">
        <td style="text-align:center;font-weight:700;color:var(--text-secondary);" class="row-num">-</td>
        <td>
            <input type="hidden" name="item_id[]" value="">
            <input type="hidden" name="item_name[]" class="item-name-hidden" value="">
            <select name="item_inventory_id[]" class="item-medication-select" onchange="onMedicationChange(this)">
                <option value="">-- Select Medication --</option>
                <?php foreach ($medications_list as $med): 
                    $med_id = (int)$med['id'];
                    $med_name = $med['medication_name'] ?? '';
                    $med_price = (float)($med['selling_price'] ?? 0);
                    $med_qty = (int)($med['total_quantity'] ?? 0);
                    $med_unit = $med['unit'] ?? '';
                ?>
                    <option value="<?= $med_id ?>" 
                            data-name="<?= htmlspecialchars($med_name) ?>"
                            data-price="<?= $med_price ?>"
                            data-unit="<?= htmlspecialchars($med_unit) ?>"
                            data-stock="<?= $med_qty ?>">
                        <?= htmlspecialchars($med_name) ?> 
                        (<?= number_format($med_qty) ?> <?= htmlspecialchars($med_unit) ?>) 
                        - <?= $currency ?> <?= number_format($med_price, 0) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" name="item_quantity[]" class="item-qty-input" value="1" min="1" oninput="recalculate()"></td>
        <td><input type="text" name="item_price[]" class="item-price-input money-input" value="0" inputmode="numeric" autocomplete="off"></td>
        <td class="item-total-cell"><?= $currency ?> <span class="item-total-value">0</span></td>
        <td><input type="text" name="item_dosage[]" value="" placeholder="e.g. 500mg"></td>
        <td><input type="text" name="item_frequency[]" value="" placeholder="e.g. 2x daily"></td>
        <td><input type="text" name="item_route[]" value="" placeholder="e.g. Oral"></td>
        <td><input type="text" name="item_instructions[]" value="" placeholder="Instructions..."></td>
        <td style="text-align:center;">
            <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<script>
var currency = '<?= $currency ?>';
var deletedItems = [];

function onMedicationChange(select) {
    var row = select.closest('tr.item-row');
    if (!row) return;
    
    var selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;
    
    var medName = selectedOption.getAttribute('data-name') || '';
    var medPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    var medStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
    
    var nameHidden = row.querySelector('.item-name-hidden');
    if (nameHidden) nameHidden.value = medName;
    
    var priceInput = row.querySelector('.item-price-input');
    if (priceInput) priceInput.value = formatMoney(medPrice);
    
    if (medStock <= 0) {
        select.style.borderColor = '#DC2626';
        select.style.background = '#FEE2E2';
        select.style.color = '#DC2626';
    } else {
        select.style.borderColor = '';
        select.style.background = '';
        select.style.color = '';
    }
    
    recalculate();
}

function formatMoney(value) {
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    var parts = cleaned.split('.');
    var integerPart = parts[0];
    var decimalPart = parts.length > 1 ? parts[1] : '';
    integerPart = integerPart.replace(/^0+/, '') || '0';
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (decimalPart) return integerPart + '.' + decimalPart;
    return integerPart;
}

function parseMoney(value) {
    if (typeof value === 'number') return value;
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    return parseFloat(cleaned) || 0;
}

function numberFormat(num) {
    num = Math.round(num || 0);
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function attachMoneyFormat(input) {
    if (!input || input.dataset.moneyAttached === '1') return;
    input.dataset.moneyAttached = '1';
    
    if (input.value && input.value !== '0') input.value = formatMoney(input.value);
    
    input.addEventListener('input', function(e) {
        var cursorPos = this.selectionStart;
        var oldLength = this.value.length;
        var formatted = formatMoney(this.value);
        this.value = formatted;
        var diff = formatted.length - oldLength;
        if (this.setSelectionRange) this.setSelectionRange(cursorPos + diff, cursorPos + diff);
        recalculate();
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '' || this.value === '.') this.value = '0';
        else this.value = formatMoney(this.value);
        recalculate();
    });
    
    input.addEventListener('focus', function() {
        var self = this;
        setTimeout(function() { self.select(); }, 10);
    });
    
    input.addEventListener('keypress', function(e) {
        var char = String.fromCharCode(e.which);
        if (!/[0-9.]/.test(char)) e.preventDefault();
    });
}

function initMoneyInputs() {
    document.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
}

function scrollItems(direction) {
    var wrapper = document.getElementById('itemsWrapper');
    if (!wrapper) return;
    if (wrapper.scrollWidth <= wrapper.clientWidth) return;
    
    var scrollAmount = 400;
    var currentScroll = wrapper.scrollLeft;
    var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
    
    var targetScroll = direction === 'left' 
        ? Math.max(0, currentScroll - scrollAmount)
        : Math.min(maxScroll, currentScroll + scrollAmount);
    
    wrapper.scrollTo({ left: targetScroll, behavior: 'smooth' });
    
    var btns = document.querySelectorAll('.btn-scroll');
    btns.forEach(function(btn) {
        btn.style.transform = 'scale(0.9)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

function recalculate() {
    var rows = document.querySelectorAll('#itemsTableBody tr.item-row');
    var subtotal = 0;
    var totalQty = 0;
    var itemCount = 0;
    
    rows.forEach(function(row) {
        if (row.classList.contains('removing') || row.style.display === 'none') return;
        
        var qtyInput = row.querySelector('.item-qty-input');
        var priceInput = row.querySelector('.item-price-input');
        
        if (!qtyInput || !priceInput) return;
        
        var qty = parseFloat(qtyInput.value) || 0;
        var price = parseMoney(priceInput.value);
        var rowTotal = qty * price;
        
        subtotal += rowTotal;
        totalQty += qty;
        itemCount++;
        
        var totalEl = row.querySelector('.item-total-value');
        if (totalEl) totalEl.textContent = numberFormat(rowTotal);
    });
    
    var discount = parseMoney(document.getElementById('discountAmount').value);
    var premium = parseMoney(document.getElementById('premiumAmount').value);
    
    var grandTotal = subtotal - discount + premium;
    if (grandTotal < 0) grandTotal = 0;
    
    document.getElementById('subtotalValue').textContent = numberFormat(subtotal);
    document.getElementById('discountValue').textContent = numberFormat(discount);
    document.getElementById('premiumValue').textContent = numberFormat(premium);
    document.getElementById('grandTotalValue').textContent = numberFormat(grandTotal);
    document.getElementById('totalQtyValue').textContent = numberFormat(totalQty);
    document.getElementById('totalItemsValue').textContent = numberFormat(itemCount);
    
    var num = 1;
    rows.forEach(function(row) {
        if (!row.classList.contains('removing') && row.style.display !== 'none') {
            var numEl = row.querySelector('.row-num');
            if (numEl) numEl.textContent = num++;
        }
    });
}

function addNewItem() {
    var tbody = document.getElementById('itemsTableBody');
    var template = document.getElementById('newItemTemplate');
    
    if (!template) return;
    
    var newRow = template.content.cloneNode(true).querySelector('tr');
    tbody.appendChild(newRow);
    
    newRow.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
    
    var medSelect = newRow.querySelector('.item-medication-select');
    if (medSelect) medSelect.focus();
    
    setTimeout(function() {
        newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }, 100);
    
    recalculate();
}

function removeItem(btn) {
    var row = btn.closest('tr.item-row');
    var itemId = row.querySelector('input[name="item_id[]"]').value;
    var medSelect = row.querySelector('.item-medication-select');
    var itemName = 'this item';
    if (medSelect && medSelect.selectedIndex > 0) {
        itemName = medSelect.options[medSelect.selectedIndex].getAttribute('data-name') || itemName;
    }
    
    if (itemId) {
        if (!confirm('Are you sure you want to remove "' + itemName + '" from this sale?')) return;
        
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

document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); scrollItems('left'); }
    if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); scrollItems('right'); }
});

document.getElementById('editOtcForm').addEventListener('submit', function(e) {
    var visibleItems = document.querySelectorAll('#itemsTableBody tr.item-row:not(.removing)');
    var hasValidItem = false;
    
    visibleItems.forEach(function(row) {
        if (row.style.display === 'none') return;
        var medSelect = row.querySelector('.item-medication-select');
        if (medSelect && medSelect.value) hasValidItem = true;
    });
    
    if (!hasValidItem) {
        e.preventDefault();
        alert('Sale must have at least one medication selected!');
        return false;
    }
    
    if (!confirm('Are you sure you want to save these changes?')) {
        e.preventDefault();
        return false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    initMoneyInputs();
    recalculate();
});

console.log('%c✏️ Edit OTC Sale V2 - BLUE THEME', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Edit only - NO delete buttons', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Sale: <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c✅ Items: <?= count($sale_items) ?>', 'font-size:13px; color:#34D399;');
console.log('%c✅ Medications available: <?= count($medications_list) ?>', 'font-size:13px; color:#7C3AED;');
console.log('%c✅ Scroll buttons: < >', 'font-size:13px; color:#3B82F6;');
</script>

</body>
</html>