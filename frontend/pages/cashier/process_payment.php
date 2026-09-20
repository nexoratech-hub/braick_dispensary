<?php
// ================================================================
// FILE: frontend/pages/cashier/process_payment.php
// CASHIER - PROCESS PAYMENT - v9.0 (PAYMENT CARD FIXED)
// ================================================================
// ✅ NEW: updateVisitPaymentStatus() - Inaupdate visit payment_status
// ✅ NEW: Auto-repair visits kila page load
// ✅ FIXED: Payment card width = 1100px (inalingana na table)
// ✅ FIXED: Payment card haigusi sidebar
// ✅ FIXED: Payment card height imeongezwa
// ✅ FIXED: Full payment → visit payment_status = 'paid'
// ✅ FIXED: Partial payment → visit payment_status = 'partial'
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');
$is_cashier = ($user_role === 'cashier');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// ✅ HELPER: UPDATE VISIT PAYMENT STATUS
// ================================================================
function updateVisitPaymentStatus($db, $visit_id, $branch_id) {
    if (empty($visit_id) || $visit_id <= 0) return false;
    
    try {
        $stmt = $db->prepare("
            SELECT id, total_amount, paid_amount, balance, status
            FROM bills
            WHERE visit_id = ? AND branch_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$visit_id, $branch_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($bills)) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET payment_status = 'pending', visit_total = 0, updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$visit_id, $branch_id]);
            return true;
        }
        
        $visit_total = 0;
        $visit_paid = 0;
        $visit_balance = 0;
        $all_paid = true;
        $any_paid = false;
        
        foreach ($bills as $bill) {
            $visit_total += (float)$bill['total_amount'];
            $visit_paid += (float)$bill['paid_amount'];
            $visit_balance += (float)$bill['balance'];
            
            if ($bill['status'] === 'paid') {
                $any_paid = true;
            } elseif ($bill['status'] === 'partial') {
                $any_paid = true;
                $all_paid = false;
            } else {
                $all_paid = false;
            }
        }
        
        $visit_payment_status = 'pending';
        
        if ($visit_total <= 0) {
            $visit_payment_status = 'paid';
        } elseif ($all_paid && $visit_balance <= 0.01) {
            $visit_payment_status = 'paid';
        } elseif ($any_paid && $visit_balance > 0) {
            $visit_payment_status = 'partial';
        } else {
            $visit_payment_status = 'pending';
        }
        
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(total_discount), 0) as total_discount,
                COALESCE(SUM(premium_amount), 0) as total_premium
            FROM bills
            WHERE visit_id = ? AND branch_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$visit_id, $branch_id]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $visit_discount = (float)($totals['total_discount'] ?? 0);
        
        $stmt = $db->prepare("
            UPDATE visits 
            SET 
                payment_status = ?,
                visit_total = ?,
                total_discount = ?,
                updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([
            $visit_payment_status,
            $visit_total,
            $visit_discount,
            $visit_id,
            $branch_id
        ]);
        
        error_log("✅ VISIT #$visit_id UPDATED: payment_status=$visit_payment_status");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ updateVisitPaymentStatus error: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// ✅ HELPER: UPDATE VISIT COMPLETION STATUS
// ================================================================
function updateVisitCompletionStatus($db, $visit_id, $branch_id) {
    if (empty($visit_id) || $visit_id <= 0) return false;
    
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bills,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bills
            FROM bills
            WHERE visit_id = ? AND branch_id = ?
        ");
        $stmt->execute([$visit_id, $branch_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = (int)($result['total_bills'] ?? 0);
        $paid = (int)($result['paid_bills'] ?? 0);
        $cancelled = (int)($result['cancelled_bills'] ?? 0);
        
        $active_bills = $total - $cancelled;
        
        if ($active_bills > 0 && $paid == $active_bills) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET 
                    is_completed = 1,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ? AND is_completed = 0
            ");
            $stmt->execute([$visit_id, $branch_id]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("updateVisitCompletionStatus error: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// HELPER: CHECK IF BILL IS LOCKED
// ================================================================
function isBillLocked($db, $bill_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as pending_count
            FROM bill_items bi
            LEFT JOIN prescriptions pr ON bi.reference_id = pr.id AND bi.reference_type = 'prescription'
            WHERE bi.bill_id = ? 
            AND bi.item_type = 'medication' 
            AND bi.reference_type = 'prescription'
            AND bi.status != 'cancelled'
            AND (pr.status IS NULL OR pr.status NOT IN ('confirmed', 'dispensed'))
        ");
        $stmt->execute([$bill_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($result['pending_count'] ?? 0)) > 0;
    } catch (Exception $e) {
        return true;
    }
}

// ================================================================
// HELPER: GET PENDING PRESCRIPTION COUNT
// ================================================================
function getPendingPrescriptionCount($db, $bill_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as pending_count
            FROM bill_items bi
            LEFT JOIN prescriptions pr ON bi.reference_id = pr.id AND bi.reference_type = 'prescription'
            WHERE bi.bill_id = ? 
            AND bi.item_type = 'medication' 
            AND bi.reference_type = 'prescription'
            AND bi.status != 'cancelled'
            AND (pr.status IS NULL OR pr.status NOT IN ('confirmed', 'dispensed'))
        ");
        $stmt->execute([$bill_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['pending_count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// ================================================================
// HELPER: RECALCULATE BILL TOTALS
// ================================================================
function recalculateBillTotals($db, $bill_id, $branch_id) {
    $stmt = $db->prepare("
        SELECT 
            subtotal, discount_amount, cashier_discount, pharmacy_discount,
            premium_amount, total_discount, total_amount, paid_amount, status
        FROM bills 
        WHERE id = ? AND branch_id = ?
    ");
    $stmt->execute([$bill_id, $branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill) return null;
    
    $subtotal = (float)$bill['subtotal'];
    $pharmacy_discount = (float)$bill['discount_amount'];
    if (isset($bill['pharmacy_discount']) && (float)$bill['pharmacy_discount'] > 0) {
        $pharmacy_discount = (float)$bill['pharmacy_discount'];
    }
    $cashier_discount = (float)$bill['cashier_discount'];
    $premium_amount = (float)$bill['premium_amount'];
    $paid_amount = (float)$bill['paid_amount'];
    
    $total_discount = $pharmacy_discount + $cashier_discount;
    if ($total_discount > $subtotal) $total_discount = $subtotal;
    
    $total_amount = $subtotal + $premium_amount - $total_discount;
    if ($total_amount < 0) $total_amount = 0;
    
    $balance = $total_amount - $paid_amount;
    if ($balance < 0) {
        $balance = 0;
        if ($paid_amount > $total_amount) $paid_amount = $total_amount;
    }
    
    if ($total_amount <= 0) {
        $status = 'paid';
    } elseif ($balance <= 0.01) {
        $status = 'paid';
    } elseif ($paid_amount > 0 && $balance > 0) {
        $status = 'partial';
    } else {
        $status = 'pending';
    }
    
    $stmt = $db->prepare("
        UPDATE bills 
        SET total_discount = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND branch_id = ?
    ");
    $stmt->execute([$total_discount, $total_amount, $paid_amount, $balance, $status, $bill_id, $branch_id]);
    
    return [
        'subtotal' => $subtotal,
        'pharmacy_discount' => $pharmacy_discount,
        'cashier_discount' => $cashier_discount,
        'total_discount' => $total_discount,
        'premium_amount' => $premium_amount,
        'total_amount' => $total_amount,
        'paid_amount' => $paid_amount,
        'balance' => $balance,
        'status' => $status
    ];
}

$selected_bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // AJAX: GET UPDATED TOTALS
    // ================================================================
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        
        $totals = [
            'subtotal' => 0, 'total_amount' => 0, 'total_paid' => 0,
            'total_balance' => 0, 'total_discount' => 0,
            'total_pharmacy_discount' => 0, 'total_cashier_discount' => 0,
            'total_premium' => 0
        ];
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    COALESCE(SUM(subtotal), 0) as subtotal,
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as total_paid,
                    COALESCE(SUM(balance), 0) as total_balance,
                    COALESCE(SUM(total_discount), 0) as total_discount,
                    COALESCE(SUM(discount_amount), 0) as total_pharmacy_discount,
                    COALESCE(SUM(cashier_discount), 0) as total_cashier_discount,
                    COALESCE(SUM(premium_amount), 0) as total_premium
                FROM bills 
                WHERE branch_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$user_branch_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $totals['subtotal'] = (float)$result['subtotal'];
                $totals['total_amount'] = (float)$result['total_amount'];
                $totals['total_paid'] = (float)$result['total_paid'];
                $totals['total_balance'] = (float)$result['total_balance'];
                $totals['total_discount'] = (float)$result['total_discount'];
                $totals['total_pharmacy_discount'] = (float)$result['total_pharmacy_discount'];
                $totals['total_cashier_discount'] = (float)$result['total_cashier_discount'];
                $totals['total_premium'] = (float)$result['total_premium'];
            }
            
            echo json_encode(['success' => true, 'totals' => $totals]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ================================================================
    // HANDLE PAYMENT PROCESSING
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        $cashier_discount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $cashier_premium = isset($_POST['premium_amount']) ? floatval($_POST['premium_amount']) : 0;
        $premium_note = isset($_POST['premium_note']) ? trim($_POST['premium_note']) : '';
        $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
        
        if ($action === 'complete_payment' || $action === 'partial_payment') {
            if (empty($item_ids) || !is_array($item_ids)) {
                echo json_encode(['success' => false, 'message' => 'No items selected for payment']);
                exit;
            }
            
            if ($action === 'partial_payment' && $partial_amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid partial amount']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                $stmt = $db->prepare("
                    SELECT 
                        bi.*, 
                        b.id as bill_id,
                        b.bill_number, 
                        b.patient_id, 
                        b.branch_id,
                        b.visit_id,
                        b.balance as bill_balance,
                        b.subtotal as bill_subtotal,
                        b.total_amount as bill_total,
                        b.discount_amount as pharmacy_discount,
                        b.cashier_discount as existing_cashier_discount,
                        b.total_discount as existing_total_discount,
                        b.premium_amount as bill_premium,
                        b.premium_note as bill_premium_note,
                        b.status as bill_status,
                        b.paid_amount as bill_paid,
                        (SELECT status FROM prescriptions WHERE id = bi.reference_id AND bi.reference_type = 'prescription') as prescription_status
                    FROM bill_items bi
                    JOIN bills b ON bi.bill_id = b.id
                    WHERE bi.id IN ($placeholders) AND bi.status != 'paid' AND bi.status != 'cancelled'
                ");
                $stmt->execute($item_ids);
                $selected_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($selected_items)) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Selected items not found or already paid']);
                    exit;
                }
                
                // CHECK LOCKED
                $locked_bills = [];
                foreach ($selected_items as $item) {
                    if ($item['item_type'] === 'medication' && $item['reference_type'] === 'prescription') {
                        $pres_status = $item['prescription_status'] ?? 'pending';
                        if ($pres_status !== 'confirmed' && $pres_status !== 'dispensed') {
                            if (!in_array($item['bill_id'], $locked_bills)) {
                                $locked_bills[] = $item['bill_id'];
                            }
                        }
                    }
                }
                
                foreach ($selected_items as $item) {
                    if (!in_array($item['bill_id'], $locked_bills)) {
                        $pending_count = getPendingPrescriptionCount($db, $item['bill_id']);
                        if ($pending_count > 0) {
                            $locked_bills[] = $item['bill_id'];
                        }
                    }
                }
                
                if (!empty($locked_bills)) {
                    $db->rollBack();
                    
                    $bill_numbers = [];
                    foreach ($locked_bills as $locked_bill_id) {
                        $stmt_bn = $db->prepare("SELECT bill_number FROM bills WHERE id = ?");
                        $stmt_bn->execute([$locked_bill_id]);
                        $bn = $stmt_bn->fetchColumn();
                        if ($bn) $bill_numbers[] = $bn;
                    }
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => "🔒 HAIWEZI KULIPWA!\n\nBill(s) zina prescriptions ambazo hazijaconfirm:\n• " . implode("\n• ", $bill_numbers),
                        'locked_bills' => $bill_numbers
                    ]);
                    exit;
                }
                
                $bill_map = [];
                $total_original_amount = 0;
                $affected_visit_ids = [];
                
                foreach ($selected_items as $item) {
                    $bill_id = $item['bill_id'];
                    $visit_id = $item['visit_id'] ?? 0;
                    
                    if ($visit_id > 0 && !in_array($visit_id, $affected_visit_ids)) {
                        $affected_visit_ids[] = $visit_id;
                    }
                    
                    if (!isset($bill_map[$bill_id])) {
                        $stmt_bill = $db->prepare("
                            SELECT 
                                paid_amount, balance, subtotal, total_amount, 
                                total_discount, discount_amount, pharmacy_discount,
                                cashier_discount, premium_amount, premium_note
                            FROM bills WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_bill->execute([$bill_id, $user_branch_id]);
                        $current_bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);
                        
                        $pharmacy_discount_val = (float)($current_bill['pharmacy_discount'] ?? 0);
                        if ($pharmacy_discount_val == 0) {
                            $pharmacy_discount_val = (float)($current_bill['discount_amount'] ?? 0);
                        }
                        
                        $bill_map[$bill_id] = [
                            'bill_id' => $bill_id,
                            'bill_number' => $item['bill_number'],
                            'patient_id' => $item['patient_id'],
                            'visit_id' => $visit_id,
                            'items' => [],
                            'items_total' => 0,
                            'bill_paid' => (float)($current_bill['paid_amount'] ?? 0),
                            'bill_subtotal' => (float)($current_bill['subtotal'] ?? 0),
                            'bill_total' => (float)($current_bill['total_amount'] ?? 0),
                            'pharmacy_discount' => $pharmacy_discount_val,
                            'existing_cashier_discount' => (float)($current_bill['cashier_discount'] ?? 0),
                            'existing_total_discount' => (float)($current_bill['total_discount'] ?? 0),
                            'bill_premium' => (float)($current_bill['premium_amount'] ?? 0),
                            'bill_premium_note' => $current_bill['premium_note'] ?? '',
                            'bill_status' => $item['bill_status'] ?? 'pending'
                        ];
                    }
                    $bill_map[$bill_id]['items'][] = $item;
                    $bill_map[$bill_id]['items_total'] += (float)$item['total_price'];
                    $total_original_amount += (float)$item['total_price'];
                }
                
                $success_count = 0;
                $receipt_numbers = [];
                $total_amount_paid = 0;
                $total_discount_applied = 0;
                $total_pharmacy_discount = 0;
                $total_cashier_discount = 0;
                $total_premium_charged = 0;
                $processed_bill_ids = [];
                $bill_recalc_results = [];
                
                foreach ($bill_map as $bill_id => $bill_data) {
                    $processed_bill_ids[] = $bill_id;
                    
                    $pharmacy_discount = $bill_data['pharmacy_discount'];
                    $existing_cashier_discount = $bill_data['existing_cashier_discount'];
                    $existing_premium = $bill_data['bill_premium'];
                    
                    $bill_portion = ($total_original_amount > 0) 
                        ? ($bill_data['items_total'] / $total_original_amount) 
                        : 1;
                    
                    $bill_cashier_discount = round($cashier_discount * $bill_portion, 2);
                    $new_cashier_discount = $existing_cashier_discount + $bill_cashier_discount;
                    
                    $bill_premium_new = round($cashier_premium * $bill_portion, 2);
                    $new_premium = $existing_premium + $bill_premium_new;
                    
                    $new_total_discount = $pharmacy_discount + $new_cashier_discount;
                    if ($new_total_discount > $bill_data['bill_subtotal']) {
                        $new_total_discount = $bill_data['bill_subtotal'];
                        $new_cashier_discount = $new_total_discount - $pharmacy_discount;
                        if ($new_cashier_discount < 0) $new_cashier_discount = 0;
                    }
                    
                    $new_total_amount = $bill_data['bill_subtotal'] + $new_premium - $new_total_discount;
                    if ($new_total_amount < 0) $new_total_amount = 0;
                    
                    if ($action === 'partial_payment') {
                        $bill_payment = round($partial_amount * $bill_portion, 2);
                        if ($bill_payment > $new_total_amount) {
                            $bill_payment = $new_total_amount;
                        }
                    } else {
                        $bill_payment = $new_total_amount - $bill_data['bill_paid'];
                        if ($bill_payment < 0) $bill_payment = 0;
                    }
                    
                    $new_paid_amount = $bill_data['bill_paid'] + $bill_payment;
                    
                    if ($action === 'complete_payment' && $bill_payment > 0) {
                        $new_paid_amount = $new_total_amount;
                    }
                    
                    $new_balance = $new_total_amount - $new_paid_amount;
                    if ($new_balance < 0) {
                        $new_balance = 0;
                        if ($new_paid_amount > $new_total_amount) {
                            $new_paid_amount = $new_total_amount;
                        }
                    }
                    
                    if ($action === 'complete_payment') {
                        $new_balance = 0;
                        $new_paid_amount = $new_total_amount;
                    }
                    
                    if ($new_total_amount <= 0) {
                        $new_status = 'paid';
                    } elseif ($new_balance <= 0.01) {
                        $new_status = 'paid';
                    } elseif ($new_paid_amount > 0 && $new_balance > 0) {
                        $new_status = 'partial';
                    } else {
                        $new_status = 'pending';
                    }
                    
                    $final_premium_note = '';
                    if ($new_premium > 0) {
                        if (!empty($premium_note)) {
                            $final_premium_note = $premium_note;
                        } elseif (!empty($bill_data['bill_premium_note'])) {
                            $final_premium_note = $bill_data['bill_premium_note'];
                        } else {
                            $final_premium_note = 'Premium Charge';
                        }
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET 
                            paid_amount = ?,
                            balance = ?,
                            total_amount = ?,
                            cashier_discount = ?,
                            total_discount = ?,
                            discount_amount = ?,
                            pharmacy_discount = ?,
                            premium_amount = ?,
                            premium_note = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([
                        $new_paid_amount,
                        $new_balance,
                        $new_total_amount,
                        $new_cashier_discount,
                        $new_total_discount,
                        $pharmacy_discount,
                        $pharmacy_discount,
                        $new_premium,
                        $final_premium_note,
                        $new_status,
                        $bill_id,
                        $user_branch_id
                    ]);
                    
                    $item_ids_for_bill = array_column($bill_data['items'], 'id');
                    if (!empty($item_ids_for_bill)) {
                        $placeholders2 = implode(',', array_fill(0, count($item_ids_for_bill), '?'));
                        $stmt = $db->prepare("
                            UPDATE bill_items 
                            SET status = 'paid', updated_at = NOW()
                            WHERE id IN ($placeholders2)
                        ");
                        $stmt->execute($item_ids_for_bill);
                    }
                    
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $notes = 'Payment | Pharm Disc: ' . $currency . ' ' . number_format($pharmacy_discount, 0) . 
                             ' | Cashier Disc: ' . $currency . ' ' . number_format($new_cashier_discount, 0);
                    if ($new_premium > 0) {
                        $notes .= ' | Premium: ' . $currency . ' ' . number_format($new_premium, 0);
                        if (!empty($final_premium_note)) {
                            $notes .= ' (' . $final_premium_note . ')';
                        }
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO payments (
                            receipt_number, bill_id, patient_id, amount, 
                            payment_method, received_by, branch_id, received_at, notes
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $receipt_number,
                        $bill_id,
                        $bill_data['patient_id'],
                        $bill_payment,
                        $payment_method,
                        $user_id,
                        $user_branch_id,
                        $notes
                    ]);
                    
                    $recalc = recalculateBillTotals($db, $bill_id, $user_branch_id);
                    if ($recalc) {
                        $bill_recalc_results[$bill_id] = $recalc;
                    }
                    
                    $total_amount_paid += $bill_payment;
                    $total_discount_applied += $new_total_discount;
                    $total_pharmacy_discount += $pharmacy_discount;
                    $total_cashier_discount += $new_cashier_discount;
                    $total_premium_charged += $new_premium;
                    $receipt_numbers[] = $receipt_number;
                    $success_count++;
                }
                
                // ✅ UPDATE VISITS
                $updated_visits = [];
                foreach ($affected_visit_ids as $visit_id) {
                    if ($visit_id > 0) {
                        $visit_updated = updateVisitPaymentStatus($db, $visit_id, $user_branch_id);
                        if ($visit_updated) {
                            $updated_visits[] = $visit_id;
                        }
                        updateVisitCompletionStatus($db, $visit_id, $user_branch_id);
                    }
                }
                
                $db->commit();
                
                $updated_totals = [
                    'subtotal' => 0, 'total_amount' => 0, 'total_paid' => 0,
                    'total_balance' => 0, 'total_discount' => 0,
                    'total_pharmacy_discount' => 0, 'total_cashier_discount' => 0,
                    'total_premium' => 0
                ];
                
                $stmt = $db->prepare("
                    SELECT 
                        COALESCE(SUM(subtotal), 0) as subtotal,
                        COALESCE(SUM(total_amount), 0) as total_amount,
                        COALESCE(SUM(paid_amount), 0) as total_paid,
                        COALESCE(SUM(balance), 0) as total_balance,
                        COALESCE(SUM(total_discount), 0) as total_discount,
                        COALESCE(SUM(discount_amount), 0) as total_pharmacy_discount,
                        COALESCE(SUM(cashier_discount), 0) as total_cashier_discount,
                        COALESCE(SUM(premium_amount), 0) as total_premium
                    FROM bills 
                    WHERE branch_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$user_branch_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $updated_totals['subtotal'] = (float)$result['subtotal'];
                    $updated_totals['total_amount'] = (float)$result['total_amount'];
                    $updated_totals['total_paid'] = (float)$result['total_paid'];
                    $updated_totals['total_balance'] = (float)$result['total_balance'];
                    $updated_totals['total_discount'] = (float)$result['total_discount'];
                    $updated_totals['total_pharmacy_discount'] = (float)$result['total_pharmacy_discount'];
                    $updated_totals['total_cashier_discount'] = (float)$result['total_cashier_discount'];
                    $updated_totals['total_premium'] = (float)$result['total_premium'];
                }
                
                $message = $success_count . " bill(s) updated!<br>";
                $message .= "Total Paid: " . $currency . " " . number_format($total_amount_paid, 0);
                if (!empty($updated_visits)) {
                    $message .= "<br>✅ " . count($updated_visits) . " visit(s) updated";
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_numbers' => $receipt_numbers,
                    'total_paid' => $total_amount_paid,
                    'total_discount' => $total_discount_applied,
                    'pharmacy_discount' => $total_pharmacy_discount,
                    'cashier_discount' => $total_cashier_discount,
                    'total_premium' => $total_premium_charged,
                    'count' => $success_count,
                    'payment_type' => $action === 'partial_payment' ? 'partial' : 'full',
                    'updated_totals' => $updated_totals,
                    'processed_bill_ids' => $processed_bill_ids,
                    'updated_visits' => $updated_visits,
                    'bill_recalc' => $bill_recalc_results
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                error_log("❌ Payment error: " . $e->getMessage());
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // ================================================================
    // GET BILLS WITH ITEMS
    // ================================================================
    $bills_query = "
        SELECT 
            b.*,
            b.discount_amount, b.pharmacy_discount, b.cashier_discount,
            b.total_discount, b.paid_amount, b.balance, b.subtotal,
            b.total_amount, b.premium_amount, b.premium_note, b.visit_id,
            v.visit_number, v.visit_type, v.visit_date,
            v.payment_status as visit_payment_status,
            u.full_name as doctor_name,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone, p.gender, p.date_of_birth, p.address, p.blood_group, p.email
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE b.branch_id = ? AND b.status != 'cancelled'
    ";

    $params = [$user_branch_id];

    if ($selected_bill_id > 0) {
        $bills_query .= " AND b.id = ?";
        $params[] = $selected_bill_id;
    }

    if (!empty($search)) {
        $bills_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $bills_query .= " ORDER BY b.created_at ASC";

    $stmt = $db->prepare($bills_query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bills as $index => $bill) {
        $recalc = recalculateBillTotals($db, $bill['id'], $user_branch_id);
        if ($recalc) {
            $bills[$index]['subtotal'] = $recalc['subtotal'];
            $bills[$index]['total_discount'] = $recalc['total_discount'];
            $bills[$index]['discount_amount'] = $recalc['pharmacy_discount'];
            $bills[$index]['pharmacy_discount'] = $recalc['pharmacy_discount'];
            $bills[$index]['cashier_discount'] = $recalc['cashier_discount'];
            $bills[$index]['premium_amount'] = $recalc['premium_amount'];
            $bills[$index]['total_amount'] = $recalc['total_amount'];
            $bills[$index]['paid_amount'] = $recalc['paid_amount'];
            $bills[$index]['balance'] = $recalc['balance'];
            $bills[$index]['status'] = $recalc['status'];
        }
    }

    // ✅ AUTO-REPAIR VISITS
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT b.visit_id 
            FROM bills b
            WHERE b.branch_id = ? 
            AND b.visit_id IS NOT NULL 
            AND b.status != 'cancelled'
        ");
        $stmt->execute([$user_branch_id]);
        $visit_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($visit_ids as $vid) {
            if ($vid > 0) {
                updateVisitPaymentStatus($db, $vid, $user_branch_id);
                updateVisitCompletionStatus($db, $vid, $user_branch_id);
            }
        }
    } catch (Exception $e) {
        error_log("Auto-repair visits error: " . $e->getMessage());
    }

    // ================================================================
    // GET ITEMS FOR EACH BILL
    // ================================================================
    $all_items_by_bill = [];
    $medication_confirmed = [];
    $bill_locked_status = [];
    $bill_pending_prescription_count = [];
    
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT bi.*,
                (SELECT status FROM prescriptions WHERE id = bi.reference_id AND reference_type = 'prescription') as prescription_status
            FROM bill_items bi
            WHERE bi.bill_id = ? AND bi.status != 'cancelled'
            ORDER BY bi.item_type ASC, bi.created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $all_items_by_bill[$bill['id']] = $items;
        
        $has_medication = false;
        $med_confirmed = true;
        $pending_pres_count = 0;
        
        foreach ($items as $item) {
            if ($item['item_type'] === 'medication') {
                $has_medication = true;
                $pres_status = $item['prescription_status'] ?? 'pending';
                
                if ($pres_status !== 'confirmed' && $pres_status !== 'dispensed') {
                    $med_confirmed = false;
                    if ($item['reference_type'] === 'prescription') {
                        $pending_pres_count++;
                    }
                }
                
                if (($item['unit_price'] ?? 0) <= 0) {
                    $med_confirmed = false;
                }
            }
        }
        
        $medication_confirmed[$bill['id']] = $has_medication ? $med_confirmed : true;
        $bill_pending_prescription_count[$bill['id']] = $pending_pres_count;
        $bill_locked_status[$bill['id']] = ($pending_pres_count > 0);
    }

    // ================================================================
    // GROUP BILLS BY PATIENT
    // ================================================================
    $patient_bills_data = [];
    $patient_map = [];

    foreach ($bills as $bill) {
        $patient_id = $bill['patient_id'];
        
        if (!isset($patient_map[$patient_id])) {
            $patient_map[$patient_id] = [
                'patient_id' => $patient_id,
                'full_name' => $bill['patient_name'],
                'patient_number' => $bill['patient_number'],
                'phone' => $bill['phone'],
                'gender' => $bill['gender'],
                'date_of_birth' => $bill['date_of_birth'],
                'address' => $bill['address'],
                'blood_group' => $bill['blood_group'],
                'email' => $bill['email'],
                'doctor_name' => $bill['doctor_name'],
                'bills' => []
            ];
        }
        
        $bill['items'] = $all_items_by_bill[$bill['id']] ?? [];
        $bill['med_confirmed'] = $medication_confirmed[$bill['id']] ?? true;
        $bill['is_locked'] = $bill_locked_status[$bill['id']] ?? false;
        $bill['pending_prescriptions'] = $bill_pending_prescription_count[$bill['id']] ?? 0;
        $patient_map[$patient_id]['bills'][] = $bill;
    }

    $patient_bills_data = array_values($patient_map);

    // ================================================================
    // CALCULATE TOTALS
    // ================================================================
    $total_patients = count($patient_bills_data);
    $total_bills = count($bills);
    $total_balance = 0;
    $total_subtotal = 0;
    $total_amount = 0;
    $total_pharmacy_discount = 0;
    $total_cashier_discount = 0;
    $total_discount = 0;
    $total_paid = 0;
    $total_premium = 0;
    $total_locked_bills = 0;
    $total_pending_prescriptions = 0;

    foreach ($bills as $bill) {
        $total_subtotal += (float)($bill['subtotal'] ?? 0);
        $total_amount += (float)($bill['total_amount'] ?? 0);
        $total_paid += (float)($bill['paid_amount'] ?? 0);
        $total_pharmacy_discount += (float)($bill['discount_amount'] ?? 0);
        $total_cashier_discount += (float)($bill['cashier_discount'] ?? 0);
        $total_discount += (float)($bill['total_discount'] ?? 0);
        $total_balance += (float)($bill['balance'] ?? 0);
        $total_premium += (float)($bill['premium_amount'] ?? 0);
        
        if ($bill_locked_status[$bill['id']] ?? false) {
            $total_locked_bills++;
        }
        $total_pending_prescriptions += ($bill_pending_prescription_count[$bill['id']] ?? 0);
    }

    $has_selected_bill = $selected_bill_id > 0 && !empty($bills);
    $selected_bill = null;
    if ($has_selected_bill) {
        foreach ($bills as $bill) {
            if ($bill['id'] == $selected_bill_id) {
                $selected_bill = $bill;
                break;
            }
        }
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bills = [];
    $patient_bills_data = [];
    $total_bills = 0;
    $total_patients = 0;
    $total_balance = 0;
    $total_subtotal = 0;
    $total_amount = 0;
    $total_pharmacy_discount = 0;
    $total_cashier_discount = 0;
    $total_discount = 0;
    $total_paid = 0;
    $total_premium = 0;
    $total_locked_bills = 0;
    $total_pending_prescriptions = 0;
    $has_selected_bill = false;
    $selected_bill = null;
    $currency = 'TSh';
    $all_items_by_bill = [];
    $medication_confirmed = [];
    $bill_locked_status = [];
    $bill_pending_prescription_count = [];
    error_log("Process payment error: " . $e->getMessage());
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #059669;
            --primary-dark: #047857;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --premium: #D97706;
            --premium-bg: #FEF3C7;
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --locked-color: #DC2626;
            --locked-bg: #FEE2E2;
            --sidebar-width: 270px;
            --content-max-width: 1100px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --locked-bg: #3A1A1A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: 68px;
            padding: 20px 24px 200px 24px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.25);
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .page-title i { font-size: 1.5rem; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.68rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .header-badge.locked {
            background: rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }
        
        .page-header .header-badge.premium-badge-header {
            background: rgba(251,191,36,0.3);
            color: #FCD34D;
            font-weight: 600;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 7px 14px;
            border-radius: 9px;
            font-weight: 500;
            font-size: 0.78rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
        }
        
        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .patient-card:hover { border-color: var(--success); }
        
        .patient-card .card-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
        }
        
        .patient-card .card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .patient-card .card-header .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .patient-card .card-header .patient-name {
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        .patient-card .card-header .patient-id {
            font-size: 0.7rem;
            opacity: 0.85;
            font-family: var(--font-mono);
        }
        
        .patient-card .card-header .bill-summary {
            display: flex;
            gap: 12px;
            font-size: 0.72rem;
            background: rgba(255,255,255,0.12);
            padding: 5px 14px;
            border-radius: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-card .card-body { padding: 0; }
        .patient-card .card-body.collapsed { display: none; }
        
        /* MASTER TABLE */
        .master-table-wrap {
            overflow-x: auto;
            background: var(--bg-card);
        }
        
        .master-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 850px;
        }
        
        .master-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: white;
            background: linear-gradient(135deg, #064E3B, #065F46);
            white-space: nowrap;
        }
        
        .master-table tbody td {
            padding: 9px 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-family: var(--font-mono);
            font-variant-numeric: tabular-nums;
        }
        
        .master-table tbody tr:hover td { background: var(--success-bg); }
        
        .master-table tbody tr.item-locked td {
            background: var(--locked-bg) !important;
        }
        
        .master-table tbody tr.bill-paid td {
            opacity: 0.6;
            background: var(--success-bg);
        }
        
        /* HEADER INFO ROW */
        .header-info-row {
            background: linear-gradient(135deg, #064E3B, #065F46);
        }
        
        .header-info-row td { padding: 8px 14px !important; }
        
        .header-info-content {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            font-size: 0.78rem;
        }
        
        .header-info-content .info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.10);
        }
        
        .header-info-content .info-item .label {
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            font-size: 0.6rem;
            text-transform: uppercase;
        }
        
        .header-info-content .info-item .value {
            font-weight: 700;
            font-size: 0.85rem;
            font-family: var(--font-mono);
            color: white;
        }
        
        .header-info-content .info-item .value.subtotal-value { color: #6EE7B7; }
        .header-info-content .info-item .value.paid-value { color: #34D399; }
        .header-info-content .info-item .value.balance-value { color: #FCA5A5; }
        .header-info-content .info-item .value.balance-value.zero-balance { color: #6EE7B7; }
        .header-info-content .info-item .value.discount-value { color: #FCD34D; }
        .header-info-content .info-item .value.premium-value { color: #FCD34D; }
        
        /* BILL HEADER ROW */
        .bill-header-row {
            background: var(--gray-100);
            border-bottom: 2px solid var(--border-color);
        }
        
        [data-theme="dark"] .bill-header-row { background: var(--gray-700); }
        
        .bill-header-row td { padding: 6px 14px !important; }
        
        .bill-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.72rem;
        }
        
        .bill-header-info .bill-number {
            font-weight: 700;
            color: var(--primary);
            font-family: var(--font-mono);
            font-size: 0.8rem;
        }
        
        .bill-header-info .bill-status {
            font-size: 0.58rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 16px;
        }
        
        .bill-header-info .bill-status.pending { background: #FEF3C7; color: #D97706; }
        .bill-header-info .bill-status.partial { background: #DBEAFE; color: #2563EB; }
        .bill-header-info .bill-status.paid { background: #D1FAE5; color: #059669; }
        
        /* BADGES */
        .locked-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 16px;
            background: var(--locked-bg);
            color: var(--locked-color);
            border: 1px solid var(--locked-color);
        }
        
        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #D97706;
        }
        
        [data-theme="dark"] .premium-badge {
            background: #3D2E0A;
            color: #FCD34D;
        }
        
        /* CHECKBOX */
        .item-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--success);
            border-radius: 4px;
        }
        
        .item-checkbox:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        /* ================================================================
           ✅ PAYMENT CONTROLS - FIXED WIDTH & HEIGHT
           ✅ Width: inalingana na content (1100px) - haigusi sidebar
           ✅ Left: 270px (baada ya sidebar) + centered
           ✅ Height: imeongezwa kwa UX bora
           ================================================================ */
        .payment-controls-wrapper {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 100;
            display: flex;
            justify-content: center;
            padding: 0 24px 12px;
            pointer-events: none;
        }
        
        .payment-controls {
            background: var(--bg-card);
            border-top: 3px solid var(--success);
            border-left: 3px solid var(--success);
            border-right: 3px solid var(--success);
            border-radius: 14px 14px 0 0;
            padding: 14px 20px 16px;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.12);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            min-height: 90px;
            max-width: var(--content-max-width);
            width: 100%;
            pointer-events: auto;
            justify-content: center;
        }
        
        @media (max-width: 1024px) {
            .payment-controls-wrapper {
                left: 0;
                padding: 0 16px 12px;
            }
        }
        
        .payment-controls .control-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
            flex-shrink: 0;
            padding: 4px 0;
        }
        
        .payment-controls .control-group label {
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            font-family: var(--font-mono);
        }
        
        .payment-controls select,
        .payment-controls input[type="text"] {
            padding: 8px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.78rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 110px;
            font-family: var(--font-mono);
            height: 42px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .payment-controls select:focus,
        .payment-controls input[type="text"]:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }
        
        .payment-controls .divider {
            width: 1px;
            height: 36px;
            background: var(--border-color);
            flex-shrink: 0;
        }
        
        /* TOTAL DISPLAY */
        .total-display {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--success-bg);
            padding: 8px 16px;
            border-radius: 12px;
            border: 2px solid var(--success);
            height: 52px;
            flex-shrink: 0;
        }
        
        .total-display .total-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1px 10px;
        }
        
        .total-display .total-item .label {
            font-size: 0.56rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
            font-family: var(--font-mono);
        }
        
        .total-display .total-item .value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--font-mono);
            white-space: nowrap;
        }
        
        .total-display .total-item .value.grand {
            color: var(--danger);
            font-size: 1.05rem;
        }
        
        .total-display .total-item .value.premium {
            color: var(--premium);
        }
        
        .total-display .total-item.premium-card {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 1px solid #D97706;
            border-radius: 8px;
            padding: 3px 12px;
        }
        
        [data-theme="dark"] .total-display .total-item.premium-card {
            background: linear-gradient(135deg, #3D2E0A, #4A3A12);
        }
        
        .total-display .total-item.premium-card .label { color: #D97706; }
        .total-display .total-item.premium-card .value { color: #D97706; font-weight: 800; }
        
        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 9px;
            font-weight: 700;
            font-size: 0.78rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            height: 44px;
            font-family: var(--font-mono);
            letter-spacing: 0.02em;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(5, 150, 105, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
            box-shadow: 0 3px 10px rgba(217, 119, 6, 0.3);
        }
        
        .btn-warning:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(217, 119, 6, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover:not(:disabled) {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.72rem;
            height: 40px;
        }
        
        /* AMOUNT INPUT */
        .amount-input-wrap {
            position: relative;
            display: inline-block;
        }
        
        .amount-input-wrap .currency-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.68rem;
            color: var(--text-secondary);
            font-weight: 700;
            font-family: var(--font-mono);
            pointer-events: none;
            z-index: 2;
        }
        
        .amount-input-wrap input {
            padding-left: 46px !important;
            text-align: right;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: 700;
            width: 110px;
        }
        
        .premium-input {
            border-color: #D97706 !important;
            background: #FEF3C7 !important;
            color: #D97706 !important;
        }
        
        [data-theme="dark"] .premium-input {
            background: #3D2E0A !important;
            color: #FCD34D !important;
        }
        
        /* TOAST */
        .toast-custom {
            position: fixed;
            bottom: 160px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
        .toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* FOOTER */
        .footer {
            padding: 12px 0;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
            margin-top: 20px;
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }
        
        .footer .footer-brand {
            color: var(--success);
            font-weight: 700;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px 16px 220px 16px; }
        }
        
        @media (max-width: 768px) {
            .payment-controls {
                max-height: 260px;
                overflow-y: auto;
                flex-direction: column;
                align-items: stretch;
                padding: 12px;
            }
            
            .payment-controls .control-group { justify-content: center; }
            .payment-controls .btn { width: 100%; justify-content: center; }
            
            .total-display {
                flex-wrap: wrap;
                justify-content: center;
                height: auto;
                padding: 10px 14px;
            }
            
            .main-content { padding-bottom: 320px; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Process Payments
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($total_locked_bills > 0): ?>
                    <span class="header-badge locked">
                        <i class="fas fa-lock"></i> <?= $total_locked_bills ?> LOCKED                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Select items to pay
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills ?> pending bill(s)
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill"></i>
                    Balance: <?= $currency ?> <?= number_format($total_balance, 0) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-check-circle"></i>
                    Paid: <?= $currency ?> <?= number_format($total_paid, 0) ?>
                </span>
                <?php if ($total_premium > 0): ?>
                <span class="header-badge premium-badge-header">
                    <i class="fas fa-crown"></i>
                    Premium: <?= $currency ?> <?= number_format($total_premium, 0) ?>
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending Bills
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- LOCKED WARNING -->
    <?php if ($total_locked_bills > 0): ?>
        <div style="background:var(--locked-bg);border:2px solid var(--locked-color);border-radius:12px;padding:12px 20px;margin-bottom:16px;display:flex;align-items:center;gap:12px;max-width:1100px;margin-left:auto;margin-right:auto;">
            <i class="fas fa-lock" style="font-size:1.5rem;color:var(--locked-color);"></i>
            <div>
                <div style="font-weight:700;font-size:0.9rem;color:var(--locked-color);">
                    🔒 <?= $total_locked_bills ?> Bill(s) LOCKED - Haiwezi Kulipwa
                </div>
                <div style="font-size:0.78rem;color:var(--locked-color);opacity:0.8;margin-top:2px;">
                    Kuna <strong><?= $total_pending_prescriptions ?></strong> prescription(s) ambazo hazijathibitishwa na pharmacy.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- PATIENT CARDS -->
    <?php if (count($patient_bills_data) > 0): ?>
        <?php foreach ($patient_bills_data as $patient): 
            $patient_bills = isset($patient['bills']) && is_array($patient['bills']) ? $patient['bills'] : [];
            $patient_total_balance = 0;
            $patient_total_subtotal = 0;
            $patient_total_amount = 0;
            $patient_paid_amount = 0;
            $patient_discount = 0;
            $patient_premium = 0;
            $patient_items = 0;
            $patient_med_items = 0;
            $patient_locked_items = 0;
            $patient_has_locked = false;
            
            foreach ($patient_bills as $bill) {
                $patient_total_balance += (float)($bill['balance'] ?? 0);
                $patient_total_subtotal += (float)($bill['subtotal'] ?? 0);
                $patient_total_amount += (float)($bill['total_amount'] ?? 0);
                $patient_paid_amount += (float)($bill['paid_amount'] ?? 0);
                $patient_discount += (float)($bill['total_discount'] ?? 0);
                $patient_premium += (float)($bill['premium_amount'] ?? 0);
                
                if ($bill['is_locked'] ?? false) $patient_has_locked = true;
                $patient_locked_items += ($bill['pending_prescriptions'] ?? 0);
                
                foreach ($bill['items'] as $item) {
                    $patient_items++;
                    if ($item['item_type'] === 'medication') $patient_med_items++;
                }
            }
            
            $doctor_name = $patient['doctor_name'] ?? 'Not Assigned';
            $is_selected_patient = $has_selected_bill && $selected_bill && $selected_bill['patient_id'] == $patient['patient_id'];
        ?>
        <div class="patient-card" data-patient-id="<?= $patient['patient_id'] ?>" <?= $patient_has_locked ? 'style="border-color:var(--locked-color);"' : '' ?>>
            <div class="card-header" onclick="togglePatientCard(this)" <?= $patient_has_locked ? 'style="background:linear-gradient(135deg, #DC2626, #B91C1C);"' : '' ?>>
                <div class="patient-info">
                    <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="patient-name">
                            <?= htmlspecialchars($patient['full_name']) ?>
                            <?php if ($patient_has_locked): ?>
                                <span class="locked-badge" style="font-size:0.5rem;padding:1px 8px;margin-left:6px;">
                                    <i class="fas fa-lock"></i> LOCKED
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="patient-id"><?= htmlspecialchars($patient['patient_number']) ?></div>
                        <div style="font-size:0.6rem; opacity:0.8;">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div class="bill-summary">
                        <span>Items: <strong><?= $patient_items ?></strong></span>
                        <span>|</span>
                        <span>Meds: <strong><?= $patient_med_items ?></strong></span>
                        <?php if ($patient_locked_items > 0): ?>
                            <span>|</span>
                            <span style="color:#FCA5A5;">🔒 <?= $patient_locked_items ?></span>
                        <?php endif; ?>
                        <span>|</span>
                        <span>Paid: <strong style="color:#6EE7B7;"><?= $currency ?> <?= number_format($patient_paid_amount, 0) ?></strong></span>
                        <span>|</span>
                        <span>Bal: <strong style="color: <?= $patient_total_balance > 0 ? '#FCD34D' : '#6EE7B7' ?>;">
                            <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                        </strong></span>
                    </div>
                </div>
            </div>
            
            <div class="card-body <?= $is_selected_patient ? '' : 'collapsed' ?>">
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th style="width:44px; text-align:center;">
                                    <input type="checkbox" class="item-checkbox select-all-items" 
                                           data-patient-id="<?= $patient['patient_id'] ?>" 
                                           onchange="selectAllItems(this, <?= $patient['patient_id'] ?>); updateSelectedTotal();">
                                </th>
                                <th style="min-width:140px;">Item Name</th>
                                <th style="min-width:80px;">Type</th>
                                <th style="text-align:center; min-width:50px;">Qty</th>
                                <th style="text-align:right; min-width:100px;">Total</th>
                                <th style="text-align:center; min-width:100px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="header-info-row">
                                <td colspan="6" style="padding:8px 14px;">
                                    <div class="header-info-content">
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-calculator"></i> Subtotal</span>
                                            <span class="value subtotal-value"><?= $currency ?> <?= number_format($patient_total_subtotal, 0) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-check-circle"></i> Paid</span>
                                            <span class="value paid-value"><?= $currency ?> <?= number_format($patient_paid_amount, 0) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-balance-scale"></i> Balance</span>
                                            <span class="value balance-value <?= $patient_total_balance <= 0 ? 'zero-balance' : '' ?>">
                                                <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                                            </span>
                                        </div>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-tag"></i> Discount</span>
                                            <span class="value discount-value">
                                                <?= $currency ?> <?= number_format($patient_discount, 0) ?>
                                            </span>
                                        </div>
                                        <?php if ($patient_premium > 0): ?>
                                        <div class="info-item">
                                            <span class="label"><i class="fas fa-crown"></i> Premium</span>
                                            <span class="value premium-value"><?= $currency ?> <?= number_format($patient_premium, 0) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php foreach ($patient_bills as $bill): 
                                $items = isset($bill['items']) && is_array($bill['items']) ? $bill['items'] : [];
                                $bill_is_locked = $bill['is_locked'] ?? false;
                                $bill_pending_pres = $bill['pending_prescriptions'] ?? 0;
                                $bill_number = $bill['bill_number'] ?? 'N/A';
                                $bill_status = $bill['status'] ?? 'pending';
                                $bill_premium = (float)($bill['premium_amount'] ?? 0);
                                $bill_balance = (float)($bill['balance'] ?? 0);
                                $bill_paid = (float)($bill['paid_amount'] ?? 0);
                                $bill_subtotal = (float)($bill['subtotal'] ?? 0);
                                $bill_total_amount = (float)($bill['total_amount'] ?? 0);
                            ?>
                                <tr class="bill-header-row" <?= $bill_is_locked ? 'style="background:var(--locked-bg);"' : '' ?>>
                                    <td colspan="6" style="padding:6px 14px;">
                                        <div class="bill-header-info">
                                            <span class="bill-number"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill_number) ?></span>
                                            <span class="bill-status <?= $bill_status ?>"><?= ucfirst($bill_status) ?></span>
                                            <?php if ($bill_is_locked): ?>
                                                <span class="locked-badge">
                                                    <i class="fas fa-lock"></i> <?= $bill_pending_pres ?> Pending
                                                </span>
                                            <?php endif; ?>
                                            <span style="color:var(--text-secondary);">
                                                Sub: <strong style="color:var(--primary);"><?= $currency ?> <?= number_format($bill_subtotal, 0) ?></strong>
                                            </span>
                                            <span style="color:var(--text-secondary);">
                                                Total: <strong style="color:var(--danger);"><?= $currency ?> <?= number_format($bill_total_amount, 0) ?></strong>
                                            </span>
                                            <span style="color:var(--text-secondary);">
                                                Paid: <strong style="color:var(--success);"><?= $currency ?> <?= number_format($bill_paid, 0) ?></strong>
                                            </span>
                                            <span style="color:var(--text-secondary);">
                                                Bal: <strong style="color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                                    <?= $currency ?> <?= number_format($bill_balance, 0) ?>
                                                </strong>
                                            </span>
                                            <?php if ($bill_premium > 0): ?>
                                                <span class="premium-badge">
                                                    <i class="fas fa-crown"></i> <?= $currency ?> <?= number_format($bill_premium, 0) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                
                                <?php foreach ($items as $item): 
                                    $is_paid = ($item['status'] === 'paid');
                                    $is_cancelled = ($item['status'] === 'cancelled');
                                    $is_medication = ($item['item_type'] === 'medication');
                                    $pres_status = $item['prescription_status'] ?? 'pending';
                                    $is_item_locked = ($is_medication && $item['reference_type'] === 'prescription' && $pres_status !== 'confirmed' && $pres_status !== 'dispensed');
                                    $can_select = !$is_paid && !$is_cancelled && !$is_item_locked;
                                    $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                    $qty = (int)($item['quantity'] ?? 1);
                                    $item_status = $item['status'] ?? 'pending';
                                ?>
                                    <tr class="item-row <?= $is_paid ? 'bill-paid' : '' ?> <?= $is_item_locked ? 'item-locked' : '' ?>" 
                                        data-item-id="<?= $item['id'] ?>" 
                                        data-price="<?= $price ?>"
                                        data-bill-id="<?= $bill['id'] ?>"
                                        data-patient-id="<?= $patient['patient_id'] ?>"
                                        data-is-locked="<?= $is_item_locked ? 'true' : 'false' ?>"
                                        data-is-medication="<?= $is_medication ? 'true' : 'false' ?>">
                                        <td style="text-align:center;">
                                            <?php if ($is_item_locked): ?>
                                                <i class="fas fa-lock" style="color:var(--locked-color);"></i>
                                            <?php elseif ($can_select): ?>
                                                <input type="checkbox" class="item-checkbox item-select" 
                                                       data-id="<?= $item['id'] ?>" 
                                                       data-price="<?= $price ?>"
                                                       data-bill-id="<?= $bill['id'] ?>"
                                                       data-patient-id="<?= $patient['patient_id'] ?>"
                                                       data-is-locked="<?= $is_item_locked ? 'true' : 'false' ?>"
                                                       onchange="updateSelectedTotal()">
                                            <?php elseif ($is_paid): ?>
                                                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                            <?php if ($is_medication && !empty($item['instructions'])): ?>
                                                <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;">
                                                    <i class="fas fa-edit"></i> <?= htmlspecialchars(substr($item['instructions'], 0, 30)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-size:0.55rem; background:var(--bg-body); padding:2px 8px; border-radius:5px;">
                                                <?= ucfirst($item['item_type'] ?? 'item') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center; font-weight:600;"><?= $qty ?></td>
                                        <td style="text-align:right; font-weight:700; <?= $is_paid ? 'color:var(--success);' : 'color:var(--danger);' ?>">
                                            <?= $currency ?> <?= number_format($price, 0) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if ($is_item_locked): ?>
                                                <span class="locked-badge"><i class="fas fa-lock"></i> Locked</span>
                                            <?php elseif ($is_paid): ?>
                                                <span class="bill-status paid">✅ Paid</span>
                                            <?php elseif ($item_status === 'partial'): ?>
                                                <span class="bill-status partial">🔄 Partial</span>
                                            <?php else: ?>
                                                <span class="bill-status pending">⏳ Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="background:var(--bg-card);border-radius:16px;border:2px solid var(--border-color);padding:60px 20px;box-shadow:var(--shadow);text-align:center;max-width:1100px;margin-left:auto;margin-right:auto;">
            <i class="fas fa-check-circle" style="color:var(--success);display:block;margin-bottom:16px;font-size:4rem;"></i>
            <h3 style="color:var(--text-primary);font-size:1.2rem;">No Pending Bills</h3>
            <p style="color:var(--text-secondary);margin-top:8px;">All bills have been paid. Great job! 🎉</p>
        </div>
    <?php endif; ?>

</main>

<!-- ✅ PAYMENT CONTROLS - FIXED WIDTH & HEIGHT -->
<div class="payment-controls-wrapper">
    <div class="payment-controls" id="paymentControls">
        <div class="control-group">
            <label><i class="fas fa-hand-holding-usd"></i> Method:</label>
            <select id="paymentMethod">
                <option value="cash">💰 Cash</option>
                <option value="m-pesa">📱 M-Pesa</option>
                <option value="airtel_money">📱 Airtel Money</option>
                <option value="tigo_pesa">📱 Tigo Pesa</option>
                <option value="halopesa">📱 HaloPesa</option>
                <option value="card">💳 Card</option>
                <option value="bank">🏦 Bank</option>
                <option value="insurance">🏥 Insurance</option>
            </select>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-percent"></i> Disc:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="discountAmount" class="discount-input" placeholder="0" 
                       value="0" oninput="formatAmount(this); updateSelectedTotal();">
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-crown"></i> Premium:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="premiumAmount" class="premium-input" placeholder="0" 
                       value="0" oninput="formatAmount(this); updateSelectedTotal();">
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-hand-holding-heart"></i> Partial:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="partialAmount" class="partial-input" placeholder="0" 
                       value="0" oninput="formatAmount(this); updateSelectedTotal();">
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="total-display" id="totalDisplay">
            <div class="total-item">
                <span class="label">Subtotal</span>
                <span class="value" id="displayTotal"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Disc</span>
                <span class="value" style="color:var(--warning);" id="displayDiscount"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item premium-card" id="premiumCard" style="display:none;">
                <span class="label"><i class="fas fa-crown"></i> Prem</span>
                <span class="value premium" id="displayPremium"><?= $currency ?> 0</span>
            </div>
            <div class="total-item">
                <span class="label">Total</span>
                <span class="value grand" id="displayGrandTotal"><?= $currency ?> 0</span>
            </div>
        </div>
        
        <div style="flex:1; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; min-width:0;">
            <span style="font-size:0.7rem;color:var(--text-secondary);padding:9px 12px;background:var(--bg-body);border-radius:20px;display:inline-flex;align-items:center;font-family:var(--font-mono);font-weight:700;height:44px;flex-shrink:0;">
                Selected: <strong id="selectedCountNum" style="color:var(--primary);margin-left:4px;">0</strong>
            </span>
            <button onclick="selectAllItemsAllPatients()" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> All
            </button>
            <button onclick="deselectAllItems()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> None
            </button>
            <button onclick="processPayment('partial')" class="btn btn-warning btn-sm" id="partialPayBtn">
                <i class="fas fa-hand-holding-heart"></i> PARTIAL
            </button>
            <button onclick="processPayment('full')" class="btn btn-success btn-sm" id="fullPayBtn">
                <i class="fas fa-check-circle"></i> FULL
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var currency = '<?= $currency ?>';
    var totalLockedBills = <?= $total_locked_bills ?>;

    console.log('💰 Process Payment v9.0');
    console.log('🔒 Locked Bills: ' + totalLockedBills);
    console.log('✅ Payment card - fixed width & height');

    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        syncDarkMode();
    })();

    function formatAmount(input) {
        var val = input.value.replace(/[^0-9.]/g, '');
        var parts = val.split('.');
        var whole = parts[0];
        var decimal = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
        if (whole.length > 0) {
            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        input.value = whole + decimal;
        var rawValue = parseFloat(val) || 0;
        input.dataset.rawValue = rawValue;
    }
    
    function getRawValue(input) {
        var raw = input.dataset.rawValue;
        if (raw !== undefined && raw !== '') {
            return parseFloat(raw) || 0;
        }
        var val = input.value.replace(/,/g, '');
        return parseFloat(val) || 0;
    }

    function togglePatientCard(header) {
        var card = header.closest('.patient-card');
        var body = card.querySelector('.card-body');
        if (body.classList.contains('collapsed')) {
            body.classList.remove('collapsed');
        } else {
            body.classList.add('collapsed');
        }
    }

    function selectAllItems(checkbox, patientId) {
        var checkboxes = document.querySelectorAll('.item-select[data-patient-id="' + patientId + '"]:not([data-is-locked="true"])');
        checkboxes.forEach(function(cb) {
            if (!cb.disabled) cb.checked = checkbox.checked;
        });
        updateSelectedTotal();
    }

    function selectAllItemsAllPatients() {
        var checkboxes = document.querySelectorAll('.item-select:not([data-is-locked="true"]):not(:disabled)');
        checkboxes.forEach(function(cb) { cb.checked = true; });
        document.querySelectorAll('.select-all-items').forEach(function(cb) { cb.checked = true; });
        updateSelectedTotal();
    }

    function deselectAllItems() {
        document.querySelectorAll('.item-select').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.select-all-items').forEach(function(cb) { cb.checked = false; });
        updateSelectedTotal();
    }

    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var count = checkboxes.length;
        var total_price = 0;
        var hasLockedItems = false;
        
        checkboxes.forEach(function(cb) {
            if (cb.getAttribute('data-is-locked') === 'true') hasLockedItems = true;
        });
        
        var discountInput = document.getElementById('discountAmount');
        var partialInput = document.getElementById('partialAmount');
        var premiumInput = document.getElementById('premiumAmount');
        
        var discount = getRawValue(discountInput);
        var partial = getRawValue(partialInput);
        var premium = getRawValue(premiumInput);
        
        checkboxes.forEach(function(cb) {
            var price = parseFloat(cb.dataset.price || 0);
            if (!isNaN(price)) total_price += price;
        });
        
        var pharmacyDiscount = 0;
        var billPremium = 0;
        var billIds = new Set();
        checkboxes.forEach(function(cb) {
            var billId = cb.dataset.billId;
            if (billId && !billIds.has(billId)) {
                billIds.add(billId);
                var row = cb.closest('.item-row');
                if (row) {
                    var prevRow = row.previousElementSibling;
                    while (prevRow) {
                        if (prevRow.classList.contains('bill-header-row')) {
                            var headerText = prevRow.textContent;
                            var discMatch = headerText.match(/Pharm: [\d,]+/);
                            if (discMatch) {
                                var num = discMatch[0].replace(/[^0-9]/g, '');
                                if (num) pharmacyDiscount += parseFloat(num);
                            }
                            var premiumMatch = headerText.match(/Premium: [\d,]+/);
                            if (premiumMatch) {
                                var num = premiumMatch[0].replace(/[^0-9]/g, '');
                                if (num) billPremium += parseFloat(num);
                            }
                            break;
                        }
                        prevRow = prevRow.previousElementSibling;
                    }
                }
            }
        });
        
        var totalPremiumDisplay = billPremium + premium;
        var totalDiscountDisplay = pharmacyDiscount + discount;
        var grand_total = total_price + totalPremiumDisplay - totalDiscountDisplay;
        if (grand_total < 0) grand_total = 0;
        
        document.getElementById('selectedCountNum').textContent = count;
        document.getElementById('displayTotal').textContent = currency + ' ' + total_price.toFixed(0);
        document.getElementById('displayDiscount').textContent = currency + ' ' + totalDiscountDisplay.toFixed(0);
        document.getElementById('displayPremium').textContent = currency + ' ' + totalPremiumDisplay.toFixed(0);
        document.getElementById('displayGrandTotal').textContent = currency + ' ' + grand_total.toFixed(0);
        
        var premiumCard = document.getElementById('premiumCard');
        if (totalPremiumDisplay > 0) {
            premiumCard.style.display = 'flex';
        } else {
            premiumCard.style.display = 'none';
        }
        
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        
        if (hasLockedItems) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-lock"></i> LOCKED';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-lock"></i> LOCKED';
            return;
        }
        
        if (count === 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL';
        } else {
            fullBtn.disabled = false;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> FULL ' + currency + ' ' + grand_total.toFixed(0);
            
            if (partial > 0 && partial <= grand_total) {
                partialBtn.disabled = false;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL ' + currency + ' ' + partial.toFixed(0);
            } else if (partial > grand_total) {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Exceeds';
            } else {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PARTIAL';
            }
        }
    }

    function processPayment(type) {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var itemIds = [];
        var hasLockedItems = false;
        
        checkboxes.forEach(function(cb) {
            itemIds.push(parseInt(cb.dataset.id));
            if (cb.getAttribute('data-is-locked') === 'true') hasLockedItems = true;
        });
        
        if (itemIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one item', 'warning');
            return;
        }
        
        if (hasLockedItems) {
            showToast('🔒 Locked', 'Kuna prescriptions ambazo hazijathibitishwa. Subiri pharmacy.', 'error');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var discount = getRawValue(document.getElementById('discountAmount'));
        var partialAmount = getRawValue(document.getElementById('partialAmount'));
        var premium = getRawValue(document.getElementById('premiumAmount'));
        
        var totalPrice = 0;
        checkboxes.forEach(function(cb) {
            totalPrice += parseFloat(cb.dataset.price || 0);
        });
        
        var pharmacyDiscount = 0;
        var billPremium = 0;
        var billIds = new Set();
        checkboxes.forEach(function(cb) {
            var billId = cb.dataset.billId;
            if (billId && !billIds.has(billId)) {
                billIds.add(billId);
                var row = cb.closest('.item-row');
                if (row) {
                    var prevRow = row.previousElementSibling;
                    while (prevRow) {
                        if (prevRow.classList.contains('bill-header-row')) {
                            var headerText = prevRow.textContent;
                            var discMatch = headerText.match(/Pharm: [\d,]+/);
                            if (discMatch) {
                                var num = discMatch[0].replace(/[^0-9]/g, '');
                                if (num) pharmacyDiscount += parseFloat(num);
                            }
                            var premiumMatch = headerText.match(/Premium: [\d,]+/);
                            if (premiumMatch) {
                                var num = premiumMatch[0].replace(/[^0-9]/g, '');
                                if (num) billPremium += parseFloat(num);
                            }
                            break;
                        }
                        prevRow = prevRow.previousElementSibling;
                    }
                }
            }
        });
        
        var totalPremium = billPremium + premium;
        var totalDiscountVal = pharmacyDiscount + discount;
        if (totalDiscountVal > totalPrice) {
            totalDiscountVal = totalPrice;
            discount = totalDiscountVal - pharmacyDiscount;
            if (discount < 0) discount = 0;
        }
        
        var grandTotal = totalPrice + totalPremium - totalDiscountVal;
        if (grandTotal < 0) grandTotal = 0;
        
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return;
            }
            if (partialAmount > grandTotal) {
                showToast('⚠️ Amount Exceeds', 'Partial amount exceeds grand total', 'warning');
                return;
            }
        }
        
        var confirmMsg = (type === 'partial' ? '💳 PARTIAL' : '💰 FULL') + ' PAYMENT\n' +
                         '═══════════════════════════════\n' +
                         'Subtotal: ' + currency + ' ' + totalPrice.toFixed(0) + '\n' +
                         'Discount: ' + currency + ' ' + totalDiscountVal.toFixed(0) + '\n' +
                         (totalPremium > 0 ? '👑 Premium: ' + currency + ' ' + totalPremium.toFixed(0) + '\n' : '') +
                         '───────────────────────────────\n' +
                         'TOTAL: ' + currency + ' ' + grandTotal.toFixed(0) + '\n' +
                         (type === 'partial' ? 'Paying: ' + currency + ' ' + partialAmount.toFixed(0) + '\n' +
                         'Remaining: ' + currency + ' ' + (grandTotal - partialAmount).toFixed(0) + '\n' : '') +
                         '\nConfirm for ' + itemIds.length + ' item(s)?';
        
        if (!confirm(confirmMsg)) return;
        
        var btn = type === 'partial' ? document.getElementById('partialPayBtn') : document.getElementById('fullPayBtn');
        var originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', type === 'partial' ? 'partial_payment' : 'complete_payment');
        formData.append('payment_method', paymentMethod);
        if (discount > 0) formData.append('discount_amount', discount);
        if (premium > 0) formData.append('premium_amount', premium);
        if (type === 'partial') formData.append('partial_amount', partialAmount);
        itemIds.forEach(function(id) {
            formData.append('item_ids[]', id);
        });
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                setTimeout(function() { window.location.reload(); }, 2500);
            } else {
                if (data.locked_bills) {
                    showToast('🔒 LOCKED', data.message, 'error');
                } else {
                    showToast('❌ Error', data.message, 'error');
                }
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedTotal();
        console.log('✅ Process Payment v9.0 loaded');
        console.log('✅ Payment card - width inalingana na table (1100px)');
    });
</script>

</body>
</html>