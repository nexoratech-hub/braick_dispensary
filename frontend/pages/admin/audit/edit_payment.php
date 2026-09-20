<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_payment.php
// ADMIN AUDIT - EDIT PAYMENT (V3 - WITH SCROLL BUTTONS)
// ✅ Edit payment details (amount, method, reference, notes)
// ✅ Auto-recalculate bill (paid_amount, balance, status)
// ✅ BILL ITEMS LIST with DELETE buttons
// ✅ Delete item → Recalculate bill (subtotal, discount, total, balance)
// ✅ Live money format
// ✅ SCROLL BUTTONS <> kwa items table
// ✅ BLUE THEME
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

$payment_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($payment_id <= 0) {
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
// HELPER: Parse money
// ================================================================
function parseMoney($value) {
    if (is_numeric($value)) return (float)$value;
    $clean = preg_replace('/[^0-9.]/', '', (string)$value);
    return (float)($clean ?: 0);
}

// ================================================================
// HELPER: Recalculate Bill (kutoka bill_items na payments)
// ================================================================
function recalculateFullBill($db, $bill_id) {
    // Get bill items totals
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_price), 0) as new_subtotal,
            COALESCE(SUM(discount_amount), 0) as new_items_discount
        FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $recalc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_subtotal = (float)($recalc['new_subtotal'] ?? 0);
    $new_items_discount = (float)($recalc['new_items_discount'] ?? 0);
    
    // Get bill discounts + premium
    $stmt = $db->prepare("SELECT discount_amount, pharmacy_discount, cashier_discount, premium_amount, paid_amount FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $bill_discount = (float)($bill_data['discount_amount'] ?? 0);
    $pharmacy_discount = (float)($bill_data['pharmacy_discount'] ?? 0);
    $cashier_discount = (float)($bill_data['cashier_discount'] ?? 0);
    $premium = (float)($bill_data['premium_amount'] ?? 0);
    
    // Total discount = items discount + bill discounts
    $new_total_discount = $new_items_discount + $bill_discount + $pharmacy_discount + $cashier_discount;
    
    // Formula: Subtotal - Discount + Premium
    $new_total_amount = $new_subtotal - $new_total_discount + $premium;
    if ($new_total_amount < 0) $new_total_amount = 0;
    
    // Get total paid from payments
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    $new_paid = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0);
    
    // New balance
    $new_balance = $new_total_amount - $new_paid;
    if ($new_balance < 0) $new_balance = 0;
    
    // Determine status
    $new_status = 'pending';
    if ($new_balance <= 0 && $new_total_amount > 0) {
        $new_status = 'paid';
    } elseif ($new_paid > 0 && $new_balance > 0) {
        $new_status = 'partial';
    }
    
    // Update bill
    $sql = "UPDATE bills SET 
        subtotal = ?, total_discount = ?, total_amount = ?,
        paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
        WHERE id = ?";
    $db->prepare($sql)->execute([
        $new_subtotal, $new_total_discount, $new_total_amount,
        $new_paid, $new_balance, $new_status, $bill_id
    ]);
    
    return [
        'subtotal' => $new_subtotal,
        'total_discount' => $new_total_discount,
        'total_amount' => $new_total_amount,
        'paid' => $new_paid,
        'balance' => $new_balance,
        'status' => $new_status
    ];
}

// ================================================================
// HANDLE UPDATE PAYMENT
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ============================================================
    // UPDATE PAYMENT
    // ============================================================
    if ($_POST['action'] === 'update_payment') {
        try {
            $db->beginTransaction();
            
            // GET OLD DATA for audit log
            $stmt = $db->prepare("SELECT receipt_number, amount, payment_method, bill_id FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $old_payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_payment) {
                throw new Exception("Payment not found!");
            }
            
            // FORM DATA
            $amount = parseMoney($_POST['amount'] ?? '0');
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $reference_number = trim($_POST['reference_number'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than 0!");
            }
            
            // UPDATE PAYMENT
            $sql = "UPDATE payments SET 
                amount = ?, payment_method = ?, reference_number = ?, 
                notes = ?, updated_at = NOW()
                WHERE id = ?";
            $db->prepare($sql)->execute([
                $amount, $payment_method, $reference_number, $notes, $payment_id
            ]);
            
            // ✅ AUTO-RECALCULATE BILL (kama payment ina bill)
            $bill_recalc = null;
            if (!empty($old_payment['bill_id'])) {
                $bill_recalc = recalculateFullBill($db, $old_payment['bill_id']);
            }
            
            // AUDIT LOG
            try {
                $changes = [];
                if ((float)$old_payment['amount'] != $amount) {
                    $changes[] = "Amount: " . $currency . " " . number_format($old_payment['amount'], 0) . " → " . $currency . " " . number_format($amount, 0);
                }
                if ($old_payment['payment_method'] !== $payment_method) {
                    $changes[] = "Method: " . $old_payment['payment_method'] . " → " . $payment_method;
                }
                
                $log_details = "Edited payment: " . ($old_payment['receipt_number'] ?? 'N/A') . " (ID: $payment_id)";
                if (!empty($changes)) $log_details .= " | " . implode(' | ', $changes);
                
                $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                              VALUES (?, ?, 'edit_payment', ?, ?, NOW())")
                   ->execute([
                       $user_id,
                       $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                       $log_details,
                       $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                   ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            $alert_message = "Payment updated successfully!" . ($bill_recalc ? " Bill recalculated." : "");
            $alert_type = 'success';
            
            header("refresh:2;url=edit_payment.php?id=$payment_id&branch=" . urlencode($selected_branch_id));
            exit;
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $alert_message = "Error updating payment: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    // ============================================================
    // ✅ DELETE BILL ITEM (from edit payment page)
    // ============================================================
    if ($_POST['action'] === 'delete_bill_item' && !empty($_POST['item_id'])) {
        try {
            $db->beginTransaction();
            
            $item_id = (int)$_POST['item_id'];
            
            // Get item info + bill_id
            $stmt = $db->prepare("SELECT bi.item_name, bi.total_price, bi.bill_id, b.bill_number 
                                  FROM bill_items bi
                                  INNER JOIN bills b ON bi.bill_id = b.id
                                  WHERE bi.id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception("Bill item not found!");
            }
            
            $bill_id = $item['bill_id'];
            $bill_number = $item['bill_number'];
            $item_name = $item['item_name'];
            $item_price = $item['total_price'];
            
            // Delete bill item
            $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$item_id]);
            
            // Check remaining items
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
            $stmt->execute([$bill_id]);
            $remaining = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            
            $bill_deleted = false;
            if ($remaining === 0) {
                // Delete bill entirely (with payments)
                $db->prepare("DELETE FROM payments WHERE bill_id = ?")->execute([$bill_id]);
                $db->prepare("DELETE FROM bills WHERE id = ?")->execute([$bill_id]);
                $bill_deleted = true;
            } else {
                // Recalculate bill
                recalculateFullBill($db, $bill_id);
            }
            
            // Audit log
            try {
                $log = "Deleted bill item from edit_payment: " . $item_name . " (ID: $item_id, Price: " . $currency . " " . number_format($item_price, 0) . ") from bill " . $bill_number;
                if ($bill_deleted) $log .= " | Bill deleted (no items left)";
                
                $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                              VALUES (?, ?, 'delete_bill_item', ?, ?, NOW())")
                   ->execute([
                       $user_id,
                       $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                       $log,
                       $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                   ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            if ($bill_deleted) {
                $alert_message = "Item \"$item_name\" deleted. Bill has been removed (no items left).";
                $alert_type = 'success';
                header("refresh:2;url=revenue.php?branch=" . urlencode($selected_branch_id));
                exit;
            } else {
                $alert_message = "Item \"$item_name\" deleted successfully! Bill recalculated.";
                $alert_type = 'success';
            }
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $alert_message = "Error deleting item: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
}

// ================================================================
// GET PAYMENT DETAILS
// ================================================================
$payment = null;
try {
    $sql = "SELECT 
        p.*,
        b.bill_number,
        b.total_amount as bill_total,
        b.subtotal as bill_subtotal,
        b.discount_amount as bill_discount,
        b.pharmacy_discount,
        b.cashier_discount,
        b.premium_amount,
        b.total_discount as bill_total_discount,
        b.paid_amount as bill_paid,
        b.balance as bill_balance,
        b.status as bill_status,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        v.visit_number,
        u.full_name as received_by_name,
        u.role as received_by_role,
        br.name as branch_name
    FROM payments p
    LEFT JOIN bills b ON p.bill_id = b.id
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON p.received_by = u.id
    LEFT JOIN branches br ON p.branch_id = br.id
    WHERE p.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Payment fetch error: " . $e->getMessage());
}

if (!$payment) {
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET BILL ITEMS (kwa bill hiyo)
// ================================================================
$bill_items = [];
if (!empty($payment['bill_id'])) {
    try {
        $sql = "SELECT * FROM bill_items 
                WHERE bill_id = ? 
                AND status != 'cancelled'
                ORDER BY id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$payment['bill_id']]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// GET OTHER PAYMENTS FOR SAME BILL
// ================================================================
$other_payments = [];
$other_payments_total = 0;
if (!empty($payment['bill_id'])) {
    try {
        $sql = "SELECT 
            p.id, p.receipt_number, p.amount, p.received_at,
            u.full_name as received_by_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ? AND p.id != ?
        ORDER BY p.received_at ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$payment['bill_id'], $payment_id]);
        $other_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($other_payments as $op) {
            $other_payments_total += (float)($op['amount'] ?? 0);
        }
    } catch (Exception $e) {}
}

// HELPERS
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

$patient_age = calculateAge($payment['date_of_birth']);

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
    <title>Edit Payment - <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></title>
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
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
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
.money-number, .money-cell, .font-mono, .money-input, .mono { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

/* ALERT */
.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }

/* PAGE HEADER */
.page-header { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; }
.page-header .page-title i { font-size: 1.5rem; color: #FDE68A; }
.page-header .page-subtitle { color: rgba(255,255,255,0.9); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.2); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.15); }
.btn-header { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.32); transform: translateY(-2px); }

/* INFO NOTICE */
.info-notice { background: var(--warning-bg); border-left: 4px solid var(--warning); border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 10px; font-size: 0.78rem; color: #78350F; font-weight: 500; }
.info-notice i { color: var(--warning); font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
[data-theme="dark"] .info-notice { color: #FEF3C7; }

/* FORM CARD */
.form-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.form-card .card-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.form-card .card-header .title { color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.form-card .card-header .title i { color: #93C5FD; font-size: 1rem; }
.form-card .card-header .meta { color: rgba(255,255,255,0.9); font-size: 0.68rem; font-weight: 700; background: rgba(255,255,255,0.15); padding: 3px 10px; border-radius: 8px; }
.form-card .card-body { padding: 20px 22px; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }
.form-group label i { color: var(--primary); font-size: 0.7rem; }
.form-group label .required { color: var(--danger); font-weight: 900; }
.form-group input, .form-group select, .form-group textarea { padding: 10px 14px; border-radius: 9px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.85rem; font-weight: 600; outline: none; transition: all 0.3s ease; font-family: var(--font-primary); }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }
.form-group input[readonly] { background: var(--bg-body); cursor: not-allowed; color: var(--text-secondary); }
.form-group textarea { resize: vertical; min-height: 80px; font-weight: 500; line-height: 1.5; font-family: var(--font-primary); }

/* MONEY INPUT */
.money-wrapper { position: relative; display: flex; align-items: center; }
.money-wrapper .currency-tag { position: absolute; left: 14px; font-size: 0.78rem; font-weight: 800; color: var(--primary); pointer-events: none; font-family: var(--font-primary); z-index: 2; }
.money-wrapper input { padding-left: 52px !important; text-align: right; font-family: var(--font-mono) !important; font-weight: 800; letter-spacing: 0.02em; font-size: 0.95rem; color: var(--primary); }

/* PATIENT PROFILE */
.patient-profile { display: flex; align-items: center; gap: 14px; padding: 16px 20px; background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft)); border-bottom: 2px solid var(--border-color); }
.patient-avatar { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.patient-name { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 4px; }
.patient-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-item { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-card); padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.patient-meta-item i { color: var(--primary); font-size: 0.65rem; }

/* BILL SUMMARY */
.bill-summary { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--purple); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.bill-summary .bs-header { padding: 14px 20px; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.bill-summary .bs-header i { color: #C4B5FD; }
.bill-summary .bs-body { padding: 16px 20px; }
.bill-totals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.bill-total-item { background: var(--bg-body); border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border-color); text-align: center; transition: all 0.3s ease; }
.bill-total-item.new-value { border-color: var(--success); background: var(--success-bg); }
.bill-total-item .bt-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); font-weight: 800; margin-bottom: 4px; }
.bill-total-item .bt-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; color: var(--text-primary); letter-spacing: -0.02em; }
.bill-total-item.total .bt-value { color: var(--primary); }
.bill-total-item.paid .bt-value { color: var(--success); }
.bill-total-item.balance .bt-value { color: var(--danger); }

.bill-preview-note { margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color); font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; gap: 6px; }
.bill-preview-note strong { color: var(--success); font-family: var(--font-mono); }

/* ✅ BILL ITEMS TABLE WITH DELETE + SCROLL */
.bill-items-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--primary); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.bill-items-card .bi-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.bill-items-card .bi-header i { color: #93C5FD; }
.bill-items-card .bi-header .count { font-size: 0.7rem; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 10px; }
.bill-items-card .bi-header .header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ✅ SCROLL BUTTONS <> */
.scroll-buttons { display: inline-flex; gap: 4px; background: rgba(255,255,255,0.15); border-radius: 10px; padding: 4px; border: 1px solid rgba(255,255,255,0.25); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-scroll { width: 34px; height: 34px; border-radius: 8px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.15); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; transition: all 0.25s ease; }
.btn-scroll:hover { background: rgba(255,255,255,0.35); transform: scale(1.1); border-color: rgba(255,255,255,0.5); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.btn-scroll:active { transform: scale(0.92); }

/* ✅ SCROLL WRAPPER */
.items-scroll-wrapper { overflow-x: auto; scroll-behavior: smooth; position: relative; }
.items-scroll-wrapper::-webkit-scrollbar { height: 10px; }
.items-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; margin: 0 8px; }
.items-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(90deg, #0B5ED7, #3B82F6); border-radius: 10px; border: 2px solid var(--bg-card); }
.items-scroll-wrapper::-webkit-scrollbar-thumb:hover { background: linear-gradient(90deg, #0A4CA8, #0B5ED7); }

.bill-items-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 1000px; }
.bill-items-table thead th { text-align: left; padding: 9px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.bill-items-table tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; font-weight: 500; }
.bill-items-table tbody tr:hover td { background: var(--primary-bg); }
.bill-items-table tbody tr:last-child td { border-bottom: none; }
.bill-items-table tbody tr.removing { opacity: 0.3; text-decoration: line-through; }

.item-type-badge { display: inline-block; padding: 2px 7px; border-radius: 5px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; background: var(--primary-bg); color: var(--primary); white-space: nowrap; }
.item-type-badge.medication { background: #FEF3C7; color: #D97706; }
.item-type-badge.lab_test { background: #DBEAFE; color: #1E40AF; }
.item-type-badge.consultation { background: #D1FAE5; color: #059669; }
.item-type-badge.procedure { background: #CCFBF1; color: #0D9488; }
.item-type-badge.registration { background: #F1F5F9; color: #64748B; }
.item-type-badge.equipment { background: #EDE9FE; color: #7C3AED; }

.btn-delete-item { width: 30px; height: 30px; border-radius: 8px; background: rgba(220, 38, 38, 0.12); color: #DC2626; border: 2px solid transparent; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; transition: all 0.2s ease; }
.btn-delete-item:hover { background: #DC2626; color: white; transform: scale(1.1); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); }

/* ✅ SCROLL HINT */
.scroll-hint { padding: 8px 16px; background: var(--primary-bg); border-top: 1px solid var(--border-color); text-align: center; font-size: 0.68rem; color: var(--text-secondary); font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; }
.scroll-hint i { color: var(--primary); font-size: 0.75rem; }
.scroll-hint kbd { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; padding: 1px 6px; font-family: var(--font-mono); font-size: 0.65rem; color: var(--primary); font-weight: 800; }

/* OTHER PAYMENTS */
.other-payments { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--success); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.other-payments .op-header { padding: 12px 20px; background: linear-gradient(135deg, #059669, #047857); color: white; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.other-payments .op-header i { color: #A7F3D0; }
.other-payments .op-body { padding: 0; }
.op-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 20px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; }
.op-item:last-child { border-bottom: none; }
.op-item .op-left { display: flex; align-items: center; gap: 10px; flex: 1; }
.op-item .op-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #059669, #34D399); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.65rem; text-transform: uppercase; flex-shrink: 0; }
.op-item .op-info { display: flex; flex-direction: column; gap: 2px; }
.op-item .op-receipt { font-size: 0.72rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono); }
.op-item .op-received { font-size: 0.65rem; color: var(--text-secondary); font-weight: 600; }
.op-item .op-amount { font-family: var(--font-mono); font-weight: 900; font-size: 0.85rem; color: var(--success); }
.op-item .op-date { font-size: 0.6rem; color: var(--text-secondary); font-weight: 600; }

/* ACTION BAR */
.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: sticky; bottom: 20px; z-index: 10; }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-save { background: linear-gradient(135deg, #059669, #047857); color: white; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
.btn-save:hover { box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5); }
.btn-back { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-back:hover { border-color: var(--primary); color: var(--primary); }
.btn-view { background: var(--bg-card); color: var(--primary); border: 2px solid var(--primary); }
.btn-view:hover { background: var(--primary); color: white; }

/* MODAL */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(6px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--bg-card); border-radius: 20px; max-width: 440px; width: 100%; padding: 30px; box-shadow: var(--shadow-xl); animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); text-align: center; }
@keyframes modalPop { 0% { opacity: 0; transform: scale(0.8) translateY(20px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
.modal-icon { width: 68px; height: 68px; border-radius: 50%; background: var(--danger-bg); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 16px; animation: iconPulse 1.5s infinite; }
@keyframes iconPulse { 0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); } 50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); } }
.modal-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; color: var(--text-primary); }
.modal-text { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 22px; line-height: 1.7; }
.modal-text strong { color: var(--primary); background: var(--primary-bg); padding: 2px 8px; border-radius: 6px; font-family: var(--font-mono); font-weight: 700; }
.modal-warning { color: var(--danger); font-weight: 700; display: block; margin-top: 6px; font-size: 0.78rem; }
.modal-actions { display: flex; gap: 10px; justify-content: center; }
.modal-btn { padding: 10px 24px; border-radius: 11px; font-weight: 700; font-size: 0.82rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: #CBD5E1; transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .form-card .card-body { padding: 16px 18px; }
    .patient-avatar { width: 48px; height: 48px; font-size: 1.2rem; }
    .patient-name { font-size: 0.95rem; }
    .bill-items-table { font-size: 0.7rem; }
    .bill-items-table thead th, .bill-items-table tbody td { padding: 7px 8px; }
    .btn-scroll { width: 30px; height: 30px; font-size: 0.75rem; }
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
        <?php if ($alert_type === 'error'): ?>
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
                Edit Payment
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-receipt"></i>
                <strong><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($payment['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hashtag"></i> Payment #<?= $payment['id'] ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_payment.php?id=<?= $payment_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- INFO NOTICE -->
    <?php if (!empty($payment['bill_id'])): ?>
    <div class="info-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>This payment is linked to bill <?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?>.</strong> 
            Changing the <strong>amount</strong> will automatically <strong>recalculate the bill</strong>. 
            Deleting a <strong>bill item</strong> will also recalculate the bill.
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="editPaymentForm">
        <input type="hidden" name="action" value="update_payment">

        <!-- PATIENT INFO CARD -->
        <?php if (!empty($payment['patient_name'])): ?>
        <div class="form-card">
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($payment['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="patient-name"><?= htmlspecialchars($payment['patient_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($payment['patient_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($payment['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($payment['patient_gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($payment['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($payment['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($payment['visit_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-clipboard-check"></i> Visit: <?= htmlspecialchars($payment['visit_number']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAYMENT INFO CARD -->
        <div class="form-card">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-receipt"></i>
                    Payment Information
                </span>
                <span class="meta">
                    <i class="fas fa-clock"></i>
                    Received: <?= date('d M Y, H:i', strtotime($payment['received_at'] ?? $payment['created_at'])) ?>
                </span>
            </div>
            
            <div class="card-body">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label><i class="fas fa-barcode"></i> Receipt Number</label>
                        <input type="text" 
                               value="<?= htmlspecialchars($payment['receipt_number'] ?? '') ?>" 
                               readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-money-bill-wave"></i> 
                            Amount Paid <span class="required">*</span>
                        </label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" 
                                   name="amount" 
                                   id="amountInput"
                                   class="money-input"
                                   value="<?= number_format((float)($payment['amount'] ?? 0), 0) ?>" 
                                   inputmode="numeric"
                                   autocomplete="off"
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Payment Method <span class="required">*</span></label>
                        <select name="payment_method" required>
                            <option value="cash" <?= ($payment['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>💰 Cash</option>
                            <option value="m-pesa" <?= ($payment['payment_method'] ?? '') === 'm-pesa' ? 'selected' : '' ?>>📱 M-Pesa</option>
                            <option value="airtel_money" <?= ($payment['payment_method'] ?? '') === 'airtel_money' ? 'selected' : '' ?>>📱 Airtel Money</option>
                            <option value="tigo_pesa" <?= ($payment['payment_method'] ?? '') === 'tigo_pesa' ? 'selected' : '' ?>>📱 Tigo Pesa</option>
                            <option value="halopesa" <?= ($payment['payment_method'] ?? '') === 'halopesa' ? 'selected' : '' ?>>📱 Halopesa</option>
                            <option value="bank" <?= ($payment['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>>🏦 Bank Transfer</option>
                            <option value="card" <?= ($payment['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>💳 Card</option>
                            <option value="insurance" <?= ($payment['payment_method'] ?? '') === 'insurance' ? 'selected' : '' ?>>🛡️ Insurance</option>
                            <option value="other" <?= ($payment['payment_method'] ?? '') === 'other' ? 'selected' : '' ?>>📝 Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-barcode"></i> Reference Number</label>
                        <input type="text" 
                               name="reference_number" 
                               value="<?= htmlspecialchars($payment['reference_number'] ?? '') ?>" 
                               placeholder="e.g. MPESA-ABC123, Bank ref...">
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea name="notes" 
                                  rows="3" 
                                  placeholder="Additional notes..."><?= htmlspecialchars($payment['notes'] ?? '') ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- BILL SUMMARY -->
        <?php if (!empty($payment['bill_id'])): ?>
        <div class="bill-summary">
            <div class="bs-header">
                <span><i class="fas fa-file-invoice"></i> Bill Summary: <?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?></span>
                <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                    <i class="fas fa-sync-alt"></i> Auto-recalculate on save
                </span>
            </div>
            <div class="bs-body">
                <div class="bill-totals-grid">
                    <div class="bill-total-item">
                        <div class="bt-label">Bill Total</div>
                        <div class="bt-value"><?= $currency ?> <?= number_format($payment['bill_total'] ?? 0, 0) ?></div>
                    </div>
                    <div class="bill-total-item">
                        <div class="bt-label">Other Payments</div>
                        <div class="bt-value" style="color:var(--purple);">
                            <?= $currency ?> <?= number_format($other_payments_total, 0) ?>
                        </div>
                    </div>
                    <div class="bill-total-item">
                        <div class="bt-label">This Payment</div>
                        <div class="bt-value" style="color:var(--primary);" id="previewThisPayment">
                            <?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?>
                        </div>
                    </div>
                    <div class="bill-total-item new-value paid">
                        <div class="bt-label">New Total Paid</div>
                        <div class="bt-value" id="previewNewPaid">
                            <?= $currency ?> <?= number_format(($other_payments_total + ($payment['amount'] ?? 0)), 0) ?>
                        </div>
                    </div>
                    <div class="bill-total-item new-value balance">
                        <div class="bt-label">New Balance</div>
                        <div class="bt-value" id="previewNewBalance">
                            <?php 
                                $preview_balance = ($payment['bill_total'] ?? 0) - ($other_payments_total + ($payment['amount'] ?? 0));
                                if ($preview_balance < 0) $preview_balance = 0;
                            ?>
                            <?= $currency ?> <?= number_format($preview_balance, 0) ?>
                        </div>
                    </div>
                </div>
                
                <div class="bill-preview-note">
                    <i class="fas fa-info-circle" style="color:var(--purple);"></i>
                    Preview updates as you type. Other payments: 
                    <strong style="color:var(--purple);font-family:var(--font-mono);"><?= $currency ?> <?= number_format($other_payments_total, 0) ?></strong>
                    → New total: <strong id="previewTotalInfo"><?= $currency ?> <?= number_format(($other_payments_total + ($payment['amount'] ?? 0)), 0) ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ✅ BILL ITEMS WITH DELETE + SCROLL BUTTONS -->
        <?php if (count($bill_items) > 0): ?>
        <div class="bill-items-card">
            <div class="bi-header">
                <span><i class="fas fa-list"></i> Bill Items (<?= count($bill_items) ?> items)</span>
                <div class="header-actions">
                    <!-- ✅ SCROLL BUTTONS <> -->
                    <div class="scroll-buttons" title="Scroll left / right">
                        <button type="button" class="btn-scroll" onclick="scrollItems('left')" title="Scroll Left (Alt+←)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="btn-scroll" onclick="scrollItems('right')" title="Scroll Right (Alt+→)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <span class="count">
                        <i class="fas fa-trash"></i> Delete item to remove
                    </span>
                </div>
            </div>
            
            <!-- ✅ SCROLL WRAPPER -->
            <div class="items-scroll-wrapper" id="itemsWrapper">
                <table class="bill-items-table" id="billItemsTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Item Name</th>
                            <th style="text-align:center;">Type</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Discount</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:center;width:80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $item_num = 1; foreach ($bill_items as $item): 
                            $item_total = (float)($item['total_price'] ?? 0);
                            $item_discount = (float)($item['discount_amount'] ?? 0);
                            $item_final = $item_total - $item_discount;
                            $item_type = strtolower($item['item_type'] ?? 'other');
                        ?>
                            <tr class="item-row" data-item-id="<?= $item['id'] ?>">
                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);" class="row-num"><?= $item_num++ ?></td>
                                <td>
                                    <div style="font-weight:700;font-size:0.78rem;"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></div>
                                    <?php if (!empty($item['description'])): ?>
                                        <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;"><?= htmlspecialchars($item['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="item-type-badge <?= htmlspecialchars($item_type) ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $item_type)) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                                    <?= (int)($item['quantity'] ?? 0) ?>
                                </td>
                                <td class="money-cell">
                                    <span class="currency-prefix" style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-primary);font-weight:600;"><?= $currency ?></span><?= number_format($item['unit_price'] ?? 0, 0) ?>
                                </td>
                                <td class="money-cell" style="color:<?= $item_discount > 0 ? 'var(--danger)' : 'var(--text-secondary)' ?>;">
                                    <?php if ($item_discount > 0): ?>
                                        - <span class="currency-prefix" style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-primary);font-weight:600;"><?= $currency ?></span><?= number_format($item_discount, 0) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="money-cell" style="color:var(--primary);">
                                    <span class="currency-prefix" style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-primary);font-weight:600;"><?= $currency ?></span><?= number_format($item_final, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= ($item['status'] ?? '') === 'paid' ? 'success' : 'warning' ?>" style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:8px;font-size:0.6rem;font-weight:800;text-transform:uppercase;background:<?= ($item['status'] ?? '') === 'paid' ? 'var(--success-bg)' : 'var(--warning-bg)' ?>;color:<?= ($item['status'] ?? '') === 'paid' ? 'var(--success)' : 'var(--warning)' ?>;">
                                        <?= strtoupper($item['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" 
                                            class="btn-delete-item" 
                                            onclick="confirmDeleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'] ?? 'N/A')) ?>', <?= (float)$item_final ?>)" 
                                            title="Delete this item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ✅ SCROLL HINT -->
            <div class="scroll-hint">
                <i class="fas fa-arrows-alt-h"></i>
                Use <kbd>‹</kbd> <kbd>›</kbd> buttons to scroll left/right
                <span style="opacity:0.5;">•</span>
                Keyboard: <kbd>Alt</kbd> + <kbd>←</kbd> / <kbd>→</kbd>
            </div>
        </div>
        <?php endif; ?>

        <!-- OTHER PAYMENTS -->
        <?php if (count($other_payments) > 0): ?>
        <div class="other-payments">
            <div class="op-header">
                <span><i class="fas fa-history"></i> Other Payments for this Bill (<?= count($other_payments) ?>)</span>
                <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                    Total: <?= $currency ?> <?= number_format($other_payments_total, 0) ?>
                </span>
            </div>
            <div class="op-body">
                <?php foreach ($other_payments as $op): 
                    $op_parts = explode(' ', trim($op['received_by_name'] ?? 'N/A'));
                    $op_initials = count($op_parts) >= 2 
                        ? strtoupper(substr($op_parts[0], 0, 1) . substr($op_parts[1], 0, 1))
                        : strtoupper(substr($op['received_by_name'] ?? 'NA', 0, 2));
                ?>
                    <div class="op-item">
                        <div class="op-left">
                            <div class="op-avatar"><?= htmlspecialchars($op_initials) ?></div>
                            <div class="op-info">
                                <span class="op-receipt">
                                    <i class="fas fa-receipt" style="font-size:0.6rem;opacity:0.7;"></i>
                                    <?= htmlspecialchars($op['receipt_number'] ?? 'N/A') ?>
                                </span>
                                <span class="op-received">
                                    By: <?= htmlspecialchars($op['received_by_name'] ?? 'N/A') ?>
                                </span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span class="op-amount">
                                <span style="font-size:0.6rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                                <?= number_format($op['amount'] ?? 0, 0) ?>
                            </span>
                            <div class="op-date">
                                <i class="fas fa-clock" style="font-size:0.55rem;"></i>
                                <?= date('d M Y, H:i', strtotime($op['received_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="action-info">
                <div class="info-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="info-text">
                    <div class="info-title">Save Changes</div>
                    <div class="info-sub">
                        <?= !empty($payment['bill_id']) ? 'Bill will be auto-recalculated' : 'Changes will be logged' ?>
                    </div>
                </div>
            </div>
            <div class="action-buttons">
                <a href="view_payment.php?id=<?= $payment_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view">
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

<!-- DELETE ITEM MODAL -->
<div class="modal-overlay" id="deleteItemModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete Bill Item?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong id="deleteItemName">#</strong><br>
            <span style="font-size:0.75rem;color:var(--danger);font-weight:700;">
                Amount: <span id="deleteItemPrice">0</span>
            </span>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> Bill will be recalculated (subtotal, total, balance)
            </span>
        </p>
        <form method="POST" id="deleteItemForm">
            <input type="hidden" name="action" value="delete_bill_item">
            <input type="hidden" name="item_id" id="deleteItemId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteItemModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// VARIABLES
// ================================================================
var OTHER_PAYMENTS_TOTAL = <?= (float)$other_payments_total ?>;
var BILL_TOTAL = <?= (float)($payment['bill_total'] ?? 0) ?>;
var CURRENCY = '<?= $currency ?>';
var HAS_BILL = <?= !empty($payment['bill_id']) ? 'true' : 'false' ?>;

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
    
    if (input.value && input.value !== '0') {
        input.value = formatMoney(input.value);
    }
    
    input.addEventListener('input', function(e) {
        var cursorPos = this.selectionStart;
        var oldLength = this.value.length;
        var formatted = formatMoney(this.value);
        this.value = formatted;
        var diff = formatted.length - oldLength;
        if (this.setSelectionRange) {
            this.setSelectionRange(cursorPos + diff, cursorPos + diff);
        }
        updateBillPreview();
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '' || this.value === '.') this.value = '0';
        else this.value = formatMoney(this.value);
        updateBillPreview();
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

// ================================================================
// ✅ SCROLL ITEMS TABLE (Left / Right)
// ================================================================
function scrollItems(direction) {
    var wrapper = document.getElementById('itemsWrapper');
    if (!wrapper) return;
    if (wrapper.scrollWidth <= wrapper.clientWidth) return;
    
    var scrollAmount = 350;
    var currentScroll = wrapper.scrollLeft;
    var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
    
    var targetScroll = direction === 'left' 
        ? Math.max(0, currentScroll - scrollAmount)
        : Math.min(maxScroll, currentScroll + scrollAmount);
    
    wrapper.scrollTo({
        left: targetScroll,
        behavior: 'smooth'
    });
    
    // Visual feedback
    var btns = document.querySelectorAll('.btn-scroll');
    btns.forEach(function(btn) {
        btn.style.transform = 'scale(0.9)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

// ✅ Keyboard shortcuts (Alt + Arrow)
document.addEventListener('keydown', function(e) {
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
// REALTIME BILL PREVIEW
// ================================================================
function updateBillPreview() {
    if (!HAS_BILL) return;
    
    var amountInput = document.getElementById('amountInput');
    if (!amountInput) return;
    
    var newAmount = parseMoney(amountInput.value);
    var newTotalPaid = OTHER_PAYMENTS_TOTAL + newAmount;
    var newBalance = BILL_TOTAL - newTotalPaid;
    if (newBalance < 0) newBalance = 0;
    
    var thisPay = document.getElementById('previewThisPayment');
    if (thisPay) thisPay.textContent = CURRENCY + ' ' + numberFormat(newAmount);
    
    var newPaid = document.getElementById('previewNewPaid');
    if (newPaid) newPaid.textContent = CURRENCY + ' ' + numberFormat(newTotalPaid);
    
    var newBal = document.getElementById('previewNewBalance');
    if (newBal) newBal.textContent = CURRENCY + ' ' + numberFormat(newBalance);
    
    var totalInfo = document.getElementById('previewTotalInfo');
    if (totalInfo) totalInfo.textContent = CURRENCY + ' ' + numberFormat(newTotalPaid);
}

// ================================================================
// ✅ DELETE ITEM MODAL
// ================================================================
function confirmDeleteItem(itemId, itemName, itemPrice) {
    document.getElementById('deleteItemId').value = itemId;
    document.getElementById('deleteItemName').textContent = itemName;
    document.getElementById('deleteItemPrice').textContent = CURRENCY + ' ' + numberFormat(itemPrice);
    document.getElementById('deleteItemModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteItemModal() {
    document.getElementById('deleteItemModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteItemModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteItemModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteItemModal();
});

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
    updateBillPreview();
});

// ================================================================
// SUBMIT VALIDATION
// ================================================================
document.getElementById('editPaymentForm').addEventListener('submit', function(e) {
    var amountInput = document.getElementById('amountInput').value;
    var cleanAmount = amountInput.replace(/[^0-9.]/g, '');
    
    if (!cleanAmount || parseFloat(cleanAmount) <= 0) {
        e.preventDefault();
        alert('Amount must be greater than 0!');
        return false;
    }
    
    if (HAS_BILL) {
        var newAmount = parseFloat(cleanAmount) || 0;
        var newTotal = OTHER_PAYMENTS_TOTAL + newAmount;
        if (newTotal > BILL_TOTAL) {
            if (!confirm('⚠️ WARNING: Total payments (' + CURRENCY + ' ' + numberFormat(newTotal) + ') exceed bill total (' + CURRENCY + ' ' + numberFormat(BILL_TOTAL) + ').\n\nContinue?')) {
                e.preventDefault();
                return false;
            }
        }
    }
    
    if (!confirm('Are you sure you want to save these changes?')) {
        e.preventDefault();
        return false;
    }
});

console.log('%c✏️ Edit Payment V3 - WITH SCROLL BUTTONS', 'font-size:18px; font-weight:bold; color:#F59E0B;');
console.log('%c✅ BLUE Theme (default)', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c✅ SCROLL BUTTONS <> kwenye header', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c⌨️ Alt + ← / → kwa keyboard scroll', 'font-size:12px; color:#7C3AED;');
console.log('%c✅ Receipt: <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>', 'font-size:12px; color:#34D399;');
console.log('%c💰 Amount: <?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?>', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c📋 Bill Items: <?= count($bill_items) ?>', 'font-size:12px; color:#7C3AED;');
console.log('%c🗑️ Delete items allowed (auto-recalculate bill)', 'font-size:12px; color:#DC2626; font-weight:bold;');
<?php if (!empty($payment['bill_id'])): ?>
console.log('%c⚠️ Bill <?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?> will be recalculated on save', 'font-size:12px; color:#DC2626;');
<?php endif; ?>
</script>

</body>
</html>