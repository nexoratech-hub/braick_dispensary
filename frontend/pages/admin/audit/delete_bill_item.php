<?php
// ================================================================
// FILE: frontend/pages/admin/audit/delete_bill_item.php
// ADMIN AUDIT - DELETE BILL ITEM (V2 - WITH PAYMENT ADJUSTMENT)
// ✅ Futa bill item
// ✅ Re-calculate bill subtotal, discount, premium, total
// ✅ Re-calculate paid_amount kutoka payments
// ✅ Kama bill ni PAID na total mpya < paid, punguza payments
// ✅ Kama bill ni PARTIAL, punguza payments kwa kiasi cha subtotal iliyopungua
// ✅ Update bill status (paid/partial/pending)
// ✅ Activity logging
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

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';
$return_url = $_GET['return'] ?? 'revenue.php';

if ($item_id <= 0) {
    header('Location: ' . $return_url . '?branch=' . $selected_branch_id . '&error=invalid_item');
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HELPER: Parse money
// ================================================================
function parseMoney($value) {
    if ($value === null || $value === '') return 0;
    $clean = str_replace([',', ' '], '', $value);
    return (float)$clean;
}

// ================================================================
// START TRANSACTION
// ================================================================
try {
    $db->beginTransaction();
    
    // ============================================================
    // 1. FETCH ITEM DETAILS
    // ============================================================
    $stmt = $db->prepare("SELECT 
                            bi.*,
                            b.bill_number,
                            b.visit_id,
                            b.patient_id,
                            b.subtotal as bill_subtotal,
                            b.total_amount as bill_total_old,
                            b.paid_amount as bill_paid_old,
                            b.balance as bill_balance_old,
                            b.status as bill_status_old,
                            b.pharmacy_discount,
                            b.cashier_discount,
                            b.pharmacy_premium,
                            b.cashier_premium,
                            b.total_discount,
                            b.premium_amount,
                            b.payment_method
                        FROM bill_items bi
                        INNER JOIN bills b ON bi.bill_id = b.id
                        WHERE bi.id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception("Bill item not found.");
    }
    
    $bill_id = (int)$item['bill_id'];
    $old_item_total = (float)$item['total_price'];
    $old_item_discount = (float)($item['discount_amount'] ?? 0);
    $old_item_tax = (float)($item['tax_amount'] ?? 0);
    $old_item_final = $old_item_total - $old_item_discount + $old_item_tax;
    
    $bill_total_old = (float)$item['bill_total_old'];
    $bill_paid_old = (float)$item['bill_paid_old'];
    $bill_balance_old = (float)$item['bill_balance_old'];
    $bill_status_old = $item['bill_status_old'];
    
    // ============================================================
    // 2. FETCH ALL BILL ITEMS (BEFORE DELETE) - for logging
    // ============================================================
    $stmt = $db->prepare("SELECT id, item_name, item_type, total_price, discount_amount, status FROM bill_items WHERE bill_id = ? ORDER BY id");
    $stmt->execute([$bill_id]);
    $all_items_before = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================================
    // 3. DELETE BILL ITEM (HARD DELETE)
    // ============================================================
    $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ?");
    $stmt->execute([$item_id]);
    
    // ============================================================
    // 4. RE-CALCULATE BILL SUBTOTAL (kutoka bill_items zilizobaki)
    // ============================================================
    $stmt = $db->prepare("SELECT 
                            COALESCE(SUM(total_price), 0) as subtotal,
                            COALESCE(SUM(discount_amount), 0) as items_discount,
                            COALESCE(SUM(tax_amount), 0) as items_tax
                          FROM bill_items 
                          WHERE bill_id = ? AND status != 'cancelled'");
    $stmt->execute([$bill_id]);
    $calc = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_subtotal = (float)$calc['subtotal'];
    $items_discount = (float)$calc['items_discount'];
    $items_tax = (float)$calc['items_tax'];
    
    // ============================================================
    // 5. GET BILL-LEVEL DISCOUNT & PREMIUM (HAZIBADILIKI)
    // ============================================================
    $pharm_disc = (float)($item['pharmacy_discount'] ?? 0);
    $cash_disc = (float)($item['cashier_discount'] ?? 0);
    $pharm_prem = (float)($item['pharmacy_premium'] ?? 0);
    $cash_prem = (float)($item['cashier_premium'] ?? 0);
    
    $total_discount = $items_discount + $pharm_disc + $cash_disc;
    $premium_amount = $pharm_prem + $cash_prem;
    
    // ============================================================
    // 6. CALCULATE NEW BILL TOTAL
    // ============================================================
    $new_total = $new_subtotal - $total_discount + $premium_amount + $items_tax;
    $new_total = max(0, $new_total);
    
    // ============================================================
    // 7. RECALCULATE PAID AMOUNT & ADJUST PAYMENTS
    // ============================================================
    // Pata payments zote za bill hii
    $stmt = $db->prepare("SELECT id, amount, received_at FROM payments WHERE bill_id = ? ORDER BY received_at ASC, id ASC");
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_paid_now = 0;
    foreach ($payments as $p) {
        $total_paid_now += (float)$p['amount'];
    }
    
    // Kama bill ilikuwa PAID (total_paid >= bill_total_old) na new_total < total_paid_now
    // Tunahitaji kupunguza payments ili total_paid = new_total
    $adjustment_needed = 0;
    
    if ($bill_status_old === 'paid' && $new_total < $total_paid_now) {
        // Bill ilikuwa PAID, lakini total mpya ni ndogo
        // Punguza payments kwa kiasi cha (total_paid_now - new_total)
        $adjustment_needed = $total_paid_now - $new_total;
        
        // Punguza payments kutoka za mwisho kwenda mwanzo
        $remaining_adjustment = $adjustment_needed;
        foreach (array_reverse($payments) as $p) {
            if ($remaining_adjustment <= 0) break;
            
            $payment_amount = (float)$p['amount'];
            
            if ($payment_amount <= $remaining_adjustment) {
                // Futa payment yote
                $db->prepare("DELETE FROM payments WHERE id = ?")->execute([$p['id']]);
                $remaining_adjustment -= $payment_amount;
            } else {
                // Punguza payment kwa kiasi kinachohitajika
                $new_payment_amount = $payment_amount - $remaining_adjustment;
                $db->prepare("UPDATE payments SET amount = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$new_payment_amount, $p['id']]);
                $remaining_adjustment = 0;
            }
        }
        
        // Sasa recalculate total_paid
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $total_paid_now = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    elseif ($bill_status_old === 'partial') {
        // Bill ilikuwa PARTIAL
        // Tunahitaji kupunguza payments kwa kiasi cha (bill_total_old - new_total)
        // Kwa sababu hiyo ni sehemu ya bill iliyopunguzwa
        $old_total = $bill_total_old;
        $difference = $old_total - $new_total;
        
        if ($difference > 0 && $total_paid_now > 0) {
            // Punguza payments kwa kiasi cha difference (lakini si zaidi ya total_paid_now)
            $adjustment_needed = min($difference, $total_paid_now);
            
            $remaining_adjustment = $adjustment_needed;
            foreach (array_reverse($payments) as $p) {
                if ($remaining_adjustment <= 0) break;
                
                $payment_amount = (float)$p['amount'];
                
                if ($payment_amount <= $remaining_adjustment) {
                    $db->prepare("DELETE FROM payments WHERE id = ?")->execute([$p['id']]);
                    $remaining_adjustment -= $payment_amount;
                } else {
                    $new_payment_amount = $payment_amount - $remaining_adjustment;
                    $db->prepare("UPDATE payments SET amount = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$new_payment_amount, $p['id']]);
                    $remaining_adjustment = 0;
                }
            }
            
            // Recalculate total_paid
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $total_paid_now = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }
    
    // ============================================================
    // 8. RECALCULATE BALANCE & STATUS
    // ============================================================
    $new_balance = max(0, $new_total - $total_paid_now);
    
    $new_status = 'pending';
    if ($new_total > 0) {
        if ($new_balance <= 0 && $total_paid_now > 0) {
            $new_status = 'paid';
        } elseif ($total_paid_now > 0 && $new_balance > 0) {
            $new_status = 'partial';
        } else {
            $new_status = 'pending';
        }
    } else {
        // Bill haina items
        $new_status = 'cancelled';
    }
    
    // ============================================================
    // 9. UPDATE BILL
    // ============================================================
    $stmt = $db->prepare("UPDATE bills SET 
                            subtotal = ?,
                            total_discount = ?,
                            premium_amount = ?,
                            total_amount = ?,
                            paid_amount = ?,
                            balance = ?,
                            status = ?,
                            updated_at = NOW()
                          WHERE id = ?");
    $stmt->execute([
        $new_subtotal,
        $total_discount,
        $premium_amount,
        $new_total,
        $total_paid_now,
        $new_balance,
        $new_status,
        $bill_id
    ]);
    
    // ============================================================
    // 10. UPDATE VISIT TOTAL (kama ipo)
    // ============================================================
    if (!empty($item['visit_id'])) {
        $visit_id = (int)$item['visit_id'];
        
        // Recalculate visit_total from all bills of this visit
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as visit_total FROM bills WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        $new_visit_total = (float)$stmt->fetch(PDO::FETCH_ASSOC)['visit_total'];
        
        $stmt = $db->prepare("UPDATE visits SET visit_total = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_visit_total, $visit_id]);
    }
    
    // ============================================================
    // 11. ACTIVITY LOG
    // ============================================================
    try {
        $details = "Deleted bill item #{$item_id}: {$item['item_name']} ({$item['item_type']}) "
                 . "TSh " . number_format($old_item_total, 0) . " from Bill {$item['bill_number']}. "
                 . "Bill total: TSh " . number_format($bill_total_old, 0) . " → TSh " . number_format($new_total, 0) . ". "
                 . "Paid: TSh " . number_format($bill_paid_old, 0) . " → TSh " . number_format($total_paid_now, 0) . ". "
                 . "Status: {$bill_status_old} → {$new_status}.";
        
        $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                      VALUES (?, ?, 'delete_bill_item', ?, ?, NOW())")
           ->execute([
               $user_id,
               $user_branch_id,
               $details,
               $_SERVER['REMOTE_ADDR'] ?? 'unknown'
           ]);
    } catch (Exception $e) {
        // Ignore logging errors
    }
    
    // ============================================================
    // 12. COMMIT
    // ============================================================
    $db->commit();
    
    // ============================================================
    // 13. REDIRECT WITH SUCCESS MESSAGE
    // ============================================================
    $success_msg = urlencode(
        "Item deleted successfully! "
        . "Bill total: TSh " . number_format($bill_total_old, 0) . " → TSh " . number_format($new_total, 0) . " | "
        . "Paid: TSh " . number_format($bill_paid_old, 0) . " → TSh " . number_format($total_paid_now, 0) . " | "
        . "Status: " . strtoupper($bill_status_old) . " → " . strtoupper($new_status)
    );
    
    header('Location: ' . $return_url . '?branch=' . $selected_branch_id . '&success=' . $success_msg);
    exit;
    
} catch (Exception $e) {
    // ============================================================
    // ROLLBACK ON ERROR
    // ============================================================
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $error_msg = urlencode("Error deleting item: " . $e->getMessage());
    header('Location: ' . $return_url . '?branch=' . $selected_branch_id . '&error=' . $error_msg);
    exit;
}