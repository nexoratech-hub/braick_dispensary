<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_procedure.php
// ADMIN - EDIT PROCEDURE / EQUIPMENT
// ✅ Inatumia reference_id + bill_item_id
// ✅ Ina-update bill_items na bills kwa usahihi
// ✅ Ina-recalculate bill baada ya edit
// ✅ Inafanya kazi kwa procedure NA equipment
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

$allowed_roles = ['admin', 'audit'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$is_admin = ($user_role === 'admin');
$profile_pic = $_SESSION['profile_pic'] ?? '';

if (!$is_admin) {
    $_SESSION['error_message'] = "Only Admin can edit procedures.";
    header('Location: other_services.php?tab=procedures');
    exit;
}

// ================================================================
// PARAMETERS
// ================================================================
$reference_id = (int)($_GET['id'] ?? 0);
$bill_item_id = (int)($_GET['bill_item_id'] ?? 0);
$item_type = $_GET['type'] ?? 'procedure';
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($reference_id <= 0 && $bill_item_id <= 0) {
    header('Location: other_services.php?tab=procedures');
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// CURRENCY
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// LOG ACTIVITY
// ================================================================
function logActivity($db, $user_id, $branch_id, $patient_id, $action, $details) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, branch_id, patient_id, action, details, ip_address, user_agent, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $branch_id, $patient_id, $action, $details, $ip, $ua]);
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

// ================================================================
// FETCH ITEM
// ================================================================
$item = null;
try {
    // Prefer bill_item_id
    if ($bill_item_id > 0) {
        $stmt = $db->prepare("
            SELECT bi.*,
                   bi.id as bill_item_row_id,
                   bi.reference_id,
                   bi.item_type,
                   bi.item_name as procedure_name,
                   bi.unit_price as procedure_price,
                   bi.quantity,
                   bi.total_price as procedure_total,
                   bi.discount_amount as item_discount,
                   bi.status as item_status,
                   bi.created_at as item_created_at,
                   bi.description,
                   b.id as bill_id, b.bill_number, b.status as bill_status,
                   b.subtotal as bill_subtotal, b.total_amount as bill_total,
                   b.paid_amount as bill_paid, b.balance as bill_balance,
                   b.total_discount as bill_discount, b.discount_amount as bill_discount_amount,
                   b.premium_amount as bill_premium, b.payment_method,
                   b.created_at as bill_created_at,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number, pat.phone as patient_phone,
                   pat.gender as patient_gender, pat.date_of_birth,
                   v.id as visit_db_id, v.visit_number, v.visit_date,
                   doc.full_name as doctor_name,
                   br.name as branch_name,
                   pc.procedure_code, pc.category as procedure_category,
                   pc.description as procedure_description,
                   me.equipment_name, me.category as equipment_category,
                   me.unit as equipment_unit, me.batch_number as equipment_batch,
                   me.supplier as equipment_supplier, me.expiry_date as equipment_expiry,
                   me.selling_price as equipment_price
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON bi.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users doc ON v.doctor_id = doc.id
            LEFT JOIN branches br ON bi.branch_id = br.id
            LEFT JOIN procedures_catalog pc ON (bi.item_type = 'procedure' AND bi.reference_id = pc.id)
            LEFT JOIN medical_equipment me ON (bi.item_type = 'equipment' AND bi.reference_id = me.id)
            WHERE bi.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bill_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Fallback: reference_id
    if (!$item && $reference_id > 0) {
        $stmt = $db->prepare("
            SELECT bi.*,
                   bi.id as bill_item_row_id,
                   bi.reference_id,
                   bi.item_type,
                   bi.item_name as procedure_name,
                   bi.unit_price as procedure_price,
                   bi.quantity,
                   bi.total_price as procedure_total,
                   bi.discount_amount as item_discount,
                   bi.status as item_status,
                   bi.created_at as item_created_at,
                   bi.description,
                   b.id as bill_id, b.bill_number, b.status as bill_status,
                   b.subtotal as bill_subtotal, b.total_amount as bill_total,
                   b.paid_amount as bill_paid, b.balance as bill_balance,
                   b.total_discount as bill_discount, b.discount_amount as bill_discount_amount,
                   b.premium_amount as bill_premium, b.payment_method,
                   b.created_at as bill_created_at,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number, pat.phone as patient_phone,
                   pat.gender as patient_gender, pat.date_of_birth,
                   v.id as visit_db_id, v.visit_number, v.visit_date,
                   doc.full_name as doctor_name,
                   br.name as branch_name,
                   pc.procedure_code, pc.category as procedure_category,
                   pc.description as procedure_description,
                   me.equipment_name, me.category as equipment_category,
                   me.unit as equipment_unit, me.batch_number as equipment_batch,
                   me.supplier as equipment_supplier, me.expiry_date as equipment_expiry,
                   me.selling_price as equipment_price
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON bi.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users doc ON v.doctor_id = doc.id
            LEFT JOIN branches br ON bi.branch_id = br.id
            LEFT JOIN procedures_catalog pc ON (bi.item_type = 'procedure' AND bi.reference_id = pc.id)
            LEFT JOIN medical_equipment me ON (bi.item_type = 'equipment' AND bi.reference_id = me.id)
            WHERE bi.reference_id = ?
              AND bi.item_type IN ('procedure', 'equipment')
            ORDER BY bi.id DESC
            LIMIT 1
        ");
        $stmt->execute([$reference_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Final fallback: procedures table
    if (!$item && $reference_id > 0) {
        $stmt = $db->prepare("
            SELECT p.*,
                   NULL as bill_item_row_id,
                   p.id as reference_id,
                   'procedure' as item_type,
                   p.procedure_name,
                   p.procedure_price,
                   1 as quantity,
                   p.procedure_price as procedure_total,
                   0 as item_discount,
                   p.status as item_status,
                   p.created_at as item_created_at,
                   p.notes as description,
                   NULL as bill_id, NULL as bill_number, NULL as bill_status,
                   NULL as bill_subtotal, NULL as bill_total,
                   NULL as bill_paid, NULL as bill_balance,
                   NULL as bill_discount, NULL as bill_discount_amount,
                   NULL as bill_premium, NULL as payment_method,
                   NULL as bill_created_at,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number, pat.phone as patient_phone,
                   pat.gender as patient_gender, pat.date_of_birth,
                   v.id as visit_db_id, v.visit_number, v.visit_date,
                   doc.full_name as doctor_name,
                   br.name as branch_name,
                   p.procedure_code, p.category as procedure_category,
                   NULL as procedure_description,
                   NULL as equipment_name, NULL as equipment_category,
                   NULL as equipment_unit, NULL as equipment_batch,
                   NULL as equipment_supplier, NULL as equipment_expiry,
                   NULL as equipment_price
            FROM procedures p
            LEFT JOIN patients pat ON p.patient_id = pat.id
            LEFT JOIN visits v ON p.visit_id = v.id
            LEFT JOIN users doc ON p.doctor_id = doc.id
            LEFT JOIN branches br ON p.branch_id = br.id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$reference_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die("Fetch error: " . $e->getMessage());
}

if (!$item) {
    $_SESSION['error_message'] = "Procedure/Equipment not found.";
    header('Location: other_services.php?tab=procedures');
    exit;
}

// Normalize fields
$item['procedure_name'] = $item['procedure_name'] ?? $item['item_name'] ?? $item['equipment_name'] ?? 'N/A';
$item['procedure_price'] = $item['procedure_price'] ?? $item['unit_price'] ?? $item['equipment_price'] ?? 0;
$item['item_status'] = $item['item_status'] ?? $item['status'] ?? 'pending';
$item['item_created_at'] = $item['item_created_at'] ?? $item['created_at'] ?? date('Y-m-d H:i:s');
$is_equipment = ($item['item_type'] ?? '') === 'equipment';
$item_label = $is_equipment ? 'Equipment' : 'Procedure';
$item_id = $item['reference_id'] ?? $item['id'] ?? 0;
$bill_item_row_id = $item['bill_item_row_id'] ?? 0;

// ================================================================
// FETCH CATALOG (kwa dropdown ya kubadilisha)
// ================================================================
$catalog_items = [];
try {
    if ($is_equipment) {
        $stmt = $db->query("
            SELECT id, equipment_name as name, category, selling_price as price
            FROM medical_equipment 
            WHERE status = 'active'
            ORDER BY equipment_name
        ");
    } else {
        $stmt = $db->query("
            SELECT id, procedure_name as name, category, price
            FROM procedures_catalog 
            WHERE is_active = 1
            ORDER BY procedure_name
        ");
    }
    $catalog_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// HANDLE SUBMIT
// ================================================================
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_procedure') {
    $new_name = trim($_POST['procedure_name'] ?? '');
    $new_price = (float)($_POST['procedure_price'] ?? 0);
    $new_quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $new_category = trim($_POST['category'] ?? '');
    $new_status = $_POST['item_status'] ?? 'pending';
    $new_notes = trim($_POST['notes'] ?? '');
    $new_item_discount = (float)($_POST['item_discount'] ?? 0);
    
    // Validation
    if (empty($new_name)) $errors[] = "Item name is required.";
    if ($new_price < 0) $errors[] = "Price cannot be negative.";
    if ($new_quantity < 1) $errors[] = "Quantity must be at least 1.";
    if ($new_item_discount < 0) $errors[] = "Discount cannot be negative.";
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $old_name = $item['procedure_name'];
            $old_price = (float)$item['procedure_price'];
            $old_quantity = (int)($item['quantity'] ?? 1);
            $new_total = ($new_price * $new_quantity) - $new_item_discount;
            if ($new_total < 0) $new_total = 0;
            
            // ============================================================
            // 1. Update procedures table (kama ni procedure)
            // ============================================================
            if (!$is_equipment && $reference_id > 0) {
                // Check kama procedures row ipo
                $stmt = $db->prepare("SELECT id FROM procedures WHERE id = ?");
                $stmt->execute([$reference_id]);
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("
                        UPDATE procedures 
                        SET procedure_name = ?,
                            procedure_price = ?,
                            category = ?,
                            status = ?,
                            notes = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_name, $new_price, $new_category, $new_status, $new_notes, $reference_id]);
                }
            }
            
            // ============================================================
            // 2. Update medical_equipment table (kama ni equipment)
            // ============================================================
            if ($is_equipment && $reference_id > 0) {
                $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ?");
                $stmt->execute([$reference_id]);
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("
                        UPDATE medical_equipment 
                        SET equipment_name = ?,
                            category = ?,
                            selling_price = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_name, $new_category, $new_price, $reference_id]);
                }
            }
            
            // ============================================================
            // 3. Update bill_items (kama item ipo kwenye bill)
            // ============================================================
            $bill_item_row_id_update = $item['bill_item_row_id'] ?? 0;
            if ($bill_item_row_id_update > 0) {
                $stmt = $db->prepare("
                    UPDATE bill_items
                    SET item_name = ?,
                        unit_price = ?,
                        quantity = ?,
                        total_price = ?,
                        discount_amount = ?,
                        status = ?,
                        description = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $new_name,
                    $new_price,
                    $new_quantity,
                    $new_total,
                    $new_item_discount,
                    $new_status,
                    $new_notes,
                    $bill_item_row_id_update
                ]);
            }
            
            // ============================================================
            // 4. Recalculate bill
            // ============================================================
            $bill_id = $item['bill_id'] ?? 0;
            if ($bill_id > 0) {
                // Calculate new subtotal + item discount from bill_items
                $stmt = $db->prepare("
                    SELECT 
                        COALESCE(SUM(total_price), 0) as new_subtotal,
                        COALESCE(SUM(discount_amount), 0) as new_item_discount
                    FROM bill_items 
                    WHERE bill_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$bill_id]);
                $btotals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $new_subtotal = (float)$btotals['new_subtotal'];
                $new_item_discount_total = (float)$btotals['new_item_discount'];
                
                // Fetch current bill
                $stmt = $db->prepare("SELECT * FROM bills WHERE id = ?");
                $stmt->execute([$bill_id]);
                $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $bill_discount = (float)($bill_data['discount_amount'] ?? 0);
                $bill_premium = (float)($bill_data['premium_amount'] ?? 0);
                $paid = (float)($bill_data['paid_amount'] ?? 0);
                
                $new_total_discount = $bill_discount + $new_item_discount_total;
                $new_total = $new_subtotal - $new_total_discount + $bill_premium;
                if ($new_total < 0) $new_total = 0;
                
                $new_balance = $new_total - $paid;
                if ($new_balance < 0) $new_balance = 0;
                
                $new_bill_status = 'pending';
                if ($new_balance <= 0 && $new_total > 0) $new_bill_status = 'paid';
                elseif ($paid > 0 && $new_balance > 0) $new_bill_status = 'partial';
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET subtotal = ?,
                        total_discount = ?,
                        total_amount = ?,
                        balance = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $new_subtotal,
                    $new_total_discount,
                    $new_total,
                    $new_balance,
                    $new_bill_status,
                    $bill_id
                ]);
            }
            
            // ============================================================
            // 5. Log activity
            // ============================================================
            logActivity(
                $db, $user_id, $item['branch_id'] ?? null, $item['patient_db_id'] ?? null,
                'procedure_updated',
                "Edited {$item_label}: '{$old_name}' → '{$new_name}' | " .
                "Price: {$currency} " . number_format($old_price, 0) . " → {$currency} " . number_format($new_price, 0) . " | " .
                "Qty: {$old_quantity} → {$new_quantity} | " .
                "Status: {$new_status}"
            );
            
            $db->commit();
            $success = true;
            $_SESSION['success_message'] = "{$item_label} updated successfully!";
            
            // Refresh item data
            header('Location: view_procedure.php?id=' . $reference_id . '&bill_item_id=' . $bill_item_row_id . '&type=' . $item['item_type'] . '&branch=' . $selected_branch_id . '&updated=1');
            exit;
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
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
    <title>Edit <?= $item_label ?> - <?= htmlspecialchars($item['procedure_name']) ?></title>
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
* { font-family: var(--font-primary); box-sizing: border-box; }
html, body { background: var(--bg-body); color: var(--text-primary); margin: 0; }
.mono { font-family: var(--font-mono) !important; }

/* ALERTS */
.alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert i { font-size: 1.1rem; }

/* PAGE HEADER */
.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; margin: 0; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.admin-tag { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; }
.branch-tag.cyan-tag { background: linear-gradient(135deg, #06B6D4, #0891B2); font-weight: 700; }
.branch-tag.purple-tag { background: linear-gradient(135deg, #7C3AED, #6D28D9); font-weight: 700; }

.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); color: white; }

/* FORM GRID */
.form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 18px; }
@media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }

.info-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 16px; }
.info-card .card-header { padding: 14px 18px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 800; }
.info-card .card-header i { color: #93C5FD; }
.info-card .card-header.cyan-header { background: linear-gradient(135deg, #06B6D4, #0891B2); }
.info-card .card-header.cyan-header i { color: #CFFAFE; }
.info-card .card-body { padding: 20px; }

/* FORM */
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
.form-group label .req { color: var(--danger); }
.form-control {
    width: 100%; padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    font-family: var(--font-primary);
}
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}
.form-control.mono { font-family: var(--font-mono); }
.form-control[readonly] { background: #F8FAFC; color: var(--text-secondary); cursor: not-allowed; }
[data-theme="dark"] .form-control[readonly] { background: #0F172A; }
textarea.form-control { min-height: 80px; resize: vertical; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }

/* INFO ROW */
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-color); font-size: 0.82rem; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: var(--text-secondary); font-weight: 600; font-size: 0.72rem; display: flex; align-items: center; gap: 6px; }
.info-row .label i { color: var(--primary); font-size: 0.7rem; }
.info-row .value { color: var(--text-primary); font-weight: 700; text-align: right; }
.info-row .value.mono { font-family: var(--font-mono); }

/* BILL SUMMARY */
.bill-summary {
    background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
    border-radius: 12px;
    padding: 16px;
    border-left: 4px solid var(--purple);
    margin-top: 16px;
}
[data-theme="dark"] .bill-summary { background: linear-gradient(135deg, #2D1B4E, #1E1B4B); }
.bill-summary-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--purple); letter-spacing: 0.05em; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.bill-summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.bill-summary-item { background: rgba(255,255,255,0.6); padding: 8px 12px; border-radius: 8px; }
[data-theme="dark"] .bill-summary-item { background: rgba(255,255,255,0.1); }
.bill-summary-item .bs-label { font-size: 0.55rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
.bill-summary-item .bs-value { font-size: 0.9rem; font-weight: 800; font-family: var(--font-mono); color: var(--text-primary); margin-top: 2px; }
.bill-summary-item.paid .bs-value { color: var(--success); }
.bill-summary-item.balance .bs-value { color: var(--danger); }
.bill-summary-item.total .bs-value { color: var(--primary); }

/* LIVE PREVIEW */
.live-preview {
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
    border-radius: 12px;
    padding: 16px;
    border: 2px solid var(--primary-light);
    margin-bottom: 16px;
}
[data-theme="dark"] .live-preview { background: linear-gradient(135deg, #1E3A5F, #0F2A4A); }
.live-preview-title { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.05em; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.live-preview-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 0.82rem; }
.live-preview-row .lp-label { color: var(--text-secondary); font-weight: 600; }
.live-preview-row .lp-value { font-weight: 800; font-family: var(--font-mono); color: var(--primary); font-size: 0.95rem; }
.live-preview-row.highlight { border-top: 2px dashed rgba(11,94,215,0.3); margin-top: 8px; padding-top: 10px; }
.live-preview-row.highlight .lp-value { font-size: 1.15rem; color: var(--success); }

/* BUTTONS */
.action-bar { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; padding: 16px 0; border-top: 2px solid var(--border-color); margin-top: 20px; }
.btn { padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; font-family: var(--font-primary); }
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); color: white; }
.btn-success { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.btn-success:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5); color: white; }
.btn-secondary { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- ERRORS -->
    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <?php foreach ($errors as $err): ?>
                    <div>• <?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-edit' ?>"></i>
                Edit <?= $item_label ?>
                <span class="branch-tag admin-tag"><i class="fas fa-crown"></i> ADMIN</span>
                <?php if ($is_equipment): ?>
                    <span class="branch-tag cyan-tag"><i class="fas fa-tools"></i> EQUIPMENT</span>
                <?php else: ?>
                    <span class="branch-tag purple-tag"><i class="fas fa-syringe"></i> PROCEDURE</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i>
                <strong><?= htmlspecialchars($item['procedure_name']) ?></strong>
                <?php if (!empty($item['branch_name'])): ?>
                    <span class="branch-tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($item['branch_name']) ?></span>
                <?php endif; ?>
                <span class="branch-tag"><i class="fas fa-hashtag"></i> ID: <?= $item_id ?></span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_procedure.php?id=<?= $item_id ?>&bill_item_id=<?= $bill_item_row_id ?>&type=<?= $item['item_type'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="other_services.php?tab=procedures&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="update_procedure">
        
        <div class="form-grid">
            
            <!-- LEFT: FORM -->
            <div>
                <div class="info-card">
                    <div class="card-header <?= $is_equipment ? 'cyan-header' : '' ?>">
                        <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-edit' ?>"></i> Edit <?= $item_label ?> Information
                    </div>
                    <div class="card-body">
                        
                        <div class="form-group">
                            <label><?= $item_label ?> Name <span class="req">*</span></label>
                            <input type="text" 
                                   name="procedure_name" 
                                   id="input_name"
                                   class="form-control" 
                                   value="<?= htmlspecialchars($item['procedure_name']) ?>"
                                   required
                                   onchange="updatePreview()"
                                   onkeyup="updatePreview()">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Unit Price (<?= $currency ?>) <span class="req">*</span></label>
                                <input type="number" 
                                       name="procedure_price" 
                                       id="input_price"
                                       class="form-control mono" 
                                       value="<?= number_format((float)$item['procedure_price'], 0, '.', '') ?>"
                                       min="0"
                                       step="1"
                                       required
                                       onchange="updatePreview()"
                                       onkeyup="updatePreview()">
                            </div>
                            
                            <div class="form-group">
                                <label>Quantity <span class="req">*</span></label>
                                <input type="number" 
                                       name="quantity" 
                                       id="input_quantity"
                                       class="form-control mono" 
                                       value="<?= (int)($item['quantity'] ?? 1) ?>"
                                       min="1"
                                       step="1"
                                       required
                                       onchange="updatePreview()"
                                       onkeyup="updatePreview()">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Discount (<?= $currency ?>)</label>
                                <input type="number" 
                                       name="item_discount" 
                                       id="input_discount"
                                       class="form-control mono" 
                                       value="<?= number_format((float)($item['item_discount'] ?? 0), 0, '.', '') ?>"
                                       min="0"
                                       step="1"
                                       onchange="updatePreview()"
                                       onkeyup="updatePreview()">
                            </div>
                            
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" 
                                       name="category" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($item['procedure_category'] ?? $item['equipment_category'] ?? '') ?>"
                                       placeholder="e.g. Surgery, Wound Care">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Item Status</label>
                            <select name="item_status" class="form-control">
                                <option value="pending" <?= ($item['item_status'] ?? '') === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                <option value="in_progress" <?= ($item['item_status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>🔄 In Progress</option>
                                <option value="completed" <?= ($item['item_status'] ?? '') === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                                <option value="paid" <?= ($item['item_status'] ?? '') === 'paid' ? 'selected' : '' ?>>💰 Paid</option>
                                <option value="cancelled" <?= ($item['item_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes / Description</label>
                            <textarea name="notes" 
                                      class="form-control" 
                                      placeholder="Additional notes..."><?= htmlspecialchars($item['description'] ?? $item['procedure_description'] ?? '') ?></textarea>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <!-- RIGHT: LIVE PREVIEW + BILL INFO -->
            <div>
                
                <!-- LIVE PREVIEW -->
                <div class="live-preview">
                    <div class="live-preview-title">
                        <i class="fas fa-calculator"></i> Live Preview
                    </div>
                    <div class="live-preview-row">
                        <span class="lp-label">Unit Price:</span>
                        <span class="lp-value" id="preview_price"><?= $currency ?> <?= number_format((float)$item['procedure_price'], 0) ?></span>
                    </div>
                    <div class="live-preview-row">
                        <span class="lp-label">Quantity:</span>
                        <span class="lp-value" id="preview_qty"><?= (int)($item['quantity'] ?? 1) ?></span>
                    </div>
                    <div class="live-preview-row">
                        <span class="lp-label">Subtotal:</span>
                        <span class="lp-value" id="preview_subtotal"><?= $currency ?> <?= number_format((float)$item['procedure_price'] * (int)($item['quantity'] ?? 1), 0) ?></span>
                    </div>
                    <div class="live-preview-row">
                        <span class="lp-label">Discount:</span>
                        <span class="lp-value" id="preview_discount" style="color:var(--danger);">-<?= $currency ?> <?= number_format((float)($item['item_discount'] ?? 0), 0) ?></span>
                    </div>
                    <div class="live-preview-row highlight">
                        <span class="lp-label">New Total:</span>
                        <span class="lp-value" id="preview_total"><?= $currency ?> <?= number_format((float)$item['procedure_price'] * (int)($item['quantity'] ?? 1) - (float)($item['item_discount'] ?? 0), 0) ?></span>
                    </div>
                </div>
                
                <!-- ITEM INFO -->
                <div class="info-card">
                    <div class="card-header" style="background:linear-gradient(135deg,#64748B,#475569);">
                        <i class="fas fa-info-circle"></i> Item Info
                    </div>
                    <div class="card-body" style="padding:14px 18px;">
                        <div class="info-row">
                            <span class="label"><i class="fas fa-hashtag"></i> Item ID</span>
                            <span class="value mono">#<?= $item_id ?></span>
                        </div>
                        <?php if (!empty($item['procedure_code'])): ?>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-tag"></i> Code</span>
                            <span class="value mono"><?= htmlspecialchars($item['procedure_code']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($item['equipment_batch'])): ?>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-barcode"></i> Batch</span>
                            <span class="value mono"><?= htmlspecialchars($item['equipment_batch']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($item['equipment_supplier'])): ?>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-truck"></i> Supplier</span>
                            <span class="value"><?= htmlspecialchars($item['equipment_supplier']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-user"></i> Patient</span>
                            <span class="value"><?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?></span>
                        </div>
                        <?php if (!empty($item['visit_number'])): ?>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-calendar-check"></i> Visit</span>
                            <span class="value mono"><?= htmlspecialchars($item['visit_number']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- BILL SUMMARY -->
                <?php if (!empty($item['bill_id'])): ?>
                <div class="bill-summary">
                    <div class="bill-summary-title">
                        <i class="fas fa-file-invoice-dollar"></i> Related Bill
                    </div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--purple);margin-bottom:8px;font-family:var(--font-mono);">
                        <?= htmlspecialchars($item['bill_number']) ?>
                    </div>
                    <div class="bill-summary-grid">
                        <div class="bill-summary-item total">
                            <div class="bs-label">Bill Total</div>
                            <div class="bs-value"><?= $currency ?> <?= number_format($item['bill_total'] ?? 0, 0) ?></div>
                        </div>
                        <div class="bill-summary-item paid">
                            <div class="bs-label">Paid</div>
                            <div class="bs-value"><?= $currency ?> <?= number_format($item['bill_paid'] ?? 0, 0) ?></div>
                        </div>
                        <div class="bill-summary-item balance">
                            <div class="bs-label">Balance</div>
                            <div class="bs-value"><?= $currency ?> <?= number_format($item['bill_balance'] ?? 0, 0) ?></div>
                        </div>
                        <div class="bill-summary-item">
                            <div class="bs-label">Status</div>
                            <div class="bs-value" style="font-size:0.75rem;text-transform:uppercase;">
                                <?= htmlspecialchars($item['bill_status'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:10px;font-size:0.68rem;color:var(--purple);font-weight:600;">
                        <i class="fas fa-info-circle"></i> Bill itarekebishwa automatically baada ya update
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <!-- ACTION BAR -->
        <div class="action-bar">
            <a href="view_procedure.php?id=<?= $item_id ?>&bill_item_id=<?= $bill_item_row_id ?>&type=<?= $item['item_type'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-success" id="submitBtn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
        
    </form>

</main>

<script>
// ================================================================
// LIVE PREVIEW
// ================================================================
function updatePreview() {
    var price = parseFloat(document.getElementById('input_price').value) || 0;
    var qty = parseInt(document.getElementById('input_quantity').value) || 1;
    var discount = parseFloat(document.getElementById('input_discount').value) || 0;
    
    var subtotal = price * qty;
    var total = subtotal - discount;
    if (total < 0) total = 0;
    
    document.getElementById('preview_price').textContent = '<?= $currency ?> ' + price.toLocaleString();
    document.getElementById('preview_qty').textContent = qty;
    document.getElementById('preview_subtotal').textContent = '<?= $currency ?> ' + subtotal.toLocaleString();
    document.getElementById('preview_discount').textContent = '-' + '<?= $currency ?> ' + discount.toLocaleString();
    document.getElementById('preview_total').textContent = '<?= $currency ?> ' + total.toLocaleString();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});

// Confirm before submit
document.getElementById('editForm').addEventListener('submit', function(e) {
    if (!confirm('Save changes to this <?= strtolower($item_label) ?>?\n\nBill itarekebishwa automatically.')) {
        e.preventDefault();
        return false;
    }
    
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

console.log('%c✏️ Edit <?= $item_label ?> - Admin', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c📝 <?= htmlspecialchars($item['procedure_name']) ?>', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c💰 Current: <?= $currency ?> <?= number_format((float)$item['procedure_price'], 0) ?>', 'font-size:13px;color:#0B5ED7;');
<?php if (!empty($item['bill_id'])): ?>
console.log('%c📄 Bill: <?= htmlspecialchars($item['bill_number']) ?>', 'font-size:13px;color:#7C3AED;font-weight:bold;');
<?php endif; ?>
</script>

</body>
</html>