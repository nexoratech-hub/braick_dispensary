<?php
// ================================================================
// FILE: frontend/pages/admin/audit/other_services.php
// ADMIN AUDIT - OTHER SERVICES (V17 - FIXED ALL BILLS + SEARCH POSITION)
// ================================================================
// ✅ V17: All Bills inaonyesha bills zenye patient_id TU
// ✅ V17: Search bar imehamishwa CHINI ya summary cards
// ✅ V16: View/Edit/Delete kwa KILA ITEM
// ✅ V16: <> Arrow buttons kwenye KILA visit table header
// ✅ V16: <> Arrow buttons kwenye KILA ITEM CATEGORY table (All Bills tab)
// ✅ V16: All Bills: 6 SUMMARY CARDS (3+3) + PATIENT CARDS
// ✅ V16: OTC tab: CARD PER SALE
// ✅ Auto stock restore kwa medications
// ✅ Auto recalculate bill baada ya delete
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function buildFilterUrl($params_to_update = []) {
    $current = $_GET;
    foreach ($params_to_update as $key => $value) {
        if ($value === null || $value === '') unset($current[$key]);
        else $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

function getStatusBadge($status) {
    $map = [
        'pending'    => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'confirmed'  => ['class' => 'info',    'icon' => '🔵', 'label' => 'Confirmed'],
        'paid'       => ['class' => 'success', 'icon' => '✅', 'label' => 'Paid'],
        'dispensed'  => ['class' => 'cyan',    'icon' => '📦', 'label' => 'Dispensed'],
        'completed'  => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled'  => ['class' => 'danger',  'icon' => '❌', 'label' => 'Cancelled'],
        'partial'    => ['class' => 'purple',  'icon' => '⚡', 'label' => 'Partial'],
        'in_progress'=> ['class' => 'info',    'icon' => '🔄', 'label' => 'In Progress'],
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status ?: 'Unknown')];
}

function formatTsh($amount) {
    return 'TSh ' . number_format((float)$amount, 0);
}

function getInitials($name) {
    if (empty($name)) return 'NA';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

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

function recalculateBill($db, $bill_id, $user_id, $item_info = []) {
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
    
    $stmt = $db->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill_data) return null;
    
    $bill_discount = (float)($bill_data['discount_amount'] ?? 0);
    $premium = (float)($bill_data['premium_amount'] ?? 0);
    $old_paid = (float)($bill_data['paid_amount'] ?? 0);
    $patient_id = $bill_data['patient_id'];
    $branch_id = $bill_data['branch_id'];
    
    $new_total_discount = $new_items_discount + $bill_discount;
    $new_total_amount = $new_subtotal - $new_total_discount + $premium;
    if ($new_total_amount < 0) $new_total_amount = 0;
    
    $new_paid = $old_paid;
    $overpayment = 0;
    $refund_created = false;
    $refund_receipt = null;
    
    if ($old_paid > $new_total_amount && $new_total_amount > 0) {
        $overpayment = $old_paid - $new_total_amount;
        $new_paid = $new_total_amount;
        
        $refund_receipt = 'REFUND-' . date('Ymd') . '-' . rand(1000, 9999);
        
        try {
            $db->prepare("
                INSERT INTO payments 
                (receipt_number, bill_id, patient_id, amount, payment_method, notes, received_by, branch_id, received_at, updated_at)
                VALUES (?, ?, ?, ?, 'cash', ?, ?, ?, NOW(), NOW())
            ")->execute([
                $refund_receipt,
                $bill_id,
                $patient_id,
                -$overpayment,
                "Auto REFUND: {$item_info['item_label']} '{$item_info['item_name']}' deleted",
                $user_id,
                $branch_id
            ]);
            $refund_created = true;
        } catch (Exception $e) {
            error_log("Refund insert failed: " . $e->getMessage());
        }
    }
    
    $new_balance = $new_total_amount - $new_paid;
    if ($new_balance < 0) $new_balance = 0;
    
    $new_status = 'pending';
    if ($new_balance <= 0 && $new_total_amount > 0) $new_status = 'paid';
    elseif ($new_paid > 0 && $new_balance > 0) $new_status = 'partial';
    if ($new_total_amount <= 0) $new_status = 'cancelled';
    
    $db->prepare("
        UPDATE bills SET 
            subtotal = ?, total_discount = ?, total_amount = ?,
            paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([
        $new_subtotal, $new_total_discount, $new_total_amount,
        $new_paid, $new_balance, $new_status, $bill_id
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
// HANDLE DELETE BILL ITEM (ALL TYPES)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_bill_item_all') {
    try {
        $db->beginTransaction();
        
        $del_bill_item_id = (int)($_POST['bill_item_id'] ?? 0);
        $del_item_type = $_POST['item_type'] ?? 'other';
        $selected_branch = $_POST['branch'] ?? 'all';
        $redirect_tab = $_POST['redirect_tab'] ?? 'all_bills';
        
        if ($del_bill_item_id <= 0) throw new Exception("Invalid bill item ID.");
        
        $stmt = $db->prepare("
            SELECT bi.*, b.bill_number, b.id as bill_id, b.patient_id, b.branch_id, b.visit_id
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.id = ?
            LIMIT 1
        ");
        $stmt->execute([$del_bill_item_id]);
        $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill_item) throw new Exception("Bill item not found.");
        
        $item_name = $bill_item['item_name'];
        $bill_id = $bill_item['bill_id'];
        $bill_number = $bill_item['bill_number'];
        $visit_id = $bill_item['visit_id'];
        $patient_id = $bill_item['patient_id'];
        $branch_id = $bill_item['branch_id'];
        $reference_id = (int)($bill_item['reference_id'] ?? 0);
        
        $item_labels = [
            'consultation' => 'Consultation',
            'lab_test' => 'Lab Test',
            'medication' => 'Medication',
            'procedure' => 'Procedure',
            'equipment' => 'Equipment',
            'registration' => 'Registration',
            'other' => 'Item',
        ];
        $item_label = $item_labels[$del_item_type] ?? 'Item';
        
        $db->prepare("DELETE FROM bill_items WHERE id = ? LIMIT 1")->execute([$del_bill_item_id]);
        
        $stock_restored = false;
        $stock_qty = 0;
        
        if ($del_item_type === 'medication' && $reference_id > 0) {
            $stmt = $db->prepare("SELECT * FROM prescription_items WHERE id = ? LIMIT 1");
            $stmt->execute([$reference_id]);
            $presc_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($presc_item && !empty($presc_item['inventory_id'])) {
                $qty_to_restore = (int)($presc_item['quantity'] ?? 0);
                $inventory_id = (int)$presc_item['inventory_id'];
                
                if ($qty_to_restore > 0 && $inventory_id > 0) {
                    $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                    $stmt->execute([$inventory_id]);
                    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($inv) {
                        $old_stock = (int)$inv['quantity'];
                        $new_stock = $old_stock + $qty_to_restore;
                        
                        $db->prepare("UPDATE medications_inventory SET quantity = ?, updated_at = NOW() WHERE id = ?")
                           ->execute([$new_stock, $inventory_id]);
                        
                        try {
                            $db->prepare("
                                INSERT INTO stock_movements 
                                (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                                 reference_type, reference_id, performed_by, branch_id, notes, created_at)
                                VALUES (?, ?, 'in', ?, ?, ?, 'prescription_deleted', ?, ?, ?, ?, NOW())
                            ")->execute([
                                $inventory_id, $patient_id, $qty_to_restore, $old_stock, $new_stock,
                                $reference_id, $user_id, $branch_id,
                                "Stock RESTORED - Deleted medication from Bill {$bill_number}"
                            ]);
                        } catch (Exception $e) {}
                        
                        $stock_restored = true;
                        $stock_qty = $qty_to_restore;
                    }
                }
            }
            
            try {
                $db->prepare("DELETE FROM prescription_items WHERE id = ? LIMIT 1")->execute([$reference_id]);
            } catch (Exception $e) {}
        }
        
        if ($del_item_type === 'lab_test' && $reference_id > 0) {
            try {
                $db->prepare("DELETE FROM lab_tests WHERE id = ? LIMIT 1")->execute([$reference_id]);
            } catch (Exception $e) {}
        }
        
        if (in_array($del_item_type, ['procedure', 'equipment']) && $reference_id > 0) {
            try {
                $stmt_check = $db->prepare("SELECT COUNT(*) as c FROM bill_items WHERE reference_id = ? AND item_type IN ('procedure', 'equipment')");
                $stmt_check->execute([$reference_id]);
                $remaining = (int)($stmt_check->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                
                if ($remaining == 0 && $del_item_type === 'procedure') {
                    $db->prepare("DELETE FROM procedures WHERE id = ? LIMIT 1")->execute([$reference_id]);
                }
            } catch (Exception $e) {}
        }
        
        $recalc = recalculateBill($db, $bill_id, $user_id, [
            'item_label' => $item_label,
            'item_name' => $item_name
        ]);
        
        logActivity($db, $user_id, $branch_id, $patient_id, 'bill_item_deleted',
            "Deleted {$item_label}: '{$item_name}' from Bill #{$bill_number}" .
            ($stock_restored ? " | Stock restored: +{$stock_qty}" : ""));
        
        $db->commit();
        
        $msg = "✅ {$item_label} '{$item_name}' deleted successfully!";
        if ($stock_restored) {
            $msg .= " Stock restored (+{$stock_qty}).";
        }
        $_SESSION['success_message'] = $msg;
        
        header('Location: other_services.php?tab=' . $redirect_tab . '&branch=' . $selected_branch);
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: other_services.php');
        exit;
    }
}

// ================================================================
// HANDLE DELETE BILL ITEM (PROCEDURES - LEGACY)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    try {
        $db->beginTransaction();
        
        $del_bill_item_id = (int)($_POST['bill_item_id'] ?? 0);
        $del_reference_id = (int)($_POST['reference_id'] ?? 0);
        $del_item_type = $_POST['item_type'] ?? 'procedure';
        
        if ($del_bill_item_id <= 0) throw new Exception("Invalid bill item ID.");
        
        $stmt = $db->prepare("
            SELECT bi.*, b.bill_number, b.id as bill_id
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.id = ?
            LIMIT 1
        ");
        $stmt->execute([$del_bill_item_id]);
        $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill_item) throw new Exception("Bill item not found.");
        
        $proc_name = $bill_item['item_name'];
        $bill_id = $bill_item['bill_id'];
        $bill_number = $bill_item['bill_number'];
        $item_label = ($del_item_type === 'equipment') ? 'Equipment' : 'Procedure';
        
        $db->prepare("DELETE FROM bill_items WHERE id = ? LIMIT 1")->execute([$del_bill_item_id]);
        
        $recalc = recalculateBill($db, $bill_id, $user_id, [
            'item_label' => $item_label,
            'item_name' => $proc_name
        ]);
        
        if ($del_item_type === 'procedure' && $del_reference_id > 0) {
            $stmt_ref = $db->prepare("SELECT COUNT(*) as c FROM bill_items WHERE reference_id = ? AND item_type = 'procedure'");
            $stmt_ref->execute([$del_reference_id]);
            $remaining = (int)($stmt_ref->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
            
            if ($remaining == 0) {
                $db->prepare("DELETE FROM procedures WHERE id = ? LIMIT 1")->execute([$del_reference_id]);
            }
        }
        
        logActivity($db, $user_id, $bill_item['branch_id'] ?? null, $bill_item['patient_id'] ?? null,
            'bill_item_deleted', "Deleted {$item_label}: '{$proc_name}' from Bill #{$bill_number}");
        
        $db->commit();
        $_SESSION['success_message'] = "✅ {$item_label} '{$proc_name}' deleted successfully!";
        
        header('Location: other_services.php?tab=procedures&branch=' . ($_POST['branch'] ?? 'all'));
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: other_services.php?tab=procedures');
        exit;
    }
}

// ================================================================
// HANDLE DELETE OTC ITEM
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_otc_item') {
    try {
        $db->beginTransaction();
        
        $otc_item_id = (int)($_POST['otc_item_id'] ?? 0);
        $sale_id = (int)($_POST['sale_id'] ?? 0);
        $selected_branch = $_POST['branch'] ?? 'all';
        
        if ($otc_item_id <= 0 || $sale_id <= 0) throw new Exception("Invalid item or sale ID.");
        
        $stmt = $db->prepare("
            SELECT osi.*, os.sale_number, os.payment_status, os.branch_id,
                   os.discount_amount, os.premium_amount
            FROM otc_sale_items osi
            INNER JOIN otc_sales os ON osi.sale_id = os.id
            WHERE osi.id = ? AND osi.sale_id = ?
            LIMIT 1
        ");
        $stmt->execute([$otc_item_id, $sale_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) throw new Exception("OTC item not found.");
        
        $item_name = $item['item_name'];
        $item_qty = (int)$item['quantity'];
        $inventory_id = (int)($item['inventory_id'] ?? 0);
        $payment_status = $item['payment_status'];
        $branch_id = $item['branch_id'];
        $sale_number = $item['sale_number'];
        
        $stock_restored = false;
        if ($payment_status === 'paid' && $inventory_id > 0 && $item_qty > 0) {
            $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
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
                        $inventory_id, $item_qty, $old_stock, $new_stock,
                        $sale_id, $user_id, $branch_id,
                        "Stock RESTORED - Deleted OTC Item: {$item_name} from {$sale_number}"
                    ]);
                } catch (Exception $e) {}
                
                $stock_restored = true;
            }
        }
        
        $db->prepare("DELETE FROM otc_sale_items WHERE id = ? AND sale_id = ? LIMIT 1")
           ->execute([$otc_item_id, $sale_id]);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) as new_subtotal, COUNT(*) as remaining_items FROM otc_sale_items WHERE sale_id = ?");
        $stmt->execute([$sale_id]);
        $recalc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_subtotal = (float)($recalc['new_subtotal'] ?? 0);
        $remaining_items = (int)($recalc['remaining_items'] ?? 0);
        
        $sale_discount = (float)($item['discount_amount'] ?? 0);
        $sale_premium = (float)($item['premium_amount'] ?? 0);
        if ($sale_discount > $new_subtotal) $sale_discount = $new_subtotal;
        
        $new_total = $new_subtotal - $sale_discount + $sale_premium;
        if ($new_total < 0) $new_total = 0;
        
        if ($remaining_items == 0) {
            $db->prepare("DELETE FROM otc_sales WHERE id = ? LIMIT 1")->execute([$sale_id]);
        } else {
            $db->prepare("UPDATE otc_sales SET subtotal = ?, total_amount = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$new_subtotal, $new_total, $sale_id]);
        }
        
        logActivity($db, $user_id, $branch_id, null, 'delete_otc_item',
            "Deleted OTC Item: '{$item_name}' from Sale {$sale_number}" . 
            ($stock_restored ? " | Stock restored: +{$item_qty}" : "") .
            " | Remaining items: {$remaining_items}");
        
        $db->commit();
        
        $_SESSION['success_message'] = "✅ OTC Item '{$item_name}' deleted successfully!";
        if ($stock_restored) $_SESSION['success_message'] .= " Stock restored (+{$item_qty}).";
        
        header('Location: other_services.php?tab=otc_bills&branch=' . $selected_branch);
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: other_services.php?tab=otc_bills');
        exit;
    }
}

// ================================================================
// HANDLE DELETE OTC SALE (FULL SALE)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_otc_sale') {
    try {
        $db->beginTransaction();
        
        $sale_id = (int)($_POST['sale_id'] ?? 0);
        $selected_branch = $_POST['branch'] ?? 'all';
        
        if ($sale_id <= 0) throw new Exception("Invalid sale ID.");
        
        $stmt = $db->prepare("SELECT sale_number, branch_id FROM otc_sales WHERE id = ? LIMIT 1");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) throw new Exception("OTC sale not found.");
        
        $sale_number = $sale['sale_number'];
        $branch_id = $sale['branch_id'];
        
        $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?")->execute([$sale_id]);
        $db->prepare("DELETE FROM otc_sales WHERE id = ? LIMIT 1")->execute([$sale_id]);
        
        logActivity($db, $user_id, $branch_id, null, 'delete_otc_sale',
            "Deleted OTC Sale: {$sale_number}");
        
        $db->commit();
        
        $_SESSION['success_message'] = "✅ OTC Sale '{$sale_number}' deleted successfully!";
        
        header('Location: other_services.php?tab=otc_bills&branch=' . $selected_branch);
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: other_services.php?tab=otc_bills');
        exit;
    }
}

// ================================================================
// FILTERS
// ================================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'procedures';
if (!in_array($active_tab, ['procedures', 'consultations', 'all_bills', 'otc_bills'])) {
    $active_tab = 'procedures';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$quick_filter = isset($_GET['quick']) ? $_GET['quick'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$quick_date_from = '';
$quick_date_to = date('Y-m-d');
switch ($quick_filter) {
    case 'today': $quick_date_from = date('Y-m-d'); $quick_date_to = date('Y-m-d'); break;
    case '1w': $quick_date_from = date('Y-m-d', strtotime('-7 days')); break;
    case '1m': $quick_date_from = date('Y-m-d', strtotime('-1 month')); break;
    case '3m': $quick_date_from = date('Y-m-d', strtotime('-3 months')); break;
    case '1y': $quick_date_from = date('Y-m-d', strtotime('-1 year')); break;
    case 'custom': $quick_date_from = $date_from; $quick_date_to = $date_to; break;
    default: $quick_date_from = ''; $quick_date_to = ''; break;
}

$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) { $selected_branch_name = $b['name']; break; }
    }
}

$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// TAB 1: PROCEDURES & EQUIPMENTS
// ================================================================
$procedures_data = [];
$procedures_array = [];
$procedures_stats = ['total'=>0, 'pending'=>0, 'completed'=>0, 'amount'=>0, 'equipment_count'=>0, 'procedure_count'=>0];

if ($active_tab === 'procedures') {
    $where = " WHERE bi.item_type IN ('procedure', 'equipment') AND bi.status != 'cancelled'";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (bi.item_name LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if ($selected_branch_id !== 'all') { 
        $where .= " AND bi.branch_id = ?"; 
        $params[] = (int)$selected_branch_id; 
    }
    if (!empty($quick_date_from)) { 
        $where .= " AND DATE(bi.created_at) >= ?"; 
        $params[] = $quick_date_from; 
    }
    if (!empty($quick_date_to)) { 
        $where .= " AND DATE(bi.created_at) <= ?"; 
        $params[] = $quick_date_to; 
    }
    if (!empty($status_filter)) {
        if (in_array($status_filter, ['pending','partial','paid','cancelled'])) {
            $where .= " AND b.status = ?";
            $params[] = $status_filter;
        } else {
            $where .= " AND bi.status = ?";
            $params[] = $status_filter;
        }
    }
    
    $sql = "
        SELECT bi.*, 
               bi.id as item_row_id,
               bi.item_name as procedure_name,
               bi.item_type,
               bi.unit_price as procedure_price,
               bi.status as item_status,
               bi.created_at as item_created_at,
               bi.item_id as catalog_id,
               bi.reference_id,
               bi.reference_type,
               b.id as bill_id, b.bill_number, b.status as bill_status,
               pat.id as patient_db_id, pat.full_name as patient_name, 
               pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, 
               pat.date_of_birth,
               v.id as visit_db_id, v.visit_number, v.visit_date, v.status as visit_status,
               doc.full_name as doctor_name, 
               br.name as branch_name
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        LEFT JOIN patients pat ON bi.patient_id = pat.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN branches br ON bi.branch_id = br.id
        $where
        ORDER BY b.created_at DESC, bi.id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $procedures_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($procedures_rows as $row) {
        $pid = $row['patient_db_id'] ?? 0;
        $vid = $row['visit_db_id'] ?? 0;
        
        $row['category'] = ($row['item_type'] === 'equipment') ? 'Equipment' : 'Procedure';
        
        if ($row['item_type'] === 'equipment') {
            $procedures_stats['equipment_count']++;
        } else {
            $procedures_stats['procedure_count']++;
        }
        
        if (!isset($procedures_data[$pid])) {
            $procedures_data[$pid] = [
                'patient_id' => $pid, 
                'patient_name' => $row['patient_name'] ?? 'Unknown',
                'patient_number' => $row['patient_number'] ?? 'N/A', 
                'patient_phone' => $row['patient_phone'] ?? '',
                'patient_gender' => $row['patient_gender'] ?? '', 
                'date_of_birth' => $row['date_of_birth'] ?? '',
                'visits' => [], 'total_amount' => 0, 'total_items' => 0,
                'partial_bills' => 0,
                'bills_with_partial' => []
            ];
        }
        
        if ($row['bill_status'] === 'partial' && !in_array($row['bill_id'], $procedures_data[$pid]['bills_with_partial'])) {
            $procedures_data[$pid]['bills_with_partial'][] = $row['bill_id'];
            $procedures_data[$pid]['partial_bills']++;
        }
        
        if (!isset($procedures_data[$pid]['visits'][$vid])) {
            $procedures_data[$pid]['visits'][$vid] = [
                'visit_id' => $vid, 
                'visit_number' => $row['visit_number'] ?? 'N/A',
                'visit_date' => $row['visit_date'] ?? $row['item_created_at'], 
                'doctor_name' => $row['doctor_name'] ?? 'N/A',
                'visit_status' => $row['visit_status'] ?? 'N/A', 
                'items' => [], 'total_amount' => 0
            ];
        }
        $procedures_data[$pid]['visits'][$vid]['items'][] = $row;
        $procedures_data[$pid]['visits'][$vid]['total_amount'] += $row['procedure_price'] ?? 0;
        $procedures_data[$pid]['total_amount'] += $row['procedure_price'] ?? 0;
        $procedures_data[$pid]['total_items']++;
    }
    
    $procedures_array = array_values($procedures_data);
    foreach ($procedures_array as &$p) {
        $p['visit_count'] = count($p['visits']);
        $p['visits'] = array_values($p['visits']);
    }
    unset($p);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(bi.total_price),0) as s 
                          FROM bill_items bi 
                          INNER JOIN bills b ON bi.bill_id = b.id 
                          LEFT JOIN patients pat ON bi.patient_id = pat.id 
                          $where");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $procedures_stats['total'] = $r['c'] ?? 0;
    $procedures_stats['amount'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi 
                          INNER JOIN bills b ON bi.bill_id = b.id 
                          LEFT JOIN patients pat ON bi.patient_id = pat.id 
                          $where AND bi.status = 'pending'");
    $stmt->execute($params);
    $procedures_stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi 
                          INNER JOIN bills b ON bi.bill_id = b.id 
                          LEFT JOIN patients pat ON bi.patient_id = pat.id 
                          $where AND bi.status = 'paid'");
    $stmt->execute($params);
    $procedures_stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
}

// ================================================================
// TAB 2: CONSULTATIONS
// ================================================================
$consultations_data = [];
$consultations_array = [];
$consultations_stats = ['total'=>0, 'pending'=>0, 'paid'=>0, 'partial'=>0, 'amount'=>0];

if ($active_tab === 'consultations') {
    $where = " WHERE bi.item_type = 'consultation' AND bi.status != 'cancelled'";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (v.visit_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if ($selected_branch_id !== 'all') { 
        $where .= " AND bi.branch_id = ?"; 
        $params[] = (int)$selected_branch_id; 
    }
    if (!empty($quick_date_from)) { 
        $where .= " AND DATE(v.visit_date) >= ?"; 
        $params[] = $quick_date_from; 
    }
    if (!empty($quick_date_to)) { 
        $where .= " AND DATE(v.visit_date) <= ?"; 
        $params[] = $quick_date_to; 
    }
    if (!empty($status_filter)) {
        if (in_array($status_filter, ['pending','partial','paid','cancelled'])) {
            $where .= " AND bi.status = ?";
            $params[] = $status_filter;
        } else {
            $where .= " AND v.status = ?";
            $params[] = $status_filter;
        }
    }
    
    $sql = "
        SELECT bi.*,
               bi.id as item_row_id,
               b.id as bill_id, b.bill_number, b.status as bill_status,
               v.id as visit_db_id, v.visit_number, v.visit_date, 
               v.visit_type, v.consultation_fee, v.diagnosis, v.symptoms,
               v.status as visit_status, v.payment_status, v.visit_total,
               pat.id as patient_db_id, pat.full_name as patient_name, 
               pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, 
               pat.date_of_birth,
               doc.full_name as doctor_name, 
               rec.full_name as receptionist_name,
               br.name as branch_name
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        INNER JOIN visits v ON b.visit_id = v.id
        LEFT JOIN patients pat ON bi.patient_id = pat.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN users rec ON v.receptionist_id = rec.id
        LEFT JOIN branches br ON bi.branch_id = br.id
        $where
        ORDER BY v.created_at DESC, bi.id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $consultations_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($consultations_rows as $row) {
        $pid = $row['patient_db_id'] ?? 0;
        $vid = $row['visit_db_id'] ?? 0;
        
        if (!isset($consultations_data[$pid])) {
            $consultations_data[$pid] = [
                'patient_id' => $pid, 'patient_name' => $row['patient_name'] ?? 'Unknown',
                'patient_number' => $row['patient_number'] ?? 'N/A', 
                'patient_phone' => $row['patient_phone'] ?? '',
                'patient_gender' => $row['patient_gender'] ?? '', 
                'date_of_birth' => $row['date_of_birth'] ?? '',
                'visits' => [], 'total_amount' => 0, 'total_items' => 0,
                'partial_bills' => 0,
                'bills_with_partial' => []
            ];
        }
        
        if ($row['bill_status'] === 'partial' && !in_array($row['bill_id'], $consultations_data[$pid]['bills_with_partial'])) {
            $consultations_data[$pid]['bills_with_partial'][] = $row['bill_id'];
            $consultations_data[$pid]['partial_bills']++;
        }
        
        $consultations_data[$pid]['visits'][$vid] = [
            'visit_id' => $vid, 'visit_number' => $row['visit_number'] ?? 'N/A',
            'visit_date' => $row['visit_date'] ?? $row['created_at'], 
            'doctor_name' => $row['doctor_name'] ?? 'N/A',
            'receptionist_name' => $row['receptionist_name'] ?? 'N/A', 
            'visit_type' => $row['visit_type'] ?? 'N/A',
            'consultation_fee' => $row['consultation_fee'] ?? 0, 
            'diagnosis' => $row['diagnosis'] ?? '',
            'symptoms' => $row['symptoms'] ?? '', 
            'status' => $row['visit_status'] ?? 'N/A',
            'payment_status' => $row['payment_status'] ?? 'N/A', 
            'visit_total' => $row['visit_total'] ?? 0,
            'bill_status' => $row['bill_status'] ?? 'pending',
            'bill_number' => $row['bill_number'] ?? null,
            'bill_id' => $row['bill_id'] ?? null,
            'item_status' => $row['status'] ?? 'pending',
            'bill_item_id' => $row['id'] ?? 0,
        ];
        $consultations_data[$pid]['total_amount'] += $row['consultation_fee'] ?? 0;
        $consultations_data[$pid]['total_items']++;
    }
    
    $consultations_array = array_values($consultations_data);
    foreach ($consultations_array as &$p) {
        $p['visit_count'] = count($p['visits']);
        $p['visits'] = array_values($p['visits']);
    }
    unset($p);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(v.consultation_fee),0) as s 
                          FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $consultations_stats['total'] = $r['c'] ?? 0;
    $consultations_stats['amount'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where AND bi.status = 'pending'");
    $stmt->execute($params);
    $consultations_stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where AND bi.status = 'paid'");
    $stmt->execute($params);
    $consultations_stats['paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where AND bi.status = 'partial'");
    $stmt->execute($params);
    $consultations_stats['partial'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
}

// ================================================================
// TAB 3: ALL BILLS - ✅ V17: ONLY BILLS WITH patient_id
// ================================================================
$bills_data = [];
$bills_array = [];
$bills_stats = ['total_bills'=>0, 'paid'=>0, 'pending'=>0, 'partial'=>0, 
                'total_paid_amt'=>0, 'total_pending_amt'=>0, 
                'total_premium'=>0, 'total_discount'=>0,
                'total_billed_amt'=>0, 'paid_percentage'=>0];

if ($active_tab === 'all_bills') {
    // ✅ V17: LAZIMISHA patient_id + visit_id + NOT BILL-OTC-%
    $where = " WHERE b.patient_id IS NOT NULL 
               AND b.visit_id IS NOT NULL
               AND b.bill_number NOT LIKE 'BILL-OTC-%'";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (b.bill_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if (!empty($status_filter)) { $where .= " AND b.status = ?"; $params[] = $status_filter; }
    if ($selected_branch_id !== 'all') { $where .= " AND b.branch_id = ?"; $params[] = (int)$selected_branch_id; }
    if (!empty($quick_date_from)) { $where .= " AND DATE(b.created_at) >= ?"; $params[] = $quick_date_from; }
    if (!empty($quick_date_to)) { $where .= " AND DATE(b.created_at) <= ?"; $params[] = $quick_date_to; }
    
    $sql = "
        SELECT b.*, pat.full_name as patient_name, pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, 
               pat.date_of_birth,
               br.name as branch_name, v.visit_number, v.visit_date, v.diagnosis,
               doc.full_name as doctor_name, rec.full_name as receptionist_name
        FROM bills b
        INNER JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN branches br ON b.branch_id = br.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN users rec ON v.receptionist_id = rec.id
        $where ORDER BY pat.full_name ASC, b.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bills_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bills_rows as $bill) {
        $pid = $bill['patient_id'] ?? 0;
        $vid = $bill['visit_id'] ?? 0;
        
        $received_by = null;
        $payments_count = 0;
        $all_payments = [];
        if (($bill['paid_amount'] ?? 0) > 0) {
            $stmt_pay = $db->prepare("
                SELECT p.amount, p.received_at, p.payment_method, p.receipt_number,
                       u.full_name as received_by_name, u.role as received_by_role
                FROM payments p
                LEFT JOIN users u ON p.received_by = u.id
                WHERE p.bill_id = ?
                ORDER BY p.received_at DESC
            ");
            $stmt_pay->execute([$bill['id']]);
            $all_payments = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);
            $payments_count = count($all_payments);
            $received_by = $all_payments[0] ?? null;
        }
        $bill['received_by'] = $received_by;
        $bill['payments_count'] = $payments_count;
        $bill['all_payments'] = $all_payments;
        
        if (!isset($bills_data[$pid])) {
            $bills_data[$pid] = [
                'patient_id' => $pid, 'patient_name' => $bill['patient_name'] ?? 'Unknown',
                'patient_number' => $bill['patient_number'] ?? 'N/A', 
                'patient_phone' => $bill['patient_phone'] ?? '',
                'patient_gender' => $bill['patient_gender'] ?? '', 
                'date_of_birth' => $bill['date_of_birth'] ?? '',
                'visits' => [], 'total_bills' => 0, 'total_paid' => 0, 'total_pending' => 0,
                'total_partial' => 0, 'total_premium' => 0, 'total_discount' => 0, 
                'total_amount' => 0, 'total_paid_amt' => 0, 'total_balance' => 0
            ];
        }
        
        if (!isset($bills_data[$pid]['visits'][$vid])) {
            $bills_data[$pid]['visits'][$vid] = [
                'visit_id' => $vid, 'visit_number' => $bill['visit_number'] ?? 'N/A',
                'visit_date' => $bill['visit_date'] ?? $bill['created_at'],
                'doctor_name' => $bill['doctor_name'] ?? 'N/A',
                'receptionist_name' => $bill['receptionist_name'] ?? 'N/A',
                'diagnosis' => $bill['diagnosis'] ?? '',
                'bills' => [], 'total_amount' => 0,
                'sum_total' => 0, 'sum_paid' => 0, 'sum_balance' => 0,
                'sum_discount' => 0, 'sum_premium' => 0
            ];
        }
        
        $stmtItems = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? 
            ORDER BY 
                CASE item_type 
                    WHEN 'consultation' THEN 1
                    WHEN 'lab_test' THEN 2
                    WHEN 'medication' THEN 3
                    WHEN 'procedure' THEN 4
                    WHEN 'equipment' THEN 5
                    ELSE 6
                END, id ASC
        ");
        $stmtItems->execute([$bill['id']]);
        $bill['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        $bills_data[$pid]['visits'][$vid]['bills'][] = $bill;
        $bills_data[$pid]['visits'][$vid]['total_amount'] += $bill['total_amount'];
        $bills_data[$pid]['visits'][$vid]['sum_total'] += $bill['total_amount'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_paid'] += $bill['paid_amount'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_balance'] += $bill['balance'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_discount'] += $bill['total_discount'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_premium'] += $bill['premium_amount'] ?? 0;
        
        $bills_data[$pid]['total_bills']++;
        $bills_data[$pid]['total_amount'] += $bill['total_amount'];
        $bills_data[$pid]['total_paid_amt'] += $bill['paid_amount'] ?? 0;
        $bills_data[$pid]['total_balance'] += $bill['balance'] ?? 0;
        
        if ($bill['status'] === 'paid') $bills_data[$pid]['total_paid']++;
        elseif ($bill['status'] === 'pending') $bills_data[$pid]['total_pending']++;
        elseif ($bill['status'] === 'partial') $bills_data[$pid]['total_partial']++;
        
        $bills_data[$pid]['total_premium'] += $bill['premium_amount'] ?? 0;
        $bills_data[$pid]['total_discount'] += $bill['total_discount'] ?? 0;
    }
    
    $bills_array = array_values($bills_data);
    foreach ($bills_array as &$p) {
        $p['visit_count'] = count($p['visits']);
        $p['visits'] = array_values($p['visits']);
        $p['partial_bills'] = $p['total_partial'];
    }
    unset($p);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as s FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $bills_stats['total_bills'] = $r['c'] ?? 0;
    $bills_stats['total_billed_amt'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(paid_amount),0) as s FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where . " AND b.status = 'paid'");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $bills_stats['paid'] = $r['c'] ?? 0;
    $bills_stats['total_paid_amt'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as s FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where . " AND b.status = 'pending'");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $bills_stats['pending'] = $r['c'] ?? 0;
    $bills_stats['total_pending_amt'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where . " AND b.status = 'partial'");
    $stmt->execute($params);
    $bills_stats['partial'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(premium_amount),0) as s FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $bills_stats['total_premium'] = $stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_discount),0) as s FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $bills_stats['total_discount'] = $stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) as s FROM bills b INNER JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $total_paid_all = $stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0;
    
    if ($bills_stats['total_billed_amt'] > 0) {
        $bills_stats['paid_percentage'] = round(($total_paid_all / $bills_stats['total_billed_amt']) * 100, 1);
    }
}

// ================================================================
// TAB 4: OTC BILLS
// ================================================================
$otc_sales_list = [];
$otc_stats = ['total'=>0, 'paid'=>0, 'pending'=>0, 'partial'=>0, 'amount'=>0, 'items_total'=>0];

if ($active_tab === 'otc_bills') {
    $where = " WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (s.sale_number LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ? OR u.full_name LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if (!empty($status_filter)) { $where .= " AND s.payment_status = ?"; $params[] = $status_filter; }
    if ($selected_branch_id !== 'all') { $where .= " AND s.branch_id = ?"; $params[] = (int)$selected_branch_id; }
    if (!empty($quick_date_from)) { $where .= " AND DATE(s.created_at) >= ?"; $params[] = $quick_date_from; }
    if (!empty($quick_date_to)) { $where .= " AND DATE(s.created_at) <= ?"; $params[] = $quick_date_to; }
    
    $sql = "
        SELECT s.id as sale_id, s.sale_number, s.customer_name, s.customer_phone,
               s.subtotal, s.discount_amount, s.premium_amount, s.premium_note, 
               s.total_amount, s.payment_method, s.payment_status, s.sold_by, 
               s.branch_id, s.notes, s.created_at, s.updated_at, s.bill_id,
               u.full_name as sold_by_name, u.role as sold_by_role, 
               b.name as branch_name,
               (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = s.id) as item_count,
               (SELECT COALESCE(SUM(quantity), 0) FROM otc_sale_items WHERE sale_id = s.id) as total_qty
        FROM otc_sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        $where 
        ORDER BY s.created_at DESC 
        LIMIT 500
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $otc_sales_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($otc_sales_list)) {
        $sale_ids = array_column($otc_sales_list, 'sale_id');
        $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
        $item_stmt = $db->prepare("SELECT * FROM otc_sale_items WHERE sale_id IN ($placeholders) ORDER BY id ASC");
        $item_stmt->execute($sale_ids);
        $all_items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $items_by_sale = [];
        foreach ($all_items as $item) {
            $items_by_sale[$item['sale_id']][] = $item;
        }
        
        foreach ($otc_sales_list as &$sale) {
            $sale['items'] = $items_by_sale[$sale['sale_id']] ?? [];
            $otc_stats['items_total'] += count($sale['items']);
        }
        unset($sale);
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(s.total_amount),0) as s FROM otc_sales s LEFT JOIN users u ON s.sold_by = u.id " . $where);
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_stats['total'] = $r['c'] ?? 0;
    $otc_stats['amount'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM otc_sales s LEFT JOIN users u ON s.sold_by = u.id " . $where . " AND s.payment_status = 'paid'");
    $stmt->execute($params);
    $otc_stats['paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM otc_sales s LEFT JOIN users u ON s.sold_by = u.id " . $where . " AND s.payment_status = 'pending'");
    $stmt->execute($params);
    $otc_stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM otc_sales s LEFT JOIN users u ON s.sold_by = u.id " . $where . " AND s.payment_status = 'partial'");
    $stmt->execute($params);
    $otc_stats['partial'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
}

// ================================================================
// MESSAGES
// ================================================================
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* CSS yote ni sawa - copy from original file */
:root {
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --font-main: 'Inter', -apple-system, sans-serif;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
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
    --gray-50: #F8FAFC;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
}
[data-theme="dark"] {
    --bg-body: #0F172A; --bg-card: #1E293B;
    --text-primary: #F1F5F9; --text-secondary: #94A3B8;
    --border-color: #334155;
}
body { font-family: var(--font-main) !important; }
.stat-number, .stat-amount, .stat-value, .stat-pill,
.patient-avatar, .amount-cell, .data-table td:first-child,
.visit-number-display, .visit-date-display {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
}

.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
    border-radius: 16px; padding: 20px 28px; margin-bottom: 20px;
    display: flex; flex-wrap: wrap; justify-content: space-between;
    align-items: center; gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
    position: relative; overflow: hidden;
}
.page-header-custom::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%; pointer-events: none;
}
.page-header-custom .page-title {
    color: white; font-size: 1.4rem; font-weight: 700;
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap; position: relative; z-index: 1; margin: 0;
}
.page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }
.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.82rem;
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap; position: relative; z-index: 1; margin-top: 4px;
}
.page-header-custom .role-badge-display {
    background: rgba(255,255,255,0.2); color: white;
    padding: 3px 12px; border-radius: 20px;
    font-size: 0.6rem; font-weight: 600; text-transform: uppercase;
}
.page-header-custom .branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 2px 10px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 500;
    display: inline-flex; align-items: center; gap: 4px;
}
.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12); color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 14px; border-radius: 8px;
    font-weight: 500; font-size: 0.75rem;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    position: relative; z-index: 1;
}
.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px); color: white;
}

.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }

.tabs-container {
    display: flex; gap: 6px; background: var(--bg-card);
    border-radius: 12px; padding: 6px; margin-bottom: 18px;
    border: 2px solid var(--border-color); overflow-x: auto;
}
.tab-btn {
    padding: 10px 20px; border-radius: 8px;
    font-weight: 700; font-size: 0.78rem; text-decoration: none;
    color: var(--text-secondary); background: transparent;
    border: none; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    white-space: nowrap; transition: all 0.2s ease;
    flex: 1; justify-content: center;
}
.tab-btn:hover { background: var(--primary-bg); color: var(--primary); }
.tab-btn.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; box-shadow: 0 3px 10px rgba(11, 94, 215, 0.3);
}

.stats-grid-6 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 18px;
}

.stat-card-custom {
    border-radius: 16px; padding: 20px 24px;
    display: flex; flex-direction: column;
    transition: all 0.4s ease;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    color: white; position: relative; overflow: hidden;
    min-height: 140px;
    text-decoration: none;
    justify-content: space-between;
}
.stat-card-custom::before {
    content: ''; position: absolute;
    top: -50%; right: -20%; width: 200px; height: 200px;
    background: rgba(255,255,255,0.08); border-radius: 50%;
    pointer-events: none;
}
.stat-card-custom::after {
    content: ''; position: absolute;
    bottom: -30%; left: -10%; width: 150px; height: 150px;
    background: rgba(255,255,255,0.05); border-radius: 50%;
    pointer-events: none;
}
.stat-card-custom:hover { 
    transform: translateY(-6px) scale(1.02); 
    box-shadow: 0 12px 30px rgba(0,0,0,0.25);
}
.stat-card-custom .stat-top {
    display: flex; align-items: center; gap: 12px;
    position: relative; z-index: 1;
}
.stat-card-custom .stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; background: rgba(255,255,255,0.22); color: white;
    border: 1.5px solid rgba(255,255,255,0.18);
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-card-custom .stat-label {
    font-size: 0.72rem; color: rgba(255,255,255,0.9);
    font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.08em; margin: 0;
    line-height: 1.3;
}
.stat-card-custom .stat-number {
    font-size: 2rem; font-weight: 900; color: white;
    margin: 0; line-height: 1.1;
    font-family: var(--font-mono);
    letter-spacing: -0.03em;
    position: relative; z-index: 1;
    margin-top: 12px;
}
.stat-card-custom .stat-amount {
    font-size: 0.8rem; font-weight: 600;
    color: rgba(255,255,255,0.92); 
    margin-top: 6px;
    display: flex; align-items: center; gap: 5px;
    position: relative; z-index: 1;
    font-family: var(--font-mono);
}
.stat-card-custom .stat-amount i { font-size: 0.7rem; opacity: 0.85; }

.card-blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.card-green  { background: linear-gradient(135deg, #10B981, #059669, #047857); }
.card-red    { background: linear-gradient(135deg, #DC2626, #B91C1C, #991B1B); }
.card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9, #5B21B6); }
.card-orange { background: linear-gradient(135deg, #F59E0B, #D97706, #B45309); }
.card-cyan   { background: linear-gradient(135deg, #06B6D4, #0891B2, #0E7490); }
.card-pink   { background: linear-gradient(135deg, #EC4899, #DB2777, #BE185D); }

.stat-card-custom.card-percentage {
    background: linear-gradient(135deg, #059669, #047857, #065F46);
}
.stat-card-custom.card-percentage .stat-number {
    font-size: 2.4rem;
    color: #6EE7B7;
    text-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.stat-card-custom.card-percentage .stat-amount {
    color: rgba(255,255,255,0.95);
    font-weight: 700;
}

.paid-progress-bar {
    margin-top: 12px;
    height: 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    overflow: hidden;
    position: relative; z-index: 1;
}
.paid-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6EE7B7, #34D399, #10B981);
    border-radius: 10px;
    transition: width 0.8s ease;
    box-shadow: 0 0 10px rgba(110, 231, 183, 0.5);
}

/* ✅ V17: SEARCH - CHINI YA CARDS */
.search-section-wrapper {
    margin-bottom: 16px;
}
.med-search-panel {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 12px; padding: 12px 16px; margin-bottom: 12px;
    box-shadow: 0 3px 12px rgba(11, 94, 215, 0.2);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.med-search-panel .search-label {
    color: white; font-size: 0.72rem; font-weight: 700;
    display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.med-search-panel .search-box {
    position: relative; display: flex; align-items: center;
    background: rgba(255,255,255,0.18);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 0 12px;
    height: 38px; flex: 1; min-width: 200px;
}
.med-search-panel .search-box input {
    flex: 1; background: transparent; border: none;
    outline: none; color: white; font-size: 0.82rem;
    font-weight: 500; font-family: var(--font-mono);
}
.med-search-panel .search-box input::placeholder { color: rgba(255,255,255,0.65); }

.quick-filters {
    display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
    background: var(--bg-card); border-radius: 12px;
    padding: 10px 16px; border: 1px solid var(--border-color);
    margin-bottom: 14px;
}
.quick-filter-btn {
    padding: 5px 12px; border-radius: 18px;
    font-size: 0.68rem; font-weight: 700;
    border: 2px solid var(--border-color);
    background: var(--bg-card); color: var(--text-secondary);
    cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
}
.quick-filter-btn:hover {
    border-color: var(--primary); color: var(--primary);
    background: var(--primary-bg);
}
.quick-filter-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-color: var(--primary); color: white;
}

.patient-card {
    background: var(--bg-card);
    border-radius: 16px;
    border: 3px solid var(--primary);
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(11, 94, 215, 0.15), 0 0 0 1px rgba(11, 94, 215, 0.1);
    transition: all 0.35s ease;
}
.patient-card:hover {
    border-color: var(--primary-light);
    box-shadow: 0 12px 40px rgba(11, 94, 215, 0.25), 0 0 0 1px rgba(11, 94, 215, 0.2);
    transform: translateY(-2px);
}
.patient-card.has-partial {
    border-color: #7C3AED;
    box-shadow: 0 8px 30px rgba(124, 58, 237, 0.15), 0 0 0 1px rgba(124, 58, 237, 0.1);
}
.patient-card.filtered-out { display: none; }

.patient-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #7C3AED);
    color: white; padding: 16px 24px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap;
    gap: 14px; cursor: pointer;
    position: relative; overflow: hidden;
}
.patient-header.has-partial { 
    background: linear-gradient(135deg, #7C3AED, #6D28D9, #5B21B6);
}
.patient-header > * { position: relative; z-index: 1; }
.patient-header .patient-info {
    display: flex; align-items: center; gap: 14px;
    flex: 1; min-width: 250px;
}
.patient-header .patient-avatar {
    width: 52px; height: 52px; border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.3rem; color: white;
    border: 3px solid rgba(255,255,255,0.4);
    text-transform: uppercase;
    flex-shrink: 0;
}
.patient-header .patient-name {
    font-weight: 800; font-size: 1.1rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.patient-header .patient-meta {
    display: flex; gap: 14px; font-size: 0.75rem;
    opacity: 0.92; flex-wrap: wrap; margin-top: 4px;
}
.patient-header .patient-meta span {
    display: flex; align-items: center; gap: 4px;
    font-weight: 600;
}
.patient-header .patient-stats {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
}
.patient-header .patient-stats .stat-pill {
    background: rgba(255,255,255,0.22);
    padding: 6px 14px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
}
.patient-header .chevron {
    font-size: 1rem; transition: transform 0.3s ease;
    margin-left: 4px;
}
.patient-header .chevron.rotated { transform: rotate(180deg); }

.partial-badge {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5);
    animation: pulse-partial 2s infinite;
    border: 1px solid rgba(255,255,255,0.3);
}
@keyframes pulse-partial {
    0%, 100% { transform: scale(1); box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5); }
    50% { transform: scale(1.05); box-shadow: 0 3px 12px rgba(245, 158, 11, 0.8); }
}

.patient-body {
    max-height: 0; overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
}
.patient-body.open { max-height: 30000px; padding: 18px 24px 20px; }

.patient-actions {
    display: flex; justify-content: space-between;
    align-items: center; gap: 12px;
    padding-bottom: 14px;
    border-bottom: 2px dashed var(--border-color);
    margin-bottom: 18px; flex-wrap: wrap;
}
.patient-actions-info {
    font-size: 0.78rem; color: var(--text-secondary);
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.patient-actions-info strong { font-weight: 800; color: var(--primary); }
.btn-patient-view {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 20px; border-radius: 8px;
    font-weight: 800; font-size: 0.75rem;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; text-decoration: none;
    border: none; cursor: pointer;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    transition: all 0.25s;
}
.btn-patient-view:hover { 
    transform: translateY(-2px); color: white; 
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
}

.patient-footer {
    background: linear-gradient(135deg, rgba(11, 94, 215, 0.08), rgba(124, 58, 237, 0.05));
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    border-top: 3px dashed var(--primary);
    position: relative;
}
.patient-footer.has-partial {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(109, 40, 217, 0.05));
    border-top-color: var(--purple);
}
.patient-footer .footer-left {
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap; flex: 1; min-width: 280px;
}
.patient-footer .footer-end-label {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 0.88rem; font-weight: 900;
    color: var(--primary);
    text-transform: uppercase; letter-spacing: 0.05em;
    background: white; padding: 8px 16px; border-radius: 10px;
    border: 2px solid var(--primary);
    box-shadow: 0 2px 8px rgba(11, 94, 215, 0.15);
}
.patient-footer.has-partial .footer-end-label {
    color: var(--purple); border-color: var(--purple);
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.15);
}
.patient-footer .footer-end-label i { font-size: 0.95rem; color: var(--primary); }
.patient-footer.has-partial .footer-end-label i { color: var(--purple); }
.patient-footer .footer-visit-count {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.72rem; font-weight: 800;
    color: var(--text-primary); padding: 6px 14px;
    background: var(--bg-card); border-radius: 8px;
    border: 1.5px solid var(--border-color);
    text-transform: uppercase; letter-spacing: 0.04em;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.patient-footer .footer-visit-count i {
    color: var(--primary); font-size: 0.75rem;
}
.patient-footer .footer-right {
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
}
.patient-footer .footer-stat {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.72rem; font-weight: 700;
    color: var(--text-secondary); background: var(--bg-card);
    padding: 7px 14px; border-radius: 8px;
    border: 1.5px solid var(--border-color);
    text-transform: uppercase; letter-spacing: 0.03em;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.patient-footer .footer-stat strong {
    font-family: var(--font-mono);
    color: var(--text-primary); font-weight: 900;
    font-size: 0.82rem; text-transform: none;
    letter-spacing: -0.02em;
}
.patient-footer .footer-stat.success { 
    border-color: rgba(5, 150, 105, 0.4);
    background: linear-gradient(135deg, #F0FDF4, #DCFCE7);
}
.patient-footer .footer-stat.success strong { color: var(--success); }
.patient-footer .footer-stat.danger { 
    border-color: rgba(220, 38, 38, 0.4);
    background: linear-gradient(135deg, #FEF2F2, #FEE2E2);
}
.patient-footer .footer-stat.danger strong { color: var(--danger); }

.visit-section {
    border: 2px solid var(--border-color);
    border-radius: 12px; margin-bottom: 18px;
    overflow: hidden; transition: all 0.3s ease;
}
.visit-section:last-child { margin-bottom: 0; }
.visit-section:hover { 
    border-color: var(--primary); 
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.1);
}

.visit-section-header {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    padding: 12px 18px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 10px;
    border-bottom: 2px solid var(--primary-light);
}
[data-theme="dark"] .visit-section-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.visit-info-left {
    display: flex; align-items: center; gap: 10px;
    flex-wrap: wrap; flex: 1;
}
.visit-icon-badge {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F; display: flex;
    align-items: center; justify-content: center;
    font-size: 1.05rem;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
    flex-shrink: 0;
}
.visit-number-display {
    font-weight: 800; font-size: 0.88rem;
    color: #78350F; background: rgba(255,255,255,0.6);
    padding: 4px 12px; border-radius: 8px;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
[data-theme="dark"] .visit-number-display { color: #FCD34D; background: rgba(255,255,255,0.1); }
.visit-date-display {
    font-size: 0.74rem; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 4px; font-weight: 700;
}
.visit-doctor-display {
    font-size: 0.74rem; color: var(--primary); font-weight: 800;
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(255,255,255,0.6);
    padding: 4px 10px; border-radius: 20px;
    border: 1px solid var(--primary-light);
}
[data-theme="dark"] .visit-doctor-display { background: rgba(255,255,255,0.1); color: #93C5FD; }
.visit-stats-right {
    display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
}
.visit-mini-stat {
    background: rgba(255,255,255,0.75);
    padding: 4px 11px; border-radius: 16px;
    font-size: 0.68rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
[data-theme="dark"] .visit-mini-stat { background: rgba(255,255,255,0.12); }
.visit-mini-stat.stat-paid { background: rgba(16,185,129,0.2); color: #047857; }
.visit-mini-stat.stat-balance { background: rgba(220,38,38,0.15); color: #B91C1C; }

.table-nav-group {
    display: inline-flex; align-items: center; gap: 2px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 2px;
    box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    margin-left: 8px;
}
.table-nav-btn {
    background: rgba(255,255,255,0.15); border: none;
    color: white; width: 28px; height: 28px;
    border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center;
    justify-content: center; font-size: 0.72rem;
    transition: all 0.2s ease;
}
.table-nav-btn:hover:not(:disabled) {
    background: rgba(255,255,255,0.35); transform: scale(1.1);
}
.table-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.table-nav-indicator {
    font-size: 0.6rem; font-weight: 800;
    font-family: var(--font-mono);
    color: white; padding: 0 6px;
    min-width: 38px; text-align: center;
    background: rgba(255,255,255,0.15);
    border-radius: 5px; height: 24px;
    line-height: 24px; letter-spacing: 0.02em;
}

.item-type-header .table-nav-group {
    padding: 1px;
    border-radius: 7px;
    margin-left: 4px;
}
.item-type-header .table-nav-btn {
    width: 24px;
    height: 24px;
    font-size: 0.62rem;
    border-radius: 5px;
}
.item-type-header .table-nav-indicator {
    font-size: 0.55rem;
    min-width: 32px;
    height: 20px;
    line-height: 20px;
    padding: 0 5px;
}

.visit-summary-box {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
    background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 12px;
    border: 2px solid var(--primary-light);
}
[data-theme="dark"] .visit-summary-box { background: linear-gradient(135deg, #1E293B, #0F172A); }
.visit-summary-item {
    display: flex; flex-direction: column;
    gap: 2px; text-align: center;
    padding: 6px 8px;
    border-right: 1px solid var(--border-color);
}
.visit-summary-item:last-child { border-right: none; }
.visit-summary-item .label {
    font-size: 0.55rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-secondary);
}
.visit-summary-item .value {
    font-size: 0.95rem; font-weight: 800;
    font-family: var(--font-mono);
    color: var(--primary);
}
.visit-summary-item .value.green { color: var(--success); }
.visit-summary-item .value.red { color: var(--danger); }
.visit-summary-item .value.purple { color: var(--purple); }
.visit-summary-item .value.cyan { color: var(--cyan); }

.visit-section-body {
    padding: 12px 16px 14px; background: var(--bg-card);
    overflow-x: auto;
    scroll-behavior: smooth;
}
.table-scroll-wrapper { position: relative; }
.table-scroll {
    overflow-x: auto; scroll-behavior: smooth;
    border-radius: 10px;
}
.table-scroll::-webkit-scrollbar { height: 8px; }
.table-scroll::-webkit-scrollbar-track {
    background: var(--bg-body); border-radius: 10px;
}
.table-scroll::-webkit-scrollbar-thumb {
    background: var(--primary); border-radius: 10px;
}
.data-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem; min-width: 1200px;
}
.data-table thead th {
    text-align: left; padding: 8px 12px;
    font-weight: 700; font-size: 0.6rem;
    text-transform: uppercase; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-bottom: 3px solid #083C8A;
    white-space: nowrap;
}
.data-table tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:nth-child(even) td { background: var(--gray-50); }

.status-badge {
    padding: 3px 10px; border-radius: 18px;
    font-size: 0.6rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 3px;
    white-space: nowrap;
}
.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger  { background: #FEE2E2; color: #EF4444; }
.status-badge.info    { background: #E8F0FE; color: #0B5ED7; }
.status-badge.purple  { background: #EDE9FE; color: #7C3AED; }
.status-badge.cyan    { background: #CFFAFE; color: #0891B2; }

.amount-cell {
    font-weight: 700; color: var(--primary); font-size: 0.8rem;
    font-family: var(--font-mono);
}
.amount-cell.green { color: var(--success); }
.amount-cell.red { color: var(--danger); }

.action-buttons-group {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
    padding: 2px;
}
.btn-action-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-width: 72px;
    height: 30px;
    padding: 0 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.62rem;
    line-height: 1;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
    font-family: var(--font-main);
    white-space: nowrap;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.btn-action-sm.view {
    background: linear-gradient(135deg, #3B82F6, #0B5ED7);
    color: white;
}
.btn-action-sm.view:hover {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.45);
    color: white;
}
.btn-action-sm.edit {
    background: linear-gradient(135deg, #FBBF24, #D97706);
    color: white;
}
.btn-action-sm.edit:hover {
    background: linear-gradient(135deg, #F59E0B, #B45309);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(217, 119, 6, 0.45);
    color: white;
}
.btn-action-sm.delete {
    background: linear-gradient(135deg, #F87171, #DC2626);
    color: white;
}
.btn-action-sm.delete:hover {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.45);
    color: white;
}
.delete-form-inline { display: inline; margin: 0; padding: 0; }

.bill-group {
    margin-bottom: 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px; overflow: hidden;
}
.bill-group-header {
    background: linear-gradient(135deg, #F1F5F9, #E2E8F0);
    padding: 8px 14px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 8px;
    font-size: 0.72rem; font-weight: 700;
}
[data-theme="dark"] .bill-group-header { background: linear-gradient(135deg, #1E293B, #0F172A); }
.bill-group-header .bill-num {
    font-family: var(--font-mono);
    color: var(--primary); font-weight: 800;
}
.bill-group-body { padding: 12px; }

.item-type-section { margin-bottom: 14px; }
.item-type-section:last-child { margin-bottom: 0; }
.item-type-header {
    font-size: 0.65rem; font-weight: 800;
    color: var(--primary); text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 6px;
    padding: 4px 10px;
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border-radius: 6px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 6px;
    border-left: 3px solid var(--primary);
}
[data-theme="dark"] .item-type-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.item-type-header .title-group {
    display: inline-flex; align-items: center; gap: 6px;
}
.item-type-header .item-count {
    background: rgba(11,94,215,0.15);
    color: var(--primary);
    padding: 2px 8px; border-radius: 10px;
    font-size: 0.6rem; font-weight: 800;
}

.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text-secondary); background: var(--bg-card);
    border-radius: 14px; border: 2px dashed var(--border-color);
}
.empty-state i {
    font-size: 3.5rem; color: var(--primary);
    display: block; margin-bottom: 12px;
}
.empty-state p {
    font-size: 1rem; font-weight: 600; color: var(--text-primary);
}
.empty-state .sub {
    font-size: 0.85rem; color: var(--text-secondary);
    margin-top: 4px; font-weight: 400;
}

.footer {
    padding: 14px 0; border-top: 1px solid var(--border-color);
    margin-top: 24px; text-align: center;
    font-size: 0.7rem; color: var(--text-secondary);
}
.footer .footer-brand { color: var(--primary); font-weight: 600; }

.otc-cards-container {
    overflow-x: auto;
    scroll-behavior: smooth;
    padding: 8px 0;
    min-width: 100%;
}
.otc-sale-card {
    background: var(--bg-card);
    border: 2px solid var(--cyan);
    border-radius: 16px;
    margin: 16px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.1);
    transition: all 0.3s ease;
}
.otc-sale-card:hover {
    box-shadow: 0 8px 28px rgba(8, 145, 178, 0.2);
    border-color: #22D3EE;
}
.otc-sale-header {
    background: linear-gradient(135deg, #0891B2, #0E7490);
    padding: 14px 20px;
    display: flex; flex-wrap: wrap;
    justify-content: space-between; align-items: center;
    gap: 12px; color: white;
}
.otc-header-left {
    display: flex; align-items: center;
    gap: 12px; flex-wrap: wrap;
    flex: 1; min-width: 300px;
}
.otc-sale-id-badge {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border: 1.5px solid rgba(255,255,255,0.4);
    color: white; padding: 6px 14px;
    border-radius: 8px; font-size: 0.78rem;
    font-weight: 900; font-family: var(--font-mono);
    display: inline-flex; align-items: center;
    gap: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.otc-customer-info { display: flex; flex-direction: column; gap: 2px; }
.otc-customer-name {
    font-size: 0.88rem; font-weight: 900;
    display: flex; align-items: center;
    gap: 6px; color: white;
}
.otc-customer-phone {
    font-size: 0.68rem; color: rgba(255,255,255,0.85);
    font-weight: 600; display: flex; align-items: center; gap: 4px;
}
.otc-header-middle {
    display: flex; gap: 10px;
    align-items: center; flex-wrap: wrap;
}
.otc-stat-chip {
    display: flex; flex-direction: column; gap: 2px;
    padding: 8px 14px; border-radius: 12px;
    min-width: 110px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.otc-stat-chip .stat-chip-label {
    font-size: 0.55rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: rgba(255,255,255,0.85);
    display: flex; align-items: center; gap: 4px;
}
.otc-stat-chip .stat-chip-value {
    font-size: 0.95rem; font-weight: 900;
    font-family: var(--font-mono); color: white;
}
.otc-stat-chip.discount {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.4), rgba(217, 119, 6, 0.2));
    border-color: rgba(251, 191, 36, 0.6);
}
.otc-stat-chip.discount .stat-chip-value { color: #FEF3C7; }
.otc-stat-chip.premium {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.4), rgba(124, 58, 237, 0.2));
    border-color: rgba(167, 139, 250, 0.6);
}
.otc-stat-chip.premium .stat-chip-value { color: #EDE9FE; }
.otc-stat-chip.grand-total {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.5), rgba(5, 150, 105, 0.3));
    border-color: rgba(52, 211, 153, 0.7);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}
.otc-stat-chip.grand-total .stat-chip-value { color: #D1FAE5; font-size: 1.05rem; }
.otc-header-right {
    display: flex; gap: 8px;
    align-items: center; flex-wrap: wrap;
}
.otc-action-btn {
    padding: 8px 14px; border-radius: 8px;
    font-weight: 800; font-size: 0.68rem;
    border: 1.5px solid rgba(255,255,255,0.3);
    cursor: pointer; transition: all 0.25s;
    display: inline-flex; align-items: center;
    gap: 5px; text-decoration: none;
    text-transform: uppercase; letter-spacing: 0.03em;
    white-space: nowrap; backdrop-filter: blur(10px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.otc-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); }
.otc-action-btn.view { background: rgba(255,255,255,0.2); color: white; }
.otc-action-btn.view:hover { background: rgba(255,255,255,0.35); }
.otc-action-btn.edit { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; border-color: transparent; }
.otc-action-btn.delete { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; border-color: transparent; }
.otc-scroll-buttons { display: flex; gap: 5px; margin-left: 8px; }
.otc-scroll-btn {
    width: 32px; height: 32px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.2);
    color: white; cursor: pointer;
    display: inline-flex; align-items: center;
    justify-content: center; font-size: 0.72rem;
    font-weight: 700; transition: all 0.25s;
    backdrop-filter: blur(10px); flex-shrink: 0;
}
.otc-scroll-btn:hover { background: rgba(255,255,255,0.4); transform: translateY(-2px); }
.otc-items-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.otc-items-table thead th {
    text-align: left; padding: 10px 14px;
    font-weight: 800; font-size: 0.6rem;
    text-transform: uppercase; letter-spacing: 0.08em;
    color: white;
    background: linear-gradient(135deg, #0891B2, #0E7490);
    white-space: nowrap;
}
.otc-items-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle; font-weight: 500;
}
.otc-items-table tbody tr:hover td { background: var(--cyan-bg); }
.otc-items-table tbody tr:last-child td { border-bottom: none; }
.otc-item-name-cell {
    display: flex; align-items: center;
    gap: 8px; font-weight: 700; color: var(--text-primary);
}
.otc-item-name-cell .item-icon {
    width: 26px; height: 26px; border-radius: 6px;
    background: linear-gradient(135deg, #0891B2, #22D3EE);
    color: white; display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 0.7rem; flex-shrink: 0;
}
.otc-qty-badge {
    display: inline-flex; align-items: center;
    justify-content: center; min-width: 36px;
    padding: 4px 10px; border-radius: 6px;
    background: var(--cyan-bg); color: var(--cyan);
    font-family: var(--font-mono); font-weight: 800;
    font-size: 0.78rem;
    border: 1.5px solid rgba(8, 145, 178, 0.3);
}
.otc-sale-footer {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.08), rgba(8, 145, 178, 0.03));
    padding: 10px 20px;
    display: flex; justify-content: space-between;
    align-items: center; gap: 12px;
    flex-wrap: wrap; border-top: 2px dashed var(--cyan);
}
.otc-footer-info {
    display: flex; gap: 14px;
    align-items: center; flex-wrap: wrap;
}
.otc-footer-stat {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.65rem; font-weight: 700;
    color: var(--text-secondary);
    background: var(--bg-card); padding: 4px 10px;
    border-radius: 6px; border: 1px solid var(--border-color);
}
.otc-footer-stat strong {
    font-family: var(--font-mono);
    color: var(--text-primary); font-weight: 900;
}

.received-by { display: flex; align-items: center; gap: 8px; }
.received-by-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: linear-gradient(135deg, #0891B2, #22D3EE);
    color: white; display: flex;
    align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.68rem;
    flex-shrink: 0; text-transform: uppercase;
}
.received-by-info { display: flex; flex-direction: column; gap: 2px; }
.received-by-name { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); }
.received-by-role { font-size: 0.55rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; }

.role-tag { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.5rem; font-weight: 800; text-transform: uppercase; }
.role-tag.cashier { background: #FEF3C7; color: #D97706; }
.role-tag.reception { background: #DBEAFE; color: #1E40AF; }
.role-tag.pharmacy { background: #D1FAE5; color: #059669; }
.role-tag.admin { background: #FCE7F3; color: #BE185D; }
.role-tag.doctor { background: #EDE9FE; color: #7C3AED; }
.role-tag.laboratory { background: #CFFAFE; color: #0891B2; }
.role-tag.user { background: var(--border-color); color: var(--text-secondary); }

.payment-badge { 
    display: inline-flex; align-items: center; gap: 5px; 
    padding: 4px 10px; border-radius: 6px; 
    font-size: 0.66rem; font-weight: 700; 
    background: var(--primary-bg); color: var(--primary); 
    white-space: nowrap; 
}

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--bg-card); border-radius: 20px; max-width: 500px; width: 100%; padding: 32px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); text-align: center; }
.modal-icon { width: 72px; height: 72px; border-radius: 50%; background: var(--danger-bg); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 18px; }
.modal-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 10px; color: var(--text-primary); }
.modal-text { font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.7; }
.modal-text strong { color: var(--primary); background: var(--primary-bg); padding: 3px 10px; border-radius: 6px; font-family: var(--font-mono); font-weight: 700; display: inline-block; margin: 4px 0; }
.modal-warning { color: var(--danger); font-weight: 700; display: block; margin-top: 8px; font-size: 0.8rem; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.modal-btn { padding: 11px 26px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 8px; }
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: var(--border-color); transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5); }

@media (max-width: 1200px) { 
    .stats-grid-6 { grid-template-columns: repeat(2, 1fr); } 
}
@media (max-width: 1024px) { 
    .stats-grid-6 { grid-template-columns: repeat(2, 1fr); }
    .patient-footer { flex-direction: column; align-items: stretch; }
    .patient-footer .footer-right { justify-content: flex-start; }
}
@media (max-width: 768px) {
    .page-header-custom { padding: 14px 16px; }
    .stats-grid-6 { grid-template-columns: 1fr; gap: 12px; }
    .stat-card-custom { min-height: 120px; padding: 16px 18px; }
    .stat-card-custom .stat-number { font-size: 1.6rem; }
    .tabs-container { flex-direction: column; }
    .tab-btn { justify-content: flex-start; }
    .patient-header { flex-direction: column; align-items: stretch; }
    .visit-section-header { flex-direction: column; align-items: stretch; }
    .action-buttons-group { flex-wrap: wrap; justify-content: flex-start; }
    .btn-action-sm { min-width: 65px; font-size: 0.58rem; }
    .patient-footer { flex-direction: column; align-items: stretch; }
    .patient-footer .footer-left { min-width: auto; }
    .patient-footer .footer-right { justify-content: flex-start; }
    .otc-sale-header { flex-direction: column; align-items: stretch; }
    .otc-stat-chip { min-width: auto; flex: 1; }
    .otc-header-right { justify-content: space-between; }
}
</style>

<main class="main-content">

    <?php if (!empty($success_message)): ?>
        <div class="alert success" id="alertBox">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($success_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 5000);
        </script>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert error" id="alertBoxError">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBoxError');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-concierge-bell"></i>
                Other Services
                <span class="role-badge-display">ADMIN AUDIT</span>
                <span class="branch-tag" style="background:rgba(16,185,129,0.3);color:#6EE7B7;"><i class="fas fa-check-circle"></i> V17</span>
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag"><i class="fas fa-th-large"></i> Procedures • Equipments • Consultations • Bills • OTC</span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <a href="<?= buildFilterUrl(['tab' => 'procedures']) ?>" 
           class="tab-btn <?= $active_tab === 'procedures' ? 'active' : '' ?>">
            <i class="fas fa-syringe"></i> Procedures & Equipments
        </a>
        <a href="<?= buildFilterUrl(['tab' => 'consultations']) ?>" 
           class="tab-btn <?= $active_tab === 'consultations' ? 'active' : '' ?>">
            <i class="fas fa-stethoscope"></i> Consultations
        </a>
        <a href="<?= buildFilterUrl(['tab' => 'all_bills']) ?>" 
           class="tab-btn <?= $active_tab === 'all_bills' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> All Bills
        </a>
        <a href="<?= buildFilterUrl(['tab' => 'otc_bills']) ?>" 
           class="tab-btn <?= $active_tab === 'otc_bills' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> OTC Bills
        </a>
    </div>

    <!-- QUICK FILTERS -->
    <div class="quick-filters">
        <span style="font-size:0.68rem;font-weight:700;color:var(--text-secondary);">
            <i class="fas fa-bolt"></i> Quick:
        </span>
        <a href="<?= buildFilterUrl(['quick' => 'all']) ?>" class="quick-filter-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-infinity"></i> All
        </a>
        <a href="<?= buildFilterUrl(['quick' => 'today']) ?>" class="quick-filter-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1w']) ?>" class="quick-filter-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> 1 Week
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1m']) ?>" class="quick-filter-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1 Month
        </a>
        <a href="<?= buildFilterUrl(['quick' => '3m']) ?>" class="quick-filter-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3 Months
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1y']) ?>" class="quick-filter-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
            <i class="fas fa-calendar"></i> 1 Year
        </a>
    </div>

    <!-- TAB 1: PROCEDURES & EQUIPMENTS -->
    <?php if ($active_tab === 'procedures'): ?>
        
        <div class="stats-grid-6">
            <div class="stat-card-custom card-blue-1">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                    <p class="stat-label">Total Items</p>
                </div>
                <p class="stat-number"><?= $procedures_stats['total'] ?></p>
                <p class="stat-amount"><i class="fas fa-money-bill-wave"></i> <?= formatTsh($procedures_stats['amount']) ?></p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-tools"></i></div>
                    <p class="stat-label">Equipments</p>
                </div>
                <p class="stat-number"><?= $procedures_stats['equipment_count'] ?></p>
                <p class="stat-amount"><i class="fas fa-cog"></i> Equipment Items</p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <p class="stat-label">Pending</p>
                </div>
                <p class="stat-number"><?= $procedures_stats['pending'] ?></p>
                <p class="stat-amount"><i class="fas fa-hourglass-half"></i> Waiting</p>
            </div>
            <div class="stat-card-custom card-green">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <p class="stat-label">Completed</p>
                </div>
                <p class="stat-number"><?= $procedures_stats['completed'] ?></p>
                <p class="stat-amount"><i class="fas fa-check-double"></i> Done</p>
            </div>
            <div class="stat-card-custom card-cyan">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <p class="stat-label">Patients</p>
                </div>
                <p class="stat-number"><?= count($procedures_array ?? []) ?></p>
                <p class="stat-amount"><i class="fas fa-user-injured"></i> With Procedures</p>
            </div>
            <div class="stat-card-custom card-pink">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-procedures"></i></div>
                    <p class="stat-label">Procedures</p>
                </div>
                <p class="stat-number"><?= $procedures_stats['procedure_count'] ?></p>
                <p class="stat-amount"><i class="fas fa-hand-holding-medical"></i> Procedure Items</p>
            </div>
        </div>

        <!-- ✅ V17: SEARCH CHINI YA CARDS -->
        <div class="search-section-wrapper">
            <div class="med-search-panel">
                <div class="search-label">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </div>
                <div class="search-box">
                    <i class="fas fa-search" style="color:white;margin-right:8px;"></i>
                    <input type="text" id="pageSearchInput" 
                           placeholder="Search patient, bill, item..." autocomplete="off">
                </div>
            </div>
        </div>
        
        <?php if (!empty($procedures_array)): ?>
            <?php foreach ($procedures_array as $patient): 
                $pid = $patient['patient_id'];
                $age = calculateAge($patient['date_of_birth']);
                $has_partial = ($patient['partial_bills'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $patient['patient_phone'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient(<?= $pid ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $patient['partial_bills'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-calendar-check"></i> <?= $patient['visit_count'] ?> visit(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-syringe"></i> <?= $patient['total_items'] ?> item(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($patient['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $pid ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $pid ?>">
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $patient['visit_count'] ?></strong> visit(s) • 
                                <strong><?= $patient['total_items'] ?></strong> procedure/equipment item(s) •
                                Total: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </div>
                            <div>
                                <a href="all_procedures.php?patient_id=<?= $pid ?>&branch=<?= $selected_branch_id ?>" 
                                   class="btn-patient-view">
                                    <i class="fas fa-eye"></i> VIEW ALL
                                </a>
                            </div>
                        </div>
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $uid = $pid . '-' . $visit['visit_id'];
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-syringe"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($visit['visit_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-syringe"></i>
                                            Items: <strong style="color:var(--primary);"><?= count($visit['items']) ?></strong>
                                        </span>
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            Total: <strong style="color:var(--primary);"><?= formatTsh($visit['total_amount']) ?></strong>
                                        </span>
                                        <div class="table-nav-group">
                                            <button type="button" class="table-nav-btn scroll-left" 
                                                    onclick="scrollVisitTable('<?= $uid ?>', -1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                                            <button type="button" class="table-nav-btn scroll-right" 
                                                    onclick="scrollVisitTable('<?= $uid ?>', 1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="visit-section-body" id="visit-body-<?= $uid ?>">
                                    <div class="table-scroll-wrapper">
                                        <div class="table-scroll" id="table-<?= $uid ?>">
                                            <table class="data-table" style="min-width:1200px;">
                                                <thead>
                                                    <tr>
                                                        <th style="width:45px;">#</th>
                                                        <th>Item Name</th>
                                                        <th>Type</th>
                                                        <th>Doctor</th>
                                                        <th>Item Status</th>
                                                        <th>Price</th>
                                                        <th>Discount</th>
                                                        <th>Date</th>
                                                        <th style="text-align:center;min-width:280px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $i = 1; foreach ($visit['items'] as $item): 
                                                        $s = getStatusBadge($item['item_status'] ?? 'pending');
                                                        $is_equipment = ($item['item_type'] ?? '') === 'equipment';
                                                        $reference_id = $item['reference_id'] ?? 0;
                                                        $bill_item_id = $item['item_row_id'] ?? 0;
                                                    ?>
                                                        <tr data-search="<?= htmlspecialchars(strtolower($item['procedure_name'])) ?>">
                                                            <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                                            <td>
                                                                <span style="font-weight:700;color:var(--primary);">
                                                                    <?= htmlspecialchars($item['procedure_name']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge <?= $is_equipment ? 'cyan' : 'purple' ?>" style="font-size:0.55rem;">
                                                                    <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i>
                                                                    <?= $is_equipment ? 'Equipment' : 'Procedure' ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span style="font-size:0.72rem;">
                                                                    <i class="fas fa-user-md" style="color:var(--cyan);"></i>
                                                                    <?= htmlspecialchars($item['doctor_name'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge <?= $s['class'] ?>">
                                                                    <?= $s['icon'] ?> <?= $s['label'] ?>
                                                                </span>
                                                            </td>
                                                            <td class="amount-cell"><?= formatTsh($item['procedure_price']) ?></td>
                                                            <td class="amount-cell <?= ($item['discount_amount'] ?? 0) > 0 ? 'red' : '' ?>">
                                                                <?= ($item['discount_amount'] ?? 0) > 0 ? '-' . formatTsh($item['discount_amount']) : '—' ?>
                                                            </td>
                                                            <td style="font-size:0.7rem;"><?= date('d M Y', strtotime($item['item_created_at'])) ?></td>
                                                            <td style="text-align:center;">
                                                                <div class="action-buttons-group">
                                                                    <a href="view_procedure.php?id=<?= $reference_id ?>&bill_item_id=<?= $bill_item_id ?>&type=<?= $item['item_type'] ?>&branch=<?= $selected_branch_id ?>" 
                                                                       class="btn-action-sm view">
                                                                        <i class="fas fa-eye"></i> View
                                                                    </a>
                                                                    <a href="edit_procedure.php?id=<?= $reference_id ?>&bill_item_id=<?= $bill_item_id ?>&type=<?= $item['item_type'] ?>&branch=<?= $selected_branch_id ?>" 
                                                                       class="btn-action-sm edit">
                                                                        <i class="fas fa-edit"></i> Edit
                                                                    </a>
                                                                    <form method="POST" class="delete-form-inline" 
                                                                          onsubmit="return confirm('⚠️ Delete this <?= $is_equipment ? 'Equipment' : 'Procedure' ?>?\n\n<?= htmlspecialchars(addslashes($item['procedure_name'])) ?>\n\nBill itarekebishwa automatically.');">
                                                                        <input type="hidden" name="action" value="delete_item">
                                                                        <input type="hidden" name="bill_item_id" value="<?= (int)$bill_item_id ?>">
                                                                        <input type="hidden" name="reference_id" value="<?= (int)$reference_id ?>">
                                                                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($item['item_type']) ?>">
                                                                        <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                                                                        <button type="submit" class="btn-action-sm delete">
                                                                            <i class="fas fa-trash"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="patient-footer <?= $has_partial ? 'has-partial' : '' ?>">
                        <div class="footer-left">
                            <span class="footer-end-label">
                                <i class="fas fa-user-check"></i>
                                END OF <?= htmlspecialchars(strtoupper($patient['patient_name'])) ?>
                            </span>
                            <span class="footer-visit-count">
                                <i class="fas fa-notes-medical"></i>
                                <?= $patient['visit_count'] ?> Visit<?= $patient['visit_count'] != 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <div class="footer-right">
                            <span class="footer-stat">
                                <i class="fas fa-file-invoice" style="color:var(--primary);"></i>
                                Billed: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </span>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                <p>No procedures or equipments found</p>
                <p class="sub">Try adjusting your filters</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TAB 2: CONSULTATIONS -->
    <?php if ($active_tab === 'consultations'): ?>
        
        <div class="stats-grid-6">
            <div class="stat-card-custom card-blue-1">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                    <p class="stat-label">Total Consultations</p>
                </div>
                <p class="stat-number"><?= $consultations_stats['total'] ?></p>
                <p class="stat-amount"><i class="fas fa-money-bill-wave"></i> <?= formatTsh($consultations_stats['amount']) ?></p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <p class="stat-label">Pending</p>
                </div>
                <p class="stat-number"><?= $consultations_stats['pending'] ?></p>
                <p class="stat-amount"><i class="fas fa-hourglass-half"></i> Unpaid</p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <p class="stat-label">Partial</p>
                </div>
                <p class="stat-number"><?= $consultations_stats['partial'] ?></p>
                <p class="stat-amount"><i class="fas fa-spinner"></i> Partially Paid</p>
            </div>
            <div class="stat-card-custom card-green">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <p class="stat-label">Paid</p>
                </div>
                <p class="stat-number"><?= $consultations_stats['paid'] ?></p>
                <p class="stat-amount"><i class="fas fa-check-double"></i> Completed</p>
            </div>
            <div class="stat-card-custom card-cyan">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <p class="stat-label">Patients</p>
                </div>
                <p class="stat-number"><?= count($consultations_array ?? []) ?></p>
                <p class="stat-amount"><i class="fas fa-user-injured"></i> With Consultations</p>
            </div>
            <div class="stat-card-custom card-pink">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <p class="stat-label">Paid %</p>
                </div>
                <p class="stat-number"><?= $consultations_stats['total'] > 0 ? round(($consultations_stats['paid'] / $consultations_stats['total']) * 100, 1) : 0 ?>%</p>
                <p class="stat-amount"><i class="fas fa-chart-pie"></i> Completion Rate</p>
            </div>
        </div>

        <!-- ✅ V17: SEARCH CHINI YA CARDS -->
        <div class="search-section-wrapper">
            <div class="med-search-panel">
                <div class="search-label">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </div>
                <div class="search-box">
                    <i class="fas fa-search" style="color:white;margin-right:8px;"></i>
                    <input type="text" id="pageSearchInput" 
                           placeholder="Search patient, visit, doctor..." autocomplete="off">
                </div>
            </div>
        </div>
        
        <?php if (!empty($consultations_array)): ?>
            <?php foreach ($consultations_array as $patient): 
                $pid = $patient['patient_id'];
                $age = calculateAge($patient['date_of_birth']);
                $has_partial = ($patient['partial_bills'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient(<?= $pid ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $patient['partial_bills'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-stethoscope"></i> <?= $patient['visit_count'] ?> consultation(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($patient['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $pid ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $pid ?>">
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $patient['visit_count'] ?></strong> consultation(s) •
                                Total Fees: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </div>
                            <div>
                                <a href="view_all_consultations.php?patient_id=<?= $pid ?>&branch=<?= $selected_branch_id ?>" 
                                   class="btn-patient-view">
                                    <i class="fas fa-eye"></i> VIEW ALL CONSULTATIONS
                                </a>
                            </div>
                        </div>
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $uid = $pid . '-v' . $visit['visit_id'];
                            $item_s = getStatusBadge($visit['item_status'] ?? 'pending');
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-stethoscope"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($visit['visit_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            Fee: <strong style="color:var(--primary);"><?= formatTsh($visit['consultation_fee']) ?></strong>
                                        </span>
                                        <span class="status-badge <?= $item_s['class'] ?>" style="font-size:0.58rem;">
                                            <?= $item_s['icon'] ?> <?= $item_s['label'] ?>
                                        </span>
                                        <div class="table-nav-group">
                                            <button type="button" class="table-nav-btn scroll-left" 
                                                    onclick="scrollVisitTable('<?= $uid ?>', -1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                                            <button type="button" class="table-nav-btn scroll-right" 
                                                    onclick="scrollVisitTable('<?= $uid ?>', 1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="visit-section-body" id="visit-body-<?= $uid ?>">
                                    <div class="table-scroll-wrapper">
                                        <div class="table-scroll" id="table-<?= $uid ?>">
                                            <table class="data-table" style="min-width:1100px;">
                                                <thead>
                                                    <tr>
                                                        <th>Visit Number</th>
                                                        <th>Visit Type</th>
                                                        <th>Doctor</th>
                                                        <th>Receptionist</th>
                                                        <th>Diagnosis</th>
                                                        <th>Symptoms</th>
                                                        <th>Fee</th>
                                                        <th style="text-align:center;min-width:280px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr data-search="<?= htmlspecialchars(strtolower($visit['visit_number'] . ' ' . $visit['doctor_name'])) ?>">
                                                        <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($visit['visit_number']) ?></td>
                                                        <td><?= htmlspecialchars($visit['visit_type']) ?></td>
                                                        <td><i class="fas fa-user-md" style="color:var(--cyan);"></i> <?= htmlspecialchars($visit['doctor_name']) ?></td>
                                                        <td><i class="fas fa-user-tie" style="color:var(--purple);"></i> <?= htmlspecialchars($visit['receptionist_name']) ?></td>
                                                        <td style="font-size:0.72rem;color:var(--text-secondary);">
                                                            <?= htmlspecialchars($visit['diagnosis'] ?: '—') ?>
                                                        </td>
                                                        <td style="font-size:0.72rem;color:var(--text-secondary);">
                                                            <?= htmlspecialchars($visit['symptoms'] ?: '—') ?>
                                                        </td>
                                                        <td class="amount-cell"><?= formatTsh($visit['consultation_fee']) ?></td>
                                                        <td style="text-align:center;">
                                                            <div class="action-buttons-group">
                                                                <a href="view_consultation.php?id=<?= $visit['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-sm view">
                                                                    <i class="fas fa-eye"></i> View
                                                                </a>
                                                                <a href="edit_consultation.php?id=<?= $visit['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-sm edit">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </a>
                                                                <form method="POST" class="delete-form-inline" 
                                                                      onsubmit="return confirm('⚠️ Delete this Consultation?\n\nVisit: <?= htmlspecialchars(addslashes($visit['visit_number'])) ?>\nFee: TSh <?= number_format($visit['consultation_fee'], 0) ?>');">
                                                                    <input type="hidden" name="action" value="delete_bill_item_all">
                                                                    <input type="hidden" name="bill_item_id" value="<?= (int)($visit['bill_item_id'] ?? 0) ?>">
                                                                    <input type="hidden" name="item_type" value="consultation">
                                                                    <input type="hidden" name="redirect_tab" value="consultations">
                                                                    <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                                                                    <button type="submit" class="btn-action-sm delete">
                                                                        <i class="fas fa-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="patient-footer <?= $has_partial ? 'has-partial' : '' ?>">
                        <div class="footer-left">
                            <span class="footer-end-label">
                                <i class="fas fa-user-check"></i>
                                END OF <?= htmlspecialchars(strtoupper($patient['patient_name'])) ?>
                            </span>
                            <span class="footer-visit-count">
                                <i class="fas fa-notes-medical"></i>
                                <?= $patient['visit_count'] ?> Consultation<?= $patient['visit_count'] != 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <div class="footer-right">
                            <span class="footer-stat">
                                <i class="fas fa-file-invoice" style="color:var(--primary);"></i>
                                Total Fees: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </span>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <p>No consultations found</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TAB 3: ALL BILLS -->
    <?php if ($active_tab === 'all_bills'): ?>
        
        <div class="stats-grid-6">
            <div class="stat-card-custom card-green">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <p class="stat-label">All Paid Bills</p>
                </div>
                <p class="stat-number"><?= $bills_stats['paid'] ?></p>
                <p class="stat-amount"><i class="fas fa-money-bill-wave"></i> <?= formatTsh($bills_stats['total_paid_amt']) ?></p>
            </div>
            
            <div class="stat-card-custom card-orange">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <p class="stat-label">Pending Bills</p>
                </div>
                <p class="stat-number"><?= $bills_stats['pending'] ?></p>
                <p class="stat-amount"><i class="fas fa-clock"></i> <?= formatTsh($bills_stats['total_pending_amt']) ?></p>
            </div>
            
            <div class="stat-card-custom card-purple">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                    <p class="stat-label">Partial Bills</p>
                </div>
                <p class="stat-number"><?= $bills_stats['partial'] ?></p>
                <p class="stat-amount"><i class="fas fa-hourglass-half"></i> Partially Paid</p>
            </div>
            
            <div class="stat-card-custom card-cyan">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <p class="stat-label">All Premiums</p>
                </div>
                <p class="stat-number" style="font-size:1.6rem;"><?= formatTsh($bills_stats['total_premium']) ?></p>
                <p class="stat-amount"><i class="fas fa-arrow-up"></i> Total Premium Added</p>
            </div>
            
            <div class="stat-card-custom card-red">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <p class="stat-label">All Discounts</p>
                </div>
                <p class="stat-number" style="font-size:1.6rem;"><?= formatTsh($bills_stats['total_discount']) ?></p>
                <p class="stat-amount"><i class="fas fa-arrow-down"></i> Total Discounts Given</p>
            </div>
            
            <div class="stat-card-custom card-percentage">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                    <p class="stat-label">Paid Percentage</p>
                </div>
                <p class="stat-number"><?= $bills_stats['paid_percentage'] ?>%</p>
                <div class="paid-progress-bar">
                    <div class="paid-progress-fill" style="width: <?= $bills_stats['paid_percentage'] ?>%;"></div>
                </div>
                <p class="stat-amount" style="margin-top:10px;">
                    <i class="fas fa-check-double"></i> 
                    <?= formatTsh($bills_stats['total_paid_amt']) ?> of <?= formatTsh($bills_stats['total_billed_amt']) ?>
                </p>
            </div>
        </div>

        <!-- ✅ V17: SEARCH CHINI YA CARDS -->
        <div class="search-section-wrapper">
            <div class="med-search-panel">
                <div class="search-label">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </div>
                <div class="search-box">
                    <i class="fas fa-search" style="color:white;margin-right:8px;"></i>
                    <input type="text" id="pageSearchInput" 
                           placeholder="Search patient, bill number, item..." autocomplete="off">
                </div>
            </div>
        </div>
        
        <?php if (!empty($bills_array)): ?>
            <?php foreach ($bills_array as $patient): 
                $pid = $patient['patient_id'];
                $age = calculateAge($patient['date_of_birth']);
                $has_partial = ($patient['total_partial'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient(<?= $pid ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $patient['total_partial'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-calendar-check"></i> <?= $patient['visit_count'] ?> visit(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-file-invoice"></i> <?= $patient['total_bills'] ?> bill(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-venus-mars"></i> <?= $age ?> yrs</span>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($patient['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $pid ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $pid ?>">
                        
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px;">
                            <div style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);border-radius:10px;padding:10px 14px;border-left:4px solid #059669;">
                                <div style="font-size:0.6rem;font-weight:700;color:#065F46;text-transform:uppercase;">Paid Bills</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#065F46;font-family:var(--font-mono);"><?= $patient['total_paid'] ?></div>
                            </div>
                            <div style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);border-radius:10px;padding:10px 14px;border-left:4px solid #D97706;">
                                <div style="font-size:0.6rem;font-weight:700;color:#78350F;text-transform:uppercase;">Pending Bills</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#78350F;font-family:var(--font-mono);"><?= $patient['total_pending'] ?></div>
                            </div>
                            <div style="background:linear-gradient(135deg,#EDE9FE,#DDD6FE);border-radius:10px;padding:10px 14px;border-left:4px solid #7C3AED;">
                                <div style="font-size:0.6rem;font-weight:700;color:#5B21B6;text-transform:uppercase;">Partial Bills</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#5B21B6;font-family:var(--font-mono);"><?= $patient['total_partial'] ?></div>
                            </div>
                            <div style="background:linear-gradient(135deg,#CFFAFE,#A5F3FC);border-radius:10px;padding:10px 14px;border-left:4px solid #0891B2;">
                                <div style="font-size:0.6rem;font-weight:700;color:#0E7490;text-transform:uppercase;">Premium</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#0E7490;font-family:var(--font-mono);"><?= formatTsh($patient['total_premium']) ?></div>
                            </div>
                        </div>
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $uid = $pid . '-' . $visit['visit_id'];
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-calendar-check"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($visit['visit_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-file-invoice"></i>
                                            Bills: <strong style="color:var(--primary);"><?= count($visit['bills']) ?></strong>
                                        </span>
                                        <div class="table-nav-group">
                                            <button type="button" class="table-nav-btn scroll-left" 
                                                    onclick="scrollVisitTable('<?= $uid ?>', -1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                                            <button type="button" class="table-nav-btn scroll-right" 
                                                    onclick="scrollVisitTable('<?= $uid ?>', 1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="visit-section-body" id="visit-body-<?= $uid ?>">
                                    
                                    <div class="visit-summary-box">
                                        <div class="visit-summary-item">
                                            <span class="label">Total Bill</span>
                                            <span class="value"><?= formatTsh($visit['sum_total']) ?></span>
                                        </div>
                                        <div class="visit-summary-item">
                                            <span class="label">Total Paid</span>
                                            <span class="value green"><?= formatTsh($visit['sum_paid']) ?></span>
                                        </div>
                                        <div class="visit-summary-item">
                                            <span class="label">Balance</span>
                                            <span class="value <?= $visit['sum_balance'] > 0 ? 'red' : 'green' ?>"><?= formatTsh($visit['sum_balance']) ?></span>
                                        </div>
                                        <div class="visit-summary-item">
                                            <span class="label">Discount</span>
                                            <span class="value cyan"><?= $visit['sum_discount'] > 0 ? '-' . formatTsh($visit['sum_discount']) : '—' ?></span>
                                        </div>
                                        <div class="visit-summary-item">
                                            <span class="label">Premium</span>
                                            <span class="value purple"><?= $visit['sum_premium'] > 0 ? '+' . formatTsh($visit['sum_premium']) : '—' ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php foreach ($visit['bills'] as $bill): 
                                        $bs = getStatusBadge($bill['status']);
                                        $items_by_type = [];
                                        foreach ($bill['items'] as $item) {
                                            $items_by_type[$item['item_type']][] = $item;
                                        }
                                    ?>
                                        <div class="bill-group">
                                            <div class="bill-group-header">
                                                <span style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                    <i class="fas fa-file-invoice-dollar" style="color:var(--primary);"></i>
                                                    <span class="bill-num"><?= htmlspecialchars($bill['bill_number']) ?></span>
                                                    <span>•</span>
                                                    <span><?= date('d M Y, H:i', strtotime($bill['created_at'])) ?></span>
                                                </span>
                                                <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                                    <span class="status-badge <?= $bs['class'] ?>"><?= $bs['icon'] ?> <?= $bs['label'] ?></span>
                                                    <span class="visit-mini-stat" style="background:rgba(11,94,215,0.15);color:var(--primary);">
                                                        <i class="fas fa-money-bill"></i> <strong><?= formatTsh($bill['total_amount']) ?></strong>
                                                    </span>
                                                    <span class="visit-mini-stat stat-paid">
                                                        <i class="fas fa-check"></i> Paid: <strong><?= formatTsh($bill['paid_amount'] ?? 0) ?></strong>
                                                    </span>
                                                    <?php if (($bill['balance'] ?? 0) > 0): ?>
                                                        <span class="visit-mini-stat stat-balance">
                                                            <i class="fas fa-exclamation-triangle"></i> Balance: <strong><?= formatTsh($bill['balance'] ?? 0) ?></strong>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="bill-group-body">
                                                <?php foreach ($items_by_type as $item_type => $items): 
                                                    $type_meta = [
                                                        'consultation' => ['icon' => 'fa-stethoscope', 'label' => 'Consultation'],
                                                        'lab_test' => ['icon' => 'fa-flask', 'label' => 'Lab Tests'],
                                                        'medication' => ['icon' => 'fa-pills', 'label' => 'Medications'],
                                                        'procedure' => ['icon' => 'fa-syringe', 'label' => 'Procedures'],
                                                        'equipment' => ['icon' => 'fa-tools', 'label' => 'Equipments'],
                                                    ];
                                                    $tm = $type_meta[$item_type] ?? ['icon' => 'fa-file', 'label' => ucfirst($item_type)];
                                                    
                                                    $type_total = 0;
                                                    foreach ($items as $it) {
                                                        $type_total += $it['total_price'] ?? 0;
                                                    }
                                                    
                                                    $cat_uid = $uid . '-b' . $bill['id'] . '-' . $item_type;
                                                ?>
                                                    <div class="item-type-section">
                                                        <div class="item-type-header">
                                                            <span class="title-group">
                                                                <i class="fas <?= $tm['icon'] ?>"></i> <?= $tm['label'] ?>
                                                                <span class="item-count"><?= count($items) ?></span>
                                                            </span>
                                                            <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                                                <span class="visit-mini-stat" style="background:rgba(11,94,215,0.15);color:var(--primary);">
                                                                    Total: <strong><?= formatTsh($type_total) ?></strong>
                                                                </span>
                                                                <div class="table-nav-group">
                                                                    <button type="button" class="table-nav-btn scroll-left" 
                                                                            onclick="scrollCategoryTable('<?= $cat_uid ?>', -1)">
                                                                        <i class="fas fa-chevron-left"></i>
                                                                    </button>
                                                                    <span class="table-nav-indicator" id="indicator-<?= $cat_uid ?>">0%</span>
                                                                    <button type="button" class="table-nav-btn scroll-right" 
                                                                            onclick="scrollCategoryTable('<?= $cat_uid ?>', 1)">
                                                                        <i class="fas fa-chevron-right"></i>
                                                                    </button>
                                                                </div>
                                                            </span>
                                                        </div>
                                                        <div class="table-scroll-wrapper" id="wrapper-<?= $cat_uid ?>">
                                                            <div class="table-scroll" id="table-<?= $cat_uid ?>">
                                                                <table class="data-table" style="min-width:1200px;">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Item</th>
                                                                            <th>Qty</th>
                                                                            <th>Unit Price</th>
                                                                            <th>Discount</th>
                                                                            <th>Total</th>
                                                                            <th style="text-align:center;min-width:280px;">Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($items as $it): 
                                                                            $view_url = '#';
                                                                            $edit_url = '#';
                                                                            
                                                                            switch ($item_type) {
                                                                                case 'consultation':
                                                                                    $view_url = 'view_consultation.php?id=' . ($it['reference_id'] ?? $bill['visit_id']) . '&bill_item_id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    $edit_url = 'edit_consultation.php?id=' . ($it['reference_id'] ?? $bill['visit_id']) . '&bill_item_id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    break;
                                                                                case 'lab_test':
                                                                                    $view_url = 'view_lab_test.php?id=' . ($it['reference_id'] ?? 0) . '&bill_item_id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    $edit_url = 'edit_lab_test.php?id=' . ($it['reference_id'] ?? 0) . '&bill_item_id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    break;
                                                                                case 'medication':
                                                                                    $view_url = 'view_medication.php?prescription_item_id=' . ($it['reference_id'] ?? 0) . '&bill_item_id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    $edit_url = 'edit_medication.php?prescription_item_id=' . ($it['reference_id'] ?? 0) . '&bill_item_id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    break;
                                                                                case 'procedure':
                                                                                case 'equipment':
                                                                                    $view_url = 'view_procedure.php?id=' . ($it['reference_id'] ?? 0) . '&bill_item_id=' . $it['id'] . '&type=' . $item_type . '&branch=' . $selected_branch_id;
                                                                                    $edit_url = 'edit_procedure.php?id=' . ($it['reference_id'] ?? 0) . '&bill_item_id=' . $it['id'] . '&type=' . $item_type . '&branch=' . $selected_branch_id;
                                                                                    break;
                                                                                default:
                                                                                    $view_url = 'view_bill_item.php?id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                                    $edit_url = 'edit_bill_item.php?id=' . $it['id'] . '&branch=' . $selected_branch_id;
                                                                            }
                                                                        ?>
                                                                            <tr>
                                                                                <td style="font-weight:700;color:var(--primary);">
                                                                                    <?= htmlspecialchars($it['item_name']) ?>
                                                                                </td>
                                                                                <td><?= $it['quantity'] ?></td>
                                                                                <td class="amount-cell"><?= formatTsh($it['unit_price']) ?></td>
                                                                                <td class="amount-cell <?= ($it['discount_amount'] ?? 0) > 0 ? 'red' : '' ?>">
                                                                                    <?= ($it['discount_amount'] ?? 0) > 0 ? '-' . formatTsh($it['discount_amount']) : '—' ?>
                                                                                </td>
                                                                                <td class="amount-cell"><?= formatTsh($it['total_price']) ?></td>
                                                                                <td style="text-align:center;">
                                                                                    <div class="action-buttons-group">
                                                                                        <a href="<?= htmlspecialchars($view_url) ?>" 
                                                                                           class="btn-action-sm view" 
                                                                                           target="_blank">
                                                                                            <i class="fas fa-eye"></i> View
                                                                                        </a>
                                                                                        
                                                                                        <a href="<?= htmlspecialchars($edit_url) ?>" 
                                                                                           class="btn-action-sm edit">
                                                                                            <i class="fas fa-edit"></i> Edit
                                                                                        </a>
                                                                                        
                                                                                        <form method="POST" class="delete-form-inline" 
                                                                                              onsubmit="return confirm('⚠️ Delete this <?= ucfirst(str_replace('_', ' ', $item_type)) ?>?\n\n<?= htmlspecialchars(addslashes($it['item_name'])) ?>\nQty: <?= (int)$it['quantity'] ?>\nAmount: TSh <?= number_format($it['total_price'], 0) ?>');">
                                                                                            <input type="hidden" name="action" value="delete_bill_item_all">
                                                                                            <input type="hidden" name="bill_item_id" value="<?= (int)$it['id'] ?>">
                                                                                            <input type="hidden" name="item_type" value="<?= htmlspecialchars($item_type) ?>">
                                                                                            <input type="hidden" name="redirect_tab" value="all_bills">
                                                                                            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                                                                                            <button type="submit" class="btn-action-sm delete">
                                                                                                <i class="fas fa-trash"></i> Delete
                                                                                            </button>
                                                                                        </form>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="patient-footer <?= $has_partial ? 'has-partial' : '' ?>">
                        <div class="footer-left">
                            <span class="footer-end-label">
                                <i class="fas fa-user-check"></i>
                                END OF <?= htmlspecialchars(strtoupper($patient['patient_name'])) ?>
                            </span>
                            <span class="footer-visit-count">
                                <i class="fas fa-notes-medical"></i>
                                <?= $patient['visit_count'] ?> Visit<?= $patient['visit_count'] != 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <div class="footer-right">
                            <span class="footer-stat">
                                <i class="fas fa-file-invoice" style="color:var(--primary);"></i>
                                Billed: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </span>
                            <span class="footer-stat success">
                                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                                Paid: <strong><?= formatTsh($patient['total_paid_amt']) ?></strong>
                            </span>
                            <?php if ($patient['total_balance'] > 0): ?>
                            <span class="footer-stat danger">
                                <i class="fas fa-exclamation-circle"></i>
                                Balance: <strong><?= formatTsh($patient['total_balance']) ?></strong>
                            </span>
                            <?php else: ?>
                            <span class="footer-stat success">
                                <i class="fas fa-check-double"></i>
                                Fully Paid
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No bills found</p>
                <p class="sub">Bills without patient ID are excluded from this view</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TAB 4: OTC BILLS -->
    <?php if ($active_tab === 'otc_bills'): ?>
        
        <div class="stats-grid-6">
            <div class="stat-card-custom card-blue-1">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <p class="stat-label">Total OTC Sales</p>
                </div>
                <p class="stat-number"><?= $otc_stats['total'] ?></p>
                <p class="stat-amount"><i class="fas fa-money-bill-wave"></i> <?= formatTsh($otc_stats['amount']) ?></p>
            </div>
            <div class="stat-card-custom card-green">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <p class="stat-label">Paid</p>
                </div>
                <p class="stat-number"><?= $otc_stats['paid'] ?></p>
                <p class="stat-amount"><i class="fas fa-check-double"></i> Completed</p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <p class="stat-label">Pending</p>
                </div>
                <p class="stat-number"><?= $otc_stats['pending'] ?></p>
                <p class="stat-amount"><i class="fas fa-hourglass-half"></i> Unpaid</p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <p class="stat-label">Partial</p>
                </div>
                <p class="stat-number"><?= $otc_stats['partial'] ?></p>
                <p class="stat-amount"><i class="fas fa-spinner"></i> Partially Paid</p>
            </div>
            <div class="stat-card-custom card-cyan">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <p class="stat-label">Items Sold</p>
                </div>
                <p class="stat-number"><?= $otc_stats['items_total'] ?></p>
                <p class="stat-amount"><i class="fas fa-capsules"></i> Total Items</p>
            </div>
            <div class="stat-card-custom card-pink">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <p class="stat-label">Paid %</p>
                </div>
                <p class="stat-number"><?= $otc_stats['total'] > 0 ? round(($otc_stats['paid'] / $otc_stats['total']) * 100, 1) : 0 ?>%</p>
                <p class="stat-amount"><i class="fas fa-chart-pie"></i> Completion Rate</p>
            </div>
        </div>

        <!-- ✅ V17: SEARCH CHINI YA CARDS -->
        <div class="search-section-wrapper">
            <div class="med-search-panel">
                <div class="search-label">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </div>
                <div class="search-box">
                    <i class="fas fa-search" style="color:white;margin-right:8px;"></i>
                    <input type="text" id="pageSearchInput" 
                           placeholder="Search customer, sale number..." autocomplete="off">
                </div>
            </div>
        </div>
        
        <div class="table-card" style="background:var(--bg-body); border:2px solid var(--cyan); border-radius:16px;">
            <div class="table-header" style="background:linear-gradient(135deg, #0891B2, #0E7490); padding:14px 20px; border-radius:14px 14px 0 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span style="color:white; font-size:0.88rem; font-weight:800; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-cash-register" style="color:#67E8F9;"></i> OTC Sales (Over-The-Counter)
                    </span>
                    <span style="color:rgba(255,255,255,0.95); font-size:0.7rem; font-weight:700; background:rgba(255,255,255,0.18); padding:5px 12px; border-radius:9999px;">
                        <i class="fas fa-list"></i> <?= count($otc_sales_list) ?> sales • Total: <?= formatTsh($otc_stats['amount']) ?>
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="otc-scroll-buttons">
                        <button type="button" class="otc-scroll-btn" onclick="scrollOtcContainer('left')"><i class="fas fa-chevron-left"></i></button>
                        <button type="button" class="otc-scroll-btn" onclick="scrollOtcContainer('right')"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
            
            <?php if (count($otc_sales_list) > 0): ?>
                <div id="otcCardsContainer" class="otc-cards-container">
                    <?php foreach ($otc_sales_list as $otc): 
                        $status = strtolower($otc['payment_status'] ?? 'pending');
                        $status_class = ($status === 'paid') ? 'success' : (($status === 'cancelled') ? 'danger' : 'warning');
                        $status_icon = ($status === 'paid') ? 'fa-check-circle' : (($status === 'cancelled') ? 'fa-times-circle' : 'fa-clock');
                        
                        $role = strtolower($otc['sold_by_role'] ?? 'user');
                        $name_parts = explode(' ', trim($otc['sold_by_name'] ?? 'N/A'));
                        $initials = count($name_parts) >= 2 ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) : strtoupper(substr($otc['sold_by_name'] ?? 'NA', 0, 2));
                        $items = $otc['items'] ?? [];
                        $item_count = count($items);
                    ?>
                    
                    <div class="otc-sale-card">
                        
                        <div class="otc-sale-header">
                            <div class="otc-header-left">
                                <span class="otc-sale-id-badge">
                                    <i class="fas fa-receipt"></i> <?= htmlspecialchars($otc['sale_number'] ?? 'N/A') ?>
                                </span>
                                <div class="otc-customer-info">
                                    <div class="otc-customer-name">
                                        <i class="fas fa-user-circle"></i>
                                        <?= htmlspecialchars($otc['customer_name'] ?? 'Walk-in Customer') ?>
                                    </div>
                                    <?php if (!empty($otc['customer_phone'])): ?>
                                        <div class="otc-customer-phone">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($otc['customer_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="otc-header-middle">
                                <div class="otc-stat-chip discount">
                                    <span class="stat-chip-label"><i class="fas fa-tag"></i> Discount</span>
                                    <span class="stat-chip-value">
                                        <?php if ((float)($otc['discount_amount'] ?? 0) > 0): ?>
                                            -TSh <?= number_format((float)$otc['discount_amount'], 0) ?>
                                        <?php else: ?>
                                            TSh 0
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="otc-stat-chip premium">
                                    <span class="stat-chip-label"><i class="fas fa-star"></i> Premium</span>
                                    <span class="stat-chip-value">
                                        <?php if ((float)($otc['premium_amount'] ?? 0) > 0): ?>
                                            +TSh <?= number_format((float)$otc['premium_amount'], 0) ?>
                                        <?php else: ?>
                                            TSh 0
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="otc-stat-chip grand-total">
                                    <span class="stat-chip-label"><i class="fas fa-calculator"></i> Grand Total</span>
                                    <span class="stat-chip-value">TSh <?= number_format((float)$otc['total_amount'], 0) ?></span>
                                </div>
                            </div>
                            
                            <div class="otc-header-right">
                                <a href="view_otc.php?id=<?= (int)$otc['sale_id'] ?>&branch=<?= $selected_branch_id ?>" 
                                   class="otc-action-btn view" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit_otc.php?id=<?= (int)$otc['sale_id'] ?>&branch=<?= $selected_branch_id ?>" 
                                   class="otc-action-btn edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button type="button" class="otc-action-btn delete" 
                                        onclick="confirmDeleteOtcSale(<?= (int)$otc['sale_id'] ?>, '<?= htmlspecialchars(addslashes($otc['sale_number'] ?? 'N/A')) ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <div class="otc-scroll-buttons">
                                    <button type="button" class="otc-scroll-btn" onclick="scrollOtcCard(this, 'left')"><i class="fas fa-chevron-left"></i></button>
                                    <button type="button" class="otc-scroll-btn" onclick="scrollOtcCard(this, 'right')"><i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        <div style="overflow-x:auto; scroll-behavior:smooth;" class="otc-items-wrapper">
                            <table class="otc-items-table" style="min-width:1100px;">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>Item Name</th>
                                        <th style="text-align:center;">Qty</th>
                                        <th style="text-align:right;">Unit Price</th>
                                        <th style="text-align:right;">Total Price</th>
                                        <th>Sold By</th>
                                        <th>Payment Method</th>
                                        <th>Date & Time</th>
                                        <th style="text-align:center; min-width:180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($item_count > 0): ?>
                                        <?php $item_num = 1; foreach ($items as $item): ?>
                                            <tr>
                                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_num++ ?></td>
                                                <td>
                                                    <div class="otc-item-name-cell">
                                                        <span class="item-icon"><i class="fas fa-capsules"></i></span>
                                                        <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                                    </div>
                                                </td>
                                                <td style="text-align:center;">
                                                    <span class="otc-qty-badge"><?= (int)($item['quantity'] ?? 0) ?></span>
                                                </td>
                                                <td class="amount-cell" style="color:#0891B2;font-size:0.78rem; text-align:right;">
                                                    TSh <?= number_format((float)($item['unit_price'] ?? 0), 0) ?>
                                                </td>
                                                <td class="amount-cell" style="font-size:0.78rem;font-weight:900; text-align:right;">
                                                    TSh <?= number_format((float)($item['total_price'] ?? 0), 0) ?>
                                                </td>
                                                <td>
                                                    <div class="received-by">
                                                        <div class="received-by-avatar"><?= htmlspecialchars($initials) ?></div>
                                                        <div class="received-by-info">
                                                            <span class="received-by-name"><?= htmlspecialchars($otc['sold_by_name'] ?? 'N/A') ?></span>
                                                            <span class="received-by-role"><span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $otc['payment_method'] ?? 'Cash'))) ?></span>
                                                </td>
                                                <td>
                                                    <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                                    <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                                </td>
                                                <td style="text-align:center;">
                                                    <div class="action-buttons-group">
                                                        <form method="POST" class="delete-form-inline" 
                                                              onsubmit="return confirm('⚠️ Delete this OTC item?\n\n<?= htmlspecialchars(addslashes($item['item_name'])) ?>\nQty: <?= (int)$item['quantity'] ?>\nAmount: TSh <?= number_format($item['total_price'], 0) ?>');">
                                                            <input type="hidden" name="action" value="delete_otc_item">
                                                            <input type="hidden" name="otc_item_id" value="<?= (int)$item['id'] ?>">
                                                            <input type="hidden" name="sale_id" value="<?= (int)$otc['sale_id'] ?>">
                                                            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                                                            <button type="submit" class="btn-action-sm delete">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center;color:var(--text-secondary);font-style:italic;padding:20px;">No items recorded for this sale</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="otc-sale-footer">
                            <div class="otc-footer-info">
                                <span class="otc-footer-stat">
                                    <i class="fas fa-list-ul" style="color:#0891B2;"></i>
                                    Items: <strong><?= $item_count ?></strong>
                                </span>
                                <span class="otc-footer-stat">
                                    <i class="fas fa-store-alt" style="color:var(--primary);"></i>
                                    Branch: <strong><?= htmlspecialchars($otc['branch_name'] ?? 'N/A') ?></strong>
                                </span>
                                <span class="otc-footer-stat">
                                    <i class="fas fa-credit-card" style="color:var(--primary);"></i>
                                    Subtotal: <strong>TSh <?= number_format((float)($otc['subtotal'] ?? 0), 0) ?></strong>
                                </span>
                            </div>
                            <div class="otc-footer-info">
                                <span class="status-badge <?= $status_class ?>">
                                    <i class="fas <?= $status_icon ?>"></i> <?= strtoupper($status) ?>
                                </span>
                            </div>
                        </div>
                        
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-cash-register"></i>
                    <p>No OTC sales found</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Other Services V17
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<!-- DELETE OTC SALE MODAL -->
<div class="modal-overlay" id="deleteOtcModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="modal-title">Delete OTC Sale?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong id="deleteOtcRecord">-</strong><br>
            <span class="modal-warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone!</span>
        </p>
        <form method="POST" id="deleteOtcForm">
            <input type="hidden" name="action" value="delete_otc_sale">
            <input type="hidden" name="sale_id" id="deleteOtcSaleId" value="">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <div class="modal-actions">
                <button type="button" onclick="closeDeleteOtcModal()" class="modal-btn cancel">
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
function togglePatient(patientId) {
    var body = document.getElementById('body-' + patientId);
    var chevron = document.getElementById('chevron-' + patientId);
    if (body) body.classList.toggle('open');
    if (chevron) chevron.classList.toggle('rotated');
    
    setTimeout(function() {
        if (body) {
            body.querySelectorAll('.table-scroll').forEach(function(tbl) {
                if (tbl.id) updateTableNav(tbl.id);
            });
            body.querySelectorAll('[id^="visit-body-"]').forEach(function(vb) {
                var uid = vb.id.replace('visit-body-', '');
                updateVisitIndicator(uid);
            });
            initCategoryTables();
        }
    }, 450);
}

function scrollVisitTable(uid, direction) {
    var body = document.getElementById('visit-body-' + uid);
    if (!body) return;
    
    var scrollTarget = body.querySelector('.table-scroll');
    if (!scrollTarget) scrollTarget = body.querySelector('.bill-group-body');
    if (!scrollTarget) scrollTarget = body;
    
    var scrollAmount = 400;
    var currentScroll = scrollTarget.scrollLeft || 0;
    var maxScroll = (scrollTarget.scrollWidth - scrollTarget.clientWidth) || 0;
    
    var newScroll = currentScroll + (direction * scrollAmount);
    if (newScroll < 0) newScroll = 0;
    if (newScroll > maxScroll) newScroll = maxScroll;
    
    if (scrollTarget.scrollTo) {
        scrollTarget.scrollTo({ left: newScroll, behavior: 'smooth' });
    }
    
    setTimeout(function() { updateVisitIndicator(uid); }, 350);
}

function updateVisitIndicator(uid) {
    var body = document.getElementById('visit-body-' + uid);
    var indicator = document.getElementById('indicator-' + uid);
    if (!body || !indicator) return;
    
    var scrollTarget = body.querySelector('.table-scroll');
    if (!scrollTarget) scrollTarget = body.querySelector('.bill-group-body');
    if (!scrollTarget) scrollTarget = body;
    
    var currentScroll = scrollTarget.scrollLeft || 0;
    var maxScroll = (scrollTarget.scrollWidth - scrollTarget.clientWidth) || 0;
    
    var percent = 0;
    if (maxScroll > 0) percent = Math.round((currentScroll / maxScroll) * 100);
    indicator.textContent = percent + '%';
    
    var visitSection = body.closest('.visit-section');
    if (visitSection) {
        var leftBtn = visitSection.querySelector('.scroll-left');
        var rightBtn = visitSection.querySelector('.scroll-right');
        if (leftBtn) leftBtn.disabled = (currentScroll <= 1);
        if (rightBtn) rightBtn.disabled = (currentScroll >= maxScroll - 1);
    }
}

function scrollCategoryTable(catUid, direction) {
    var table = document.getElementById('table-' + catUid);
    if (!table) return;
    
    var scrollAmount = 400;
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var newScroll = currentScroll + (direction * scrollAmount);
    if (newScroll < 0) newScroll = 0;
    if (newScroll > maxScroll) newScroll = maxScroll;
    
    table.scrollTo({ left: newScroll, behavior: 'smooth' });
    setTimeout(function() { updateCategoryIndicator(catUid); }, 350);
}

function updateCategoryIndicator(catUid) {
    var table = document.getElementById('table-' + catUid);
    var indicator = document.getElementById('indicator-' + catUid);
    var wrapper = document.getElementById('wrapper-' + catUid);
    
    if (!table || !indicator) return;
    
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var percent = 0;
    if (maxScroll > 0) percent = Math.round((currentScroll / maxScroll) * 100);
    indicator.textContent = percent + '%';
    
    if (wrapper) {
        var parentHeader = wrapper.closest('.item-type-section')?.querySelector('.item-type-header');
        if (parentHeader) {
            var leftBtn = parentHeader.querySelector('.scroll-left');
            var rightBtn = parentHeader.querySelector('.scroll-right');
            if (leftBtn) leftBtn.disabled = (currentScroll <= 1);
            if (rightBtn) rightBtn.disabled = (currentScroll >= maxScroll - 1);
        }
    }
}

function initCategoryTables() {
    document.querySelectorAll('.table-scroll[id^="table-"]').forEach(function(tbl) {
        if (tbl.closest('.item-type-section')) {
            var catUid = tbl.id.replace('table-', '');
            updateCategoryIndicator(catUid);
        }
    });
}

function updateTableNav(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var uniqueId = tableId.replace('table-', '');
    var indicator = document.getElementById('indicator-' + uniqueId);
    
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var percent = 0;
    if (maxScroll > 0) percent = Math.round((currentScroll / maxScroll) * 100);
    if (indicator) indicator.textContent = percent + '%';
}

document.addEventListener('DOMContentLoaded', function() {
    var firstBody = document.querySelector('.patient-body');
    var firstChevron = document.querySelector('.chevron');
    if (firstBody) {
        setTimeout(function() {
            firstBody.classList.add('open');
            if (firstChevron) firstChevron.classList.add('rotated');
            firstBody.querySelectorAll('.table-scroll').forEach(function(tbl) {
                if (tbl.id) updateTableNav(tbl.id);
            });
            firstBody.querySelectorAll('[id^="visit-body-"]').forEach(function(vb) {
                var uid = vb.id.replace('visit-body-', '');
                updateVisitIndicator(uid);
            });
            initCategoryTables();
        }, 300);
    }
    
    document.querySelectorAll('.table-scroll').forEach(function(tbl) {
        if (!tbl.id) return;
        var isCategoryTable = tbl.closest('.item-type-section') !== null;
        
        tbl.addEventListener('scroll', function() { 
            if (isCategoryTable) {
                var catUid = tbl.id.replace('table-', '');
                updateCategoryIndicator(catUid);
            } else {
                updateTableNav(tbl.id);
                var visitBody = tbl.closest('[id^="visit-body-"]');
                if (visitBody) {
                    var uid = visitBody.id.replace('visit-body-', '');
                    updateVisitIndicator(uid);
                }
            }
        });
        
        if (isCategoryTable) {
            var catUid = tbl.id.replace('table-', '');
            updateCategoryIndicator(catUid);
        } else {
            updateTableNav(tbl.id);
        }
    });
    
    document.querySelectorAll('[id^="indicator-"]').forEach(function(el) {
        var uid = el.id.replace('indicator-', '');
        if (document.getElementById('wrapper-' + uid)) {
            updateCategoryIndicator(uid);
        } else {
            updateVisitIndicator(uid);
        }
    });
});

function scrollOtcContainer(direction) {
    var container = document.getElementById('otcCardsContainer');
    if (!container) return;
    var amount = 500;
    container.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function scrollOtcCard(btn, direction) {
    var card = btn.closest('.otc-sale-card');
    if (!card) return;
    var wrapper = card.querySelector('.otc-items-wrapper');
    if (!wrapper) return;
    var amount = 400;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function confirmDeleteOtcSale(id, saleNumber) {
    document.getElementById('deleteOtcSaleId').value = id;
    document.getElementById('deleteOtcRecord').textContent = saleNumber;
    document.getElementById('deleteOtcModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteOtcModal() {
    document.getElementById('deleteOtcModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteOtcModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteOtcModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteOtcModal();
});

(function() {
    var input = document.getElementById('pageSearchInput');
    if (!input) return;
    
    input.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        var cards = document.querySelectorAll('.patient-card');
        
        cards.forEach(function(card) {
            var data = card.getAttribute('data-patient-search') || '';
            var rows = card.querySelectorAll('tbody tr[data-search]');
            var hasMatch = false;
            
            if (q === '') hasMatch = true;
            else if (data.includes(q)) hasMatch = true;
            
            rows.forEach(function(row) {
                var rd = row.getAttribute('data-search') || '';
                if (q === '' || rd.includes(q) || data.includes(q)) {
                    hasMatch = true;
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (hasMatch) card.classList.remove('filtered-out');
            else card.classList.add('filtered-out');
        });
    });
})();

setInterval(function() {
    var now = new Date();
    var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ft = document.getElementById('footerTimestamp');
    if (ft) ft.textContent = 'Last updated: ' + t;
}, 1000);

console.log('%c🏥 Braick - Other Services V17', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ All Bills: inaonyesha bills zenye patient_id TU', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ Search bar imehamishwa CHINI ya summary cards', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ <> ARROW BUTTONS kwenye KILA visit table header', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ <> ARROW BUTTONS kwenye KILA ITEM CATEGORY table', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ ALL ITEMS: View + Edit + Delete', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ Auto stock restore + recalculate bill', 'font-size:12px;color:#34D399;font-weight:bold;');
</script>

</body>
</html>