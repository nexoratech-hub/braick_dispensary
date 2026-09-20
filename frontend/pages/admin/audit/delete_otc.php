<?php
// ================================================================
// FILE: frontend/pages/admin/audit/delete_otc.php
// ADMIN - DELETE OTC SALE (V3 - POST ONLY, SINGLE ID)
// ✅ Inatumia POST pekee
// ✅ Inafuta OTC sale MOJA pekee (WHERE id = ?)
// ✅ Inarudisha stock ya medications_inventory
// ✅ Ina-log activity + stock movement
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

$allowed_roles = ['admin'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error_message'] = "Only Admin can delete OTC sales.";
    header('Location: revenue.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// ✅ ONLY ACCEPT POST
// ================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method. OTC deletion must be done via the Delete button.";
    header('Location: revenue.php');
    exit;
}

$sale_id = (int)($_POST['sale_id'] ?? 0);
$selected_branch_id = $_POST['branch'] ?? 'all';

if ($sale_id <= 0) {
    $_SESSION['error_message'] = "Invalid OTC sale ID.";
    header('Location: revenue.php?branch=' . urlencode($selected_branch_id));
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
// FETCH OTC SALE (MOJA PEKEE)
// ================================================================
$sale = null;
try {
    $stmt = $db->prepare("
        SELECT s.*, 
               u.full_name as sold_by_name, u.role as sold_by_role,
               b.name as branch_name
        FROM otc_sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Sale fetch error: " . $e->getMessage());
}

if (!$sale) {
    $_SESSION['error_message'] = "OTC sale not found (ID: $sale_id).";
    header('Location: revenue.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// FETCH SALE ITEMS
$sale_items = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM otc_sale_items 
        WHERE sale_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Sale items fetch error: " . $e->getMessage());
}

// ================================================================
// PERFORM DELETE
// ================================================================
try {
    $db->beginTransaction();
    
    $sale_number = $sale['sale_number'];
    $sale_total = (float)$sale['total_amount'];
    $branch_id = $sale['branch_id'];
    $customer_name = $sale['customer_name'] ?? 'Walk-in';
    $payment_status = $sale['payment_status'] ?? 'pending';
    
    $items_restored = 0;
    $restore_details = [];
    
    // ============================================================
    // 1. RESTORE STOCK (kama ilikuwa paid)
    // ============================================================
    if ($payment_status === 'paid') {
        foreach ($sale_items as $item) {
            $inventory_id = (int)($item['inventory_id'] ?? 0);
            $item_qty = (int)($item['quantity'] ?? 0);
            $item_name = $item['item_name'] ?? 'N/A';
            
            if ($inventory_id > 0 && $item_qty > 0) {
                $stmt = $db->prepare("SELECT quantity, medication_name FROM medications_inventory WHERE id = ?");
                $stmt->execute([$inventory_id]);
                $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($inv) {
                    $old_stock = (int)$inv['quantity'];
                    $new_stock = $old_stock + $item_qty;
                    
                    $db->prepare("UPDATE medications_inventory SET quantity = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$new_stock, $inventory_id]);
                    
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
                            "Stock RESTORED - Deleted OTC Sale: {$sale_number} - {$item_name}"
                        ]);
                    } catch (Exception $e) {
                        error_log("Stock movement insert failed: " . $e->getMessage());
                    }
                    
                    $items_restored++;
                    $restore_details[] = "{$item_name}: +{$item_qty}";
                }
            }
        }
    }
    
    // ============================================================
    // 2. DELETE OTC SALE ITEMS (kwa sale_id hii pekee)
    // ============================================================
    $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?")->execute([$sale_id]);
    
    // ============================================================
    // 3. DELETE OTC SALE (MOJA PEKEE - WHERE id = ?)
    // ============================================================
    $stmt_del = $db->prepare("DELETE FROM otc_sales WHERE id = ? LIMIT 1");
    $stmt_del->execute([$sale_id]);
    
    // Verify delete ilifanikiwa
    $stmt_check = $db->prepare("SELECT COUNT(*) as c FROM otc_sales WHERE id = ?");
    $stmt_check->execute([$sale_id]);
    $still_exists = (int)($stmt_check->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    if ($still_exists > 0) {
        throw new Exception("Failed to delete OTC sale. It still exists.");
    }
    
    // ============================================================
    // 4. LOG ACTIVITY
    // ============================================================
    $log_details = "Deleted OTC Sale: {$sale_number} | " .
                   "Customer: {$customer_name} | " .
                   "Amount: {$currency} " . number_format($sale_total, 0) . " | " .
                   "Items: " . count($sale_items) . " | " .
                   "Status: {$payment_status}";
    
    if ($items_restored > 0) {
        $log_details .= " | ✅ Stock RESTORED: {$items_restored} items";
    }
    
    logActivity(
        $db, $user_id, $branch_id, null,
        'delete_otc_sale',
        $log_details
    );
    
    $db->commit();
    
    // ============================================================
    // 5. SUCCESS MESSAGE
    // ============================================================
    $success_msg = "✅ OTC Sale '{$sale_number}' deleted successfully!";
    $success_msg .= " ({$currency} " . number_format($sale_total, 0) . ")";
    
    if ($items_restored > 0) {
        $success_msg .= " | Stock restored for {$items_restored} item(s)";
    }
    
    $_SESSION['success_message'] = $success_msg;
    
    header('Location: revenue.php?branch=' . $selected_branch_id . '&deleted=1');
    exit;
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
    header('Location: revenue.php?branch=' . $selected_branch_id);
    exit;
}