<?php
// ================================================================
// FILE: frontend/pages/admin/audit/delete_otc_item.php
// ADMIN - DELETE SINGLE OTC ITEM (POST ONLY)
// ✅ Inafuta item MOJA kutoka kwenye sale
// ✅ Inarudisha stock ya item hiyo PEKEE
// ✅ Ina-recalculate sale total
// ✅ Ina-log activity
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

$allowed_roles = ['admin'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error_message'] = "Only Admin can delete OTC items.";
    header('Location: other_services.php?tab=otc_bills');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: other_services.php?tab=otc_bills');
    exit;
}

$otc_item_id = (int)($_POST['otc_item_id'] ?? 0);
$sale_id = (int)($_POST['sale_id'] ?? 0);
$selected_branch_id = $_POST['branch'] ?? 'all';

if ($otc_item_id <= 0 || $sale_id <= 0) {
    $_SESSION['error_message'] = "Invalid item or sale ID.";
    header('Location: other_services.php?tab=otc_bills&branch=' . urlencode($selected_branch_id));
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

// HELPER: Log Activity
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
// FETCH ITEM + SALE
// ================================================================
$item = null;
$sale = null;

try {
    // Fetch OTC item MOJA PEKEE
    $stmt = $db->prepare("
        SELECT * FROM otc_sale_items 
        WHERE id = ? AND sale_id = ?
        LIMIT 1
    ");
    $stmt->execute([$otc_item_id, $sale_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        // Fetch sale info
        $stmt = $db->prepare("
            SELECT s.*, b.name as branch_name
            FROM otc_sales s
            LEFT JOIN branches b ON s.branch_id = b.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Item fetch error: " . $e->getMessage());
}

if (!$item || !$sale) {
    $_SESSION['error_message'] = "OTC item not found.";
    header('Location: other_services.php?tab=otc_bills&branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// PERFORM DELETE
// ================================================================
try {
    $db->beginTransaction();
    
    $item_name = $item['item_name'];
    $item_qty = (int)$item['quantity'];
    $item_total = (float)$item['total_price'];
    $inventory_id = (int)($item['inventory_id'] ?? 0);
    $sale_number = $sale['sale_number'];
    $payment_status = $sale['payment_status'] ?? 'pending';
    $branch_id = $sale['branch_id'];
    
    $stock_restored = false;
    
    // ============================================================
    // 1. RESTORE STOCK (kama sale ilikuwa paid)
    // ============================================================
    if ($payment_status === 'paid' && $inventory_id > 0 && $item_qty > 0) {
        $stmt = $db->prepare("SELECT quantity, medication_name FROM medications_inventory WHERE id = ?");
        $stmt->execute([$inventory_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inv) {
            $old_stock = (int)$inv['quantity'];
            $new_stock = $old_stock + $item_qty;
            
            $db->prepare("UPDATE medications_inventory SET quantity = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$new_stock, $inventory_id]);
            
            // Stock movement
            try {
                $db->prepare("
                    INSERT INTO stock_movements 
                    (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                     reference_type, reference_id, performed_by, branch_id, notes, created_at)
                    VALUES (?, NULL, 'in', ?, ?, ?, 'otc', ?, ?, ?, ?, NOW())
                ")->execute([
                    $inventory_id,
                    $item_qty,
                    $old_stock,
                    $new_stock,
                    $sale_id,
                    $user_id,
                    $branch_id,
                    "Stock RESTORED - Deleted OTC Item: {$item_name} from {$sale_number}"
                ]);
            } catch (Exception $e) {
                error_log("Stock movement insert failed: " . $e->getMessage());
            }
            
            $stock_restored = true;
        }
    }
    
    // ============================================================
    // 2. DELETE ITEM MOJA PEKEE
    // ============================================================
    $stmt_del = $db->prepare("DELETE FROM otc_sale_items WHERE id = ? AND sale_id = ? LIMIT 1");
    $stmt_del->execute([$otc_item_id, $sale_id]);
    
    // Verify
    $stmt_chk = $db->prepare("SELECT COUNT(*) as c FROM otc_sale_items WHERE id = ?");
    $stmt_chk->execute([$otc_item_id]);
    if ((int)($stmt_chk->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) > 0) {
        throw new Exception("Failed to delete item.");
    }
    
    // ============================================================
    // 3. RECALCULATE SALE TOTAL
    // ============================================================
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) as new_subtotal, COUNT(*) as remaining_items FROM otc_sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $recalc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_subtotal = (float)($recalc['new_subtotal'] ?? 0);
    $remaining_items = (int)($recalc['remaining_items'] ?? 0);
    
    $sale_discount = (float)($sale['discount_amount'] ?? 0);
    $sale_premium = (float)($sale['premium_amount'] ?? 0);
    
    // ✅ Kama discount/premium > subtotal mpya, punguza
    if ($sale_discount > $new_subtotal) $sale_discount = $new_subtotal;
    
    $new_total = $new_subtotal - $sale_discount + $sale_premium;
    if ($new_total < 0) $new_total = 0;
    
    // Kama hakuna items zilizobaki, futa sale yote
    if ($remaining_items == 0) {
        $db->prepare("DELETE FROM otc_sales WHERE id = ? LIMIT 1")->execute([$sale_id]);
        $action_msg = "sale deleted (no items left)";
    } else {
        // Update sale total
        $db->prepare("UPDATE otc_sales SET subtotal = ?, total_amount = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$new_subtotal, $new_total, $sale_id]);
        $action_msg = "sale updated (remaining: {$remaining_items} items)";
    }
    
    // ============================================================
    // 4. LOG ACTIVITY
    // ============================================================
    $log_details = "Deleted OTC Item: '{$item_name}' (Qty: {$item_qty}, Amount: {$currency} " . number_format($item_total, 0) . ") from Sale {$sale_number} | " .
                   "Stock restored: " . ($stock_restored ? 'YES' : 'NO') . " | " .
                   $action_msg;
    
    logActivity(
        $db, $user_id, $branch_id, null,
        'delete_otc_item',
        $log_details
    );
    
    $db->commit();
    
    // Success message
    $_SESSION['success_message'] = "✅ OTC Item '{$item_name}' deleted successfully!";
    if ($stock_restored) {
        $_SESSION['success_message'] .= " Stock restored (+{$item_qty}).";
    }
    if ($remaining_items > 0) {
        $_SESSION['success_message'] .= " Sale updated (New Total: {$currency} " . number_format($new_total, 0) . ")";
    } else {
        $_SESSION['success_message'] .= " Sale deleted (no items left).";
    }
    
    header('Location: other_services.php?tab=otc_bills&branch=' . $selected_branch_id);
    exit;
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
    header('Location: other_services.php?tab=otc_bills&branch=' . $selected_branch_id);
    exit;
}