<?php
// ================================================================
// FILE: frontend/pages/admin/audit/delete_procedure.php
// ADMIN - DELETE PROCEDURE / EQUIPMENT (V2 - WITH REFUND SUPPORT)
// ✅ Inafanya kazi kwa procedure NA equipment
// ✅ Inafuta bill_items + recalculate bills
// ✅ Ina-rekodi REFUND kama paid > new_total
// ✅ Ina-log activity
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

$allowed_roles = ['admin'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error_message'] = "Only Admin can delete procedures.";
    header('Location: other_services.php?tab=procedures');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ================================================================
// PARAMETERS
// ================================================================
$reference_id = (int)($_GET['id'] ?? 0);
$bill_item_id = (int)($_GET['bill_item_id'] ?? 0);
$item_type = $_GET['type'] ?? 'procedure';
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($reference_id <= 0 && $bill_item_id <= 0) {
    $_SESSION['error_message'] = "Invalid item ID.";
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
// HELPER: Log Activity
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
// HELPER: Recalculate Bill with REFUND SUPPORT
// ================================================================
function recalculateBill($db, $bill_id, $user_id, $item_info = []) {
    // 1. Get new subtotal + item discount from bill_items
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
    
    // 2. Get current bill data
    $stmt = $db->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill_data) return null;
    
    $bill_discount = (float)($bill_data['discount_amount'] ?? 0);
    $premium = (float)($bill_data['premium_amount'] ?? 0);
    $old_paid = (float)($bill_data['paid_amount'] ?? 0);
    $patient_id = $bill_data['patient_id'];
    $branch_id = $bill_data['branch_id'];
    $bill_number = $bill_data['bill_number'];
    
    // 3. Compute new totals
    $new_total_discount = $new_items_discount + $bill_discount;
    $new_total_amount = $new_subtotal - $new_total_discount + $premium;
    if ($new_total_amount < 0) $new_total_amount = 0;
    
    // 4. Determine new paid + overpayment
    $new_paid = $old_paid;
    $overpayment = 0;
    $refund_created = false;
    $refund_receipt = null;
    
    if ($old_paid > $new_total_amount && $new_total_amount > 0) {
        // ✅ OVERPAYMENT! Patient amelipa zaidi ya bill mpya
        $overpayment = $old_paid - $new_total_amount;
        $new_paid = $new_total_amount;
        
        // ✅ Rekodi REFUND kama payment negative
        $refund_receipt = 'REFUND-' . date('Ymd') . '-' . rand(1000, 9999);
        
        try {
            $stmt_refund = $db->prepare("
                INSERT INTO payments 
                (receipt_number, bill_id, patient_id, amount, payment_method, notes, received_by, branch_id, received_at, updated_at)
                VALUES (?, ?, ?, ?, 'cash', ?, ?, ?, NOW(), NOW())
            ");
            $stmt_refund->execute([
                $refund_receipt,
                $bill_id,
                $patient_id,
                -$overpayment,
                "Auto REFUND: {$item_info['item_label']} '{$item_info['item_name']}' deleted. Bill recalculated. Overpayment: {$currency} " . number_format($overpayment, 0),
                $user_id,
                $branch_id
            ]);
            $refund_created = true;
        } catch (Exception $e) {
            error_log("Refund insert failed: " . $e->getMessage());
        }
    }
    
    // 5. Compute new balance + status
    $new_balance = $new_total_amount - $new_paid;
    if ($new_balance < 0) $new_balance = 0;
    
    $new_status = 'pending';
    if ($new_balance <= 0 && $new_total_amount > 0) $new_status = 'paid';
    elseif ($new_paid > 0 && $new_balance > 0) $new_status = 'partial';
    if ($new_total_amount <= 0) $new_status = 'cancelled';
    
    // 6. Update bill
    $stmt = $db->prepare("
        UPDATE bills SET 
            subtotal = ?, 
            total_discount = ?, 
            total_amount = ?,
            paid_amount = ?,
            balance = ?, 
            status = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $new_subtotal,
        $new_total_discount,
        $new_total_amount,
        $new_paid,
        $new_balance,
        $new_status,
        $bill_id
    ]);
    
    return [
        'new_subtotal' => $new_subtotal,
        'new_total' => $new_total_amount,
        'new_paid' => $new_paid,
        'new_balance' => $new_balance,
        'new_status' => $new_status,
        'overpayment' => $overpayment,
        'refund_created' => $refund_created,
        'refund_receipt' => $refund_receipt
    ];
}

// ================================================================
// FETCH ITEM
// ================================================================
$item = null;
try {
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
                   bi.status as item_status,
                   b.id as bill_id, b.bill_number,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number,
                   v.id as visit_db_id, v.visit_number
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON bi.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            WHERE bi.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bill_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
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
                   bi.status as item_status,
                   b.id as bill_id, b.bill_number,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number,
                   v.id as visit_db_id, v.visit_number
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON bi.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            WHERE bi.reference_id = ?
              AND bi.item_type IN ('procedure', 'equipment')
            ORDER BY bi.id DESC
            LIMIT 1
        ");
        $stmt->execute([$reference_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Fetch error: " . $e->getMessage();
    header('Location: other_services.php?tab=procedures');
    exit;
}

if (!$item) {
    $_SESSION['error_message'] = "Item not found.";
    header('Location: other_services.php?tab=procedures');
    exit;
}

$is_equipment = ($item['item_type'] ?? '') === 'equipment';
$item_label = $is_equipment ? 'Equipment' : 'Procedure';

// ================================================================
// PERFORM DELETE
// ================================================================
try {
    $db->beginTransaction();
    
    $proc_name = $item['procedure_name'];
    $bill_id = $item['bill_id'];
    $bill_number = $item['bill_number'];
    $item_total = (float)$item['procedure_total'];
    $actual_bill_item_id = $item['bill_item_row_id'];
    $actual_reference_id = $item['reference_id'];
    
    // 1. Delete from bill_items
    $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ?");
    $stmt->execute([$actual_bill_item_id]);
    
    // 2. Recalculate bill WITH REFUND SUPPORT
    $recalc_result = null;
    if ($bill_id > 0) {
        $recalc_result = recalculateBill($db, $bill_id, $user_id, [
            'item_label' => $item_label,
            'item_name' => $proc_name
        ]);
    }
    
    // 3. Delete from procedures OR medical_equipment
    if ($is_equipment) {
        // Equipment: HAIFUTWI kutoka medical_equipment (inventory item)
        // Kwa sababu inaweza kutumika mahali pengine
    } else {
        // Procedure: futa kutoka procedures table
        if ($actual_reference_id > 0) {
            $stmt = $db->prepare("DELETE FROM procedures WHERE id = ?");
            $stmt->execute([$actual_reference_id]);
        }
    }
    
    // 4. Log activity
    $log_details = "Deleted {$item_label}: '{$proc_name}' (Bill: {$bill_number}) | " .
        "Amount: {$currency} " . number_format($item_total, 0) . " | " .
        "Bill recalculated: Total → {$currency} " . number_format($recalc_result['new_total'] ?? 0, 0) . 
        ", Balance → {$currency} " . number_format($recalc_result['new_balance'] ?? 0, 0);
    
    if (!empty($recalc_result['overpayment']) && $recalc_result['overpayment'] > 0) {
        $log_details .= " | ⚠️ OVERPAYMENT: {$currency} " . number_format($recalc_result['overpayment'], 0) .
                        " | REFUND issued: " . $recalc_result['refund_receipt'];
    }
    
    logActivity(
        $db, $user_id, $item['branch_id'] ?? null, $item['patient_db_id'] ?? null,
        'bill_item_deleted',
        $log_details
    );
    
    $db->commit();
    
    // Success message
    $success_msg = "✅ {$item_label} '{$proc_name}' deleted successfully!";
    if ($recalc_result) {
        $success_msg .= " Bill #{$bill_number} updated (New Total: {$currency} " . number_format($recalc_result['new_total'], 0) . ")";
        if (!empty($recalc_result['overpayment']) && $recalc_result['overpayment'] > 0) {
            $success_msg .= " | ⚠️ REFUND: {$currency} " . number_format($recalc_result['overpayment'], 0) . 
                           " (Receipt: {$recalc_result['refund_receipt']})";
        }
    }
    
    $_SESSION['success_message'] = $success_msg;
    
    header('Location: other_services.php?tab=procedures&branch=' . $selected_branch_id . '&deleted=1');
    exit;
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
    header('Location: view_procedure.php?id=' . $reference_id . '&bill_item_id=' . $bill_item_id . '&type=' . $item_type . '&branch=' . $selected_branch_id);
    exit;
}