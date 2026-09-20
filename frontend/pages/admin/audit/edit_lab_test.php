<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_lab_test.php
// ADMIN AUDIT - EDIT LAB TEST (FIXED)
// ✅ Edit test details, result, status, price
// ✅ Auto-update bill if PAID (uses b.visit_id not bi.visit_id)
// ✅ BLUE THEME (kama lab_tests.php)
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

$test_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($test_id <= 0) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_lab_test') {
    try {
        $db->beginTransaction();
        
        // Get OLD data for audit log
        $stmt = $db->prepare("SELECT test_name, test_price, status FROM lab_tests WHERE id = ?");
        $stmt->execute([$test_id]);
        $old_test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Form data
        $test_name = trim($_POST['test_name'] ?? '');
        $test_type = trim($_POST['test_type'] ?? '');
        $sample_type = trim($_POST['sample_type'] ?? '');
        $lab_technician_id = !empty($_POST['lab_technician_id']) ? (int)$_POST['lab_technician_id'] : null;
        $test_date = $_POST['test_date'] ?? date('Y-m-d');
        $test_price = parseMoney($_POST['test_price'] ?? '0');
        $status = $_POST['status'] ?? 'pending';
        $results = trim($_POST['results'] ?? '');
        $formatted_result = trim($_POST['formatted_result'] ?? '');
        $reference_range = trim($_POST['reference_range'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // ============================================================
        // ✅ UPDATE LAB TEST
        // ============================================================
        $sql = "UPDATE lab_tests SET 
            test_name = ?,
            test_type = ?,
            sample_type = ?,
            lab_technician_id = ?,
            test_date = ?,
            test_price = ?,
            status = ?,
            results = ?,
            formatted_result = ?,
            reference_range = ?,
            notes = ?,
            updated_at = NOW()
            WHERE id = ?";
        
        $db->prepare($sql)->execute([
            $test_name,
            $test_type,
            $sample_type,
            $lab_technician_id,
            $test_date,
            $test_price,
            $status,
            $results,
            $formatted_result,
            $reference_range,
            $notes,
            $test_id
        ]);
        
        // ============================================================
        // ✅ AUTO-UPDATE BILL KAMA TEST ILIKUWA/INAKUWA PAID
        // ✅ FIXED: Use b.visit_id (from bills) not bi.visit_id
        // ============================================================
        $bill_updated = false;
        
        // Fetch current test (to get visit_id)
        $stmt = $db->prepare("SELECT * FROM lab_tests WHERE id = ?");
        $stmt->execute([$test_id]);
        $current_test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_test && !empty($current_test['visit_id'])) {
            // ✅ Find related bill item via bills.visit_id
            $sql_bill = "SELECT bi.*, 
                                b.id as bill_id,
                                b.bill_number, 
                                b.visit_id as bill_visit_id,
                                b.total_amount, 
                                b.subtotal, 
                                b.paid_amount, 
                                b.balance,
                                b.total_discount, 
                                b.discount_amount, 
                                b.premium_amount, 
                                b.status as bill_status
                         FROM bill_items bi
                         INNER JOIN bills b ON bi.bill_id = b.id
                         WHERE bi.item_type = 'lab_test'
                           AND b.visit_id = ?
                           AND (
                               bi.reference_id = ?
                               OR bi.item_name = ?
                               OR bi.item_name LIKE ?
                           )
                         ORDER BY bi.id DESC
                         LIMIT 1";
            
            $stmt = $db->prepare($sql_bill);
            $stmt->execute([
                $current_test['visit_id'],
                $test_id,
                $old_test['test_name'] ?? '',
                '%' . ($old_test['test_name'] ?? '') . '%'
            ]);
            $related_bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($related_bill_item) {
                $bill_id = (int)$related_bill_item['bill_id'];
                $old_item_price = (float)($related_bill_item['total_price'] ?? 0);
                $old_item_discount = (float)($related_bill_item['discount_amount'] ?? 0);
                
                // Update bill_item
                $new_item_total = $test_price;
                $new_item_final = $new_item_total - $old_item_discount;
                if ($new_item_final < 0) $new_item_final = 0;
                
                $sql_update_item = "UPDATE bill_items SET 
                    item_name = ?,
                    unit_price = ?,
                    total_price = ?,
                    final_price = ?,
                    updated_at = NOW()
                    WHERE id = ?";
                $db->prepare($sql_update_item)->execute([
                    $test_name,
                    $test_price,
                    $new_item_total,
                    $new_item_final,
                    $related_bill_item['id']
                ]);
                
                // Recalculate bill
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
                $bill_discount = (float)($related_bill_item['discount_amount'] ?? 0);
                $premium = (float)($related_bill_item['premium_amount'] ?? 0);
                $new_total_discount = $new_items_discount + $bill_discount;
                
                // ✅ Formula: Subtotal - Discount + Premium
                $new_total_amount = $new_subtotal - $new_total_discount + $premium;
                if ($new_total_amount < 0) $new_total_amount = 0;
                
                $paid = (float)($related_bill_item['paid_amount'] ?? 0);
                $new_balance = $new_total_amount - $paid;
                if ($new_balance < 0) $new_balance = 0;
                
                $new_status = 'pending';
                if ($new_balance <= 0 && $new_total_amount > 0) {
                    $new_status = 'paid';
                } elseif ($paid > 0 && $new_balance > 0) {
                    $new_status = 'partial';
                }
                
                $sql_update_bill = "UPDATE bills SET 
                    subtotal = ?,
                    total_discount = ?,
                    total_amount = ?,
                    balance = ?,
                    status = ?,
                    updated_at = NOW()
                    WHERE id = ?";
                $db->prepare($sql_update_bill)->execute([
                    $new_subtotal,
                    $new_total_discount,
                    $new_total_amount,
                    $new_balance,
                    $new_status,
                    $bill_id
                ]);
                
                $bill_updated = true;
            }
        }
        
        // ============================================================
        // AUDIT LOG
        // ============================================================
        try {
            $changes = [];
            if ($old_test) {
                if ($old_test['test_name'] !== $test_name) {
                    $changes[] = "Name: " . $old_test['test_name'] . " → " . $test_name;
                }
                if ((float)$old_test['test_price'] != $test_price) {
                    $changes[] = "Price: " . $currency . " " . number_format($old_test['test_price'], 0) . " → " . $currency . " " . number_format($test_price, 0);
                }
                if ($old_test['status'] !== $status) {
                    $changes[] = "Status: " . $old_test['status'] . " → " . $status;
                }
            }
            
            $log_details = "Edited lab test: " . $test_name . " (ID: $test_id)";
            if (!empty($changes)) $log_details .= " | " . implode(' | ', $changes);
            if ($bill_updated) $log_details .= " | Bill auto-updated";
            
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'edit_lab_test', ?, ?, NOW())")
               ->execute([
                   $user_id,
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                   $log_details,
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $alert_message = "Lab test updated successfully!" . ($bill_updated ? " Bill has been auto-updated." : "");
        $alert_type = 'success';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $alert_message = "Error updating lab test: " . $e->getMessage();
        $alert_type = 'error';
    }
}

// ================================================================
// GET LAB TEST DETAILS
// ================================================================
$test = null;
try {
    $sql = "SELECT 
        lt.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        doc.full_name as doctor_name,
        tech.full_name as lab_technician_name,
        recv.full_name as received_by_name,
        b.name as branch_name,
        v.visit_number,
        v.visit_date
    FROM lab_tests lt
    LEFT JOIN patients pat ON lt.patient_id = pat.id
    LEFT JOIN users doc ON lt.doctor_id = doc.id
    LEFT JOIN users tech ON lt.lab_technician_id = tech.id
    LEFT JOIN users recv ON lt.performed_by = recv.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    WHERE lt.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Lab test fetch error: " . $e->getMessage());
}

if (!$test) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET LAB TECHNICIANS
// ================================================================
$lab_technicians = [];
try {
    $stmt = $db->query("SELECT id, full_name FROM users WHERE role = 'laboratory' AND status = 'active' ORDER BY full_name");
    $lab_technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// ✅ GET RELATED BILL (kama paid) - FIXED: use b.visit_id
// ================================================================
$related_bill = null;
$related_bill_item = null;
$is_paid = in_array($test['status'], ['completed', 'paid']);

try {
    if (!empty($test['visit_id'])) {
        $sql = "SELECT bi.*, 
                       b.id as bill_id,
                       b.bill_number, 
                       b.visit_id as bill_visit_id,
                       b.total_amount, 
                       b.subtotal, 
                       b.paid_amount, 
                       b.balance,
                       b.total_discount, 
                       b.discount_amount, 
                       b.premium_amount, 
                       b.status as bill_status
                FROM bill_items bi
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE bi.item_type = 'lab_test'
                  AND b.visit_id = ?
                  AND (
                      bi.reference_id = ?
                      OR bi.item_name = ?
                      OR bi.item_name LIKE ?
                  )
                ORDER BY bi.id DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $test['visit_id'],
            $test_id,
            $test['test_name'],
            '%' . $test['test_name'] . '%'
        ]);
        $related_bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($related_bill_item) {
            $related_bill = [
                'id' => $related_bill_item['bill_id'],
                'bill_number' => $related_bill_item['bill_number'],
                'total_amount' => $related_bill_item['total_amount'],
                'subtotal' => $related_bill_item['subtotal'],
                'paid_amount' => $related_bill_item['paid_amount'],
                'balance' => $related_bill_item['balance'],
                'status' => $related_bill_item['bill_status'],
            ];
        }
    }
} catch (Exception $e) {
    error_log("Related bill fetch error: " . $e->getMessage());
}

// HELPERS
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

$patient_age = calculateAge($test['date_of_birth']);

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
    <title>Edit Lab Test - <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></title>
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
.money-number, .money-cell, .font-mono, .money-input, .mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ALERT */
.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert i { font-size: 1.1rem; }

/* PAGE HEADER - WARNING THEME */
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

/* RESULT BOX */
.result-box { border-radius: 14px; border: 2px solid var(--success); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; background: var(--bg-card); }
.result-box .result-header { padding: 14px 20px; background: linear-gradient(135deg, #059669, #047857); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.result-box .result-header i { color: #A7F3D0; }
.result-box .result-body { padding: 20px 22px; }

/* RELATED BILL BOX */
.bill-box { border-radius: 14px; border: 2px solid var(--purple); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; background: var(--bg-card); }
.bill-box .bill-header { padding: 14px 20px; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.bill-box .bill-header i { color: #C4B5FD; }
.bill-box .bill-body { padding: 16px 20px; }
.bill-totals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.bill-total-item { background: var(--bg-body); border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border-color); text-align: center; }
.bill-total-item .bt-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); font-weight: 800; margin-bottom: 4px; }
.bill-total-item .bt-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; color: var(--text-primary); letter-spacing: -0.02em; }
.bill-total-item.total .bt-value { color: var(--primary); }
.bill-total-item.paid .bt-value { color: var(--success); }
.bill-total-item.balance .bt-value { color: var(--danger); }

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

/* RESPONSIVE */
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .form-card .card-body { padding: 16px 18px; }
    .patient-avatar { width: 48px; height: 48px; font-size: 1.2rem; }
    .patient-name { font-size: 0.95rem; }
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
                if (el) { 
                    el.style.transition = 'all 0.5s ease'; 
                    el.style.opacity = '0'; 
                    setTimeout(function() { el.remove(); }, 500); 
                }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Lab Test
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($test['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hashtag"></i> Test #<?= $test['id'] ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_lab_test.php?id=<?= $test_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ✅ PAID NOTICE -->
    <?php if ($is_paid && $related_bill): ?>
    <div class="info-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>This lab test is PAID.</strong> 
            It is linked to bill <strong><?= htmlspecialchars($related_bill['bill_number']) ?></strong>. 
            Changing the <strong>price</strong> will automatically <strong>update the bill totals</strong> 
            (subtotal, discount, total amount, balance).
        </div>
    </div>
    <?php elseif ($is_paid && !$related_bill): ?>
    <div class="info-notice">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>This lab test is PAID</strong> but no related bill item was found. 
            Price changes will not affect any bill.
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="editLabTestForm">
        <input type="hidden" name="action" value="update_lab_test">

        <!-- PATIENT INFO CARD -->
        <?php if (!empty($test['patient_name'])): ?>
        <div class="form-card">
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($test['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="patient-name"><?= htmlspecialchars($test['patient_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($test['patient_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($test['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($test['patient_gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($test['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($test['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($test['visit_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-clipboard-check"></i> Visit: <?= htmlspecialchars($test['visit_number']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TEST INFO CARD -->
        <div class="form-card">
            <div class="card-header">
                <span class="title">
                    <i class="fas fa-flask"></i>
                    Test Information
                </span>
                <span class="meta">
                    <i class="fas fa-clock"></i>
                    Created: <?= date('d M Y, H:i', strtotime($test['created_at'])) ?>
                </span>
            </div>
            
            <div class="card-body">
                <div class="form-grid">
                    
                    <!-- TEST NAME -->
                    <div class="form-group full-width">
                        <label>
                            <i class="fas fa-microscope"></i> 
                            Test Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="test_name" 
                               value="<?= htmlspecialchars($test['test_name'] ?? '') ?>" 
                               placeholder="e.g. Complete Blood Count (CBC)"
                               required>
                    </div>
                    
                    <!-- TEST TYPE -->
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Test Type</label>
                        <input type="text" 
                               name="test_type" 
                               value="<?= htmlspecialchars($test['test_type'] ?? '') ?>" 
                               placeholder="e.g. Hematology, Biochemistry...">
                    </div>
                    
                    <!-- SAMPLE TYPE -->
                    <div class="form-group">
                        <label><i class="fas fa-vial"></i> Sample Type</label>
                        <input type="text" 
                               name="sample_type" 
                               value="<?= htmlspecialchars($test['sample_type'] ?? '') ?>" 
                               placeholder="e.g. Blood, Urine, Stool...">
                    </div>
                    
                    <!-- LAB TECHNICIAN -->
                    <div class="form-group">
                        <label><i class="fas fa-user-nurse"></i> Lab Technician</label>
                        <select name="lab_technician_id">
                            <option value="">-- Not Assigned --</option>
                            <?php foreach ($lab_technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" 
                                    <?= ($test['lab_technician_id'] ?? 0) == $tech['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tech['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- TEST DATE -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Test Date</label>
                        <input type="date" 
                               name="test_date" 
                               value="<?= htmlspecialchars($test['test_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    
                    <!-- PRICE (MONEY FORMAT) -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-money-bill-wave"></i> 
                            Test Price <span class="required">*</span>
                        </label>
                        <div class="money-wrapper">
                            <span class="currency-tag"><?= $currency ?></span>
                            <input type="text" 
                                   name="test_price" 
                                   id="testPriceInput"
                                   class="money-input"
                                   value="<?= number_format((float)($test['test_price'] ?? 0), 0) ?>" 
                                   inputmode="numeric"
                                   autocomplete="off"
                                   required>
                        </div>
                    </div>
                    
                    <!-- STATUS -->
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Status <span class="required">*</span></label>
                        <select name="status" id="statusSelect" required>
                            <option value="pending" <?= ($test['status'] ?? '') === 'pending' ? 'selected' : '' ?>>
                                ⏳ Pending
                            </option>
                            <option value="in_progress" <?= ($test['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>
                                🔄 In Progress
                            </option>
                            <option value="completed" <?= ($test['status'] ?? '') === 'completed' ? 'selected' : '' ?>>
                                ✅ Completed
                            </option>
                            <option value="cancelled" <?= ($test['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>
                                ❌ Cancelled
                            </option>
                        </select>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- RESULT CARD -->
        <div class="result-box">
            <div class="result-header">
                <span><i class="fas fa-file-medical"></i> Test Result</span>
                <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                    <i class="fas fa-pencil-alt"></i> Editable
                </span>
            </div>
            <div class="result-body">
                <div class="form-grid">
                    
                    <!-- RESULT -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-clipboard-check"></i> Result</label>
                        <textarea name="results" 
                                  rows="3" 
                                  placeholder="Enter test result (e.g. Hemoglobin: 14.5 g/dL)"><?= htmlspecialchars($test['results'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- FORMATTED RESULT -->
                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Formatted Result</label>
                        <input type="text" 
                               name="formatted_result" 
                               value="<?= htmlspecialchars($test['formatted_result'] ?? '') ?>" 
                               placeholder="e.g. Normal, High, Low...">
                    </div>
                    
                    <!-- REFERENCE RANGE -->
                    <div class="form-group">
                        <label><i class="fas fa-chart-line"></i> Reference Range</label>
                        <input type="text" 
                               name="reference_range" 
                               value="<?= htmlspecialchars($test['reference_range'] ?? '') ?>" 
                               placeholder="e.g. 12.0 - 16.0 g/dL">
                    </div>
                    
                    <!-- NOTES -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea name="notes" 
                                  rows="2" 
                                  placeholder="Additional notes..."><?= htmlspecialchars($test['notes'] ?? '') ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- ✅ RELATED BILL INFO (kama paid) -->
        <?php if ($is_paid && $related_bill): ?>
        <div class="bill-box">
            <div class="bill-header">
                <span><i class="fas fa-file-invoice"></i> Related Bill: <?= htmlspecialchars($related_bill['bill_number']) ?></span>
                <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                    <i class="fas fa-sync-alt"></i> Auto-update on save
                </span>
            </div>
            <div class="bill-body">
                <div class="bill-totals-grid">
                    <div class="bill-total-item">
                        <div class="bt-label">Current Subtotal</div>
                        <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['subtotal'], 0) ?></div>
                    </div>
                    <div class="bill-total-item total">
                        <div class="bt-label">Current Total</div>
                        <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['total_amount'], 0) ?></div>
                    </div>
                    <div class="bill-total-item paid">
                        <div class="bt-label">Paid</div>
                        <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['paid_amount'], 0) ?></div>
                    </div>
                    <div class="bill-total-item balance">
                        <div class="bt-label">Balance</div>
                        <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['balance'], 0) ?></div>
                    </div>
                </div>
                
                <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--border-color);font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                    <i class="fas fa-info-circle" style="color:var(--purple);"></i>
                    Current contribution: 
                    <strong style="color:var(--purple);font-family:var(--font-mono);"><?= $currency ?> <?= number_format($related_bill_item['total_price'] ?? 0, 0) ?></strong>
                    → New: 
                    <strong id="newContribution" style="color:var(--success);font-family:var(--font-mono);"><?= $currency ?> <?= number_format($test['test_price'] ?? 0, 0) ?></strong>
                </div>
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
                        <?= $is_paid && $related_bill ? 'Bill will be auto-updated' : 'All changes will be logged' ?>
                    </div>
                </div>
            </div>
            <div class="action-buttons">
                <a href="view_lab_test.php?id=<?= $test_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn btn-back">
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
    
    if (decimalPart) return integerPart + '.' + decimalPart;
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
        
        updateNewContribution();
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '' || this.value === '.') {
            this.value = '0';
        } else {
            this.value = formatMoney(this.value);
        }
        updateNewContribution();
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
// UPDATE NEW CONTRIBUTION DISPLAY
// ================================================================
function updateNewContribution() {
    var priceInput = document.getElementById('testPriceInput');
    var newContrib = document.getElementById('newContribution');
    if (!priceInput || !newContrib) return;
    
    var value = priceInput.value.replace(/[^0-9.]/g, '');
    var num = parseFloat(value) || 0;
    
    newContrib.textContent = '<?= $currency ?> ' + num.toLocaleString('en-US', { maximumFractionDigits: 0 });
}

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
    updateNewContribution();
});

// ================================================================
// SUBMIT VALIDATION
// ================================================================
document.getElementById('editLabTestForm').addEventListener('submit', function(e) {
    var testName = document.querySelector('input[name="test_name"]').value.trim();
    var priceInput = document.getElementById('testPriceInput').value;
    var cleanPrice = priceInput.replace(/[^0-9.]/g, '');
    
    if (!testName) {
        e.preventDefault();
        alert('Test name is required!');
        return false;
    }
    
    if (!cleanPrice || parseFloat(cleanPrice) < 0) {
        e.preventDefault();
        alert('Please enter a valid price!');
        return false;
    }
    
    if (!confirm('Are you sure you want to save these changes?')) {
        e.preventDefault();
        return false;
    }
});

console.log('%c✏️ Edit Lab Test - Audit (FIXED)', 'font-size:18px;font-weight:bold;color:#F59E0B;');
console.log('%c✅ Test: <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c✅ Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c💰 Current Price: <?= $currency ?> <?= number_format($test['test_price'] ?? 0, 0) ?>', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
<?php if ($is_paid && $related_bill): ?>
console.log('%c⚠️ PAID - Bill <?= htmlspecialchars($related_bill['bill_number']) ?> will be auto-updated on save', 'font-size:12px;color:#DC2626;font-weight:bold;');
<?php endif; ?>
</script>

</body>
</html>