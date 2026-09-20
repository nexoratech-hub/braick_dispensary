<?php
// ================================================================
// FILE: frontend/pages/cashier/make_payment.php
// CASHIER - MAKE PAYMENT - v7.0 (IMPROVED PAYMENT CARD)
// ================================================================
// ✅ NEW: updateVisitPaymentStatus() - Inaupdate visit payment_status
// ✅ NEW: updateVisitCompletionStatus() - Inaupdate is_completed
// ✅ IMPROVED: Payment card - width inalingana na table, height imeongezwa
// ✅ FIXED: Full payment → visit payment_status = 'paid'
// ✅ FIXED: Partial payment → visit payment_status = 'partial'
// ✅ LOCK bills with pending prescriptions
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

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if ($bill_id <= 0) {
    header('Location: partial_payments.php?error=invalid_bill');
    exit;
}

$message = '';
$message_type = '';
$currency = 'TSh';

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
            SELECT COALESCE(SUM(total_discount), 0) as total_discount
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
        
        error_log("✅ VISIT #$visit_id UPDATED: payment_status=$visit_payment_status, total=$visit_total, paid=$visit_paid");
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
// ✅ HELPER: CHECK IF BILL IS LOCKED
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
        error_log("isBillLocked error: " . $e->getMessage());
        return true;
    }
}

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // ✅ CHECK LOCK STATUS
    // ================================================================
    if (isBillLocked($db, $bill_id)) {
        $_SESSION['flash_message'] = "🔒 Bill hii haiwezi kulipwa! Kuna prescriptions ambazo hazijathibitishwa na pharmacy.";
        $_SESSION['flash_type'] = 'error';
        header('Location: partial_payments.php?locked=1');
        exit;
    }

    // ================================================================
    // AJAX: MAKE PAYMENT
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $premium_amount = isset($_POST['premium_amount']) ? floatval($_POST['premium_amount']) : 0;
        $premium_note = isset($_POST['premium_note']) ? trim($_POST['premium_note']) : '';
        $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
        $payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : 'full';
        $bill_id_post = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
        
        if ($action === 'make_payment') {
            if ($bill_id_post <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid bill ID']);
                exit;
            }
            
            if (isBillLocked($db, $bill_id_post)) {
                echo json_encode([
                    'success' => false, 
                    'message' => '🔒 HAIWEZI KULIPWA! Kuna prescriptions ambazo hazijathibitishwa.',
                    'locked' => true
                ]);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT b.*, p.full_name as patient_name, p.patient_id as patient_number, p.id as patient_id_ref
                    FROM bills b
                    JOIN patients p ON b.patient_id = p.id
                    WHERE b.id = ? AND b.branch_id = ? AND b.status != 'cancelled'
                ");
                $stmt->execute([$bill_id_post, $user_branch_id]);
                $current_bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_bill) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Bill not found']);
                    exit;
                }
                
                $visit_id = (int)($current_bill['visit_id'] ?? 0);
                
                $subtotal = (float)($current_bill['subtotal'] ?? 0);
                $paid_amount = (float)($current_bill['paid_amount'] ?? 0);
                $existing_premium = (float)($current_bill['premium_amount'] ?? 0);
                $existing_premium_note = $current_bill['premium_note'] ?? '';
                
                $existing_pharmacy_discount = (float)($current_bill['pharmacy_discount'] ?? 0);
                if ($existing_pharmacy_discount == 0) {
                    $existing_pharmacy_discount = (float)($current_bill['discount_amount'] ?? 0);
                }
                $existing_cashier_discount = (float)($current_bill['cashier_discount'] ?? 0);
                $existing_total_discount = $existing_pharmacy_discount + $existing_cashier_discount;
                
                $new_premium = max(0, $premium_amount);
                $new_discount = max(0, $discount_amount);
                
                $total_premium_all = $existing_premium + $new_premium;
                $updated_cashier_discount = $existing_cashier_discount + $new_discount;
                $updated_total_discount = $existing_pharmacy_discount + $updated_cashier_discount;
                
                $remaining_balance = $subtotal + $total_premium_all - $updated_total_discount - $paid_amount;
                if ($remaining_balance < 0) $remaining_balance = 0;
                
                if ($payment_type === 'partial') {
                    $amount_to_pay = min($partial_amount, $remaining_balance);
                    if ($amount_to_pay <= 0) {
                        $db->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Invalid partial amount']);
                        exit;
                    }
                    if ($amount_to_pay >= $remaining_balance) {
                        $amount_to_pay = $remaining_balance;
                        $payment_type = 'full';
                    }
                } else {
                    $amount_to_pay = $remaining_balance;
                }
                
                if ($amount_to_pay <= 0) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Amount to pay must be greater than 0']);
                    exit;
                }
                
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $new_paid = $paid_amount + $amount_to_pay;
                
                $new_total_amount = $subtotal + $total_premium_all - $updated_total_discount;
                if ($new_total_amount < 0) $new_total_amount = 0;
                
                $new_balance = $new_total_amount - $new_paid;
                if ($new_balance < 0) $new_balance = 0;
                
                $premium_note_final = $existing_premium_note;
                if (!empty($premium_note) && $new_premium > 0) {
                    if (!empty($premium_note_final)) {
                        $premium_note_final .= ', ' . $premium_note;
                    } else {
                        $premium_note_final = $premium_note;
                    }
                }
                
                if ($new_balance <= 0.01) {
                    $new_status = 'paid';
                } elseif ($new_paid > 0 && $new_balance > 0) {
                    $new_status = 'partial';
                } else {
                    $new_status = $current_bill['status'] ?? 'pending';
                }
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET paid_amount = ?,
                        balance = ?,
                        total_amount = ?,
                        cashier_discount = ?,
                        discount_amount = ?,
                        pharmacy_discount = ?,
                        total_discount = ?,
                        premium_amount = ?,
                        premium_note = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([
                    $new_paid,
                    $new_balance,
                    $new_total_amount,
                    $updated_cashier_discount,
                    $existing_pharmacy_discount,
                    $existing_pharmacy_discount,
                    $updated_total_discount,
                    $total_premium_all,
                    $premium_note_final,
                    $new_status,
                    $bill_id_post,
                    $user_branch_id
                ]);
                
                $stmt = $db->prepare("
                    SELECT id, total_price, status FROM bill_items 
                    WHERE bill_id = ? AND status != 'cancelled'
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$bill_id_post]);
                $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $remaining_to_pay = $amount_to_pay;
                foreach ($all_items as $item) {
                    if ($remaining_to_pay <= 0) break;
                    if ($item['status'] === 'paid') continue;
                    
                    $item_price = (float)$item['total_price'];
                    if ($remaining_to_pay >= $item_price) {
                        $stmt_update = $db->prepare("UPDATE bill_items SET status = 'paid', updated_at = NOW() WHERE id = ?");
                        $stmt_update->execute([$item['id']]);
                        $remaining_to_pay -= $item_price;
                    } else {
                        $stmt_update = $db->prepare("UPDATE bill_items SET status = 'partial', updated_at = NOW() WHERE id = ?");
                        $stmt_update->execute([$item['id']]);
                        $remaining_to_pay = 0;
                        break;
                    }
                }
                
                $notes = 'Payment | Pharm Disc: ' . $currency . ' ' . number_format($existing_pharmacy_discount, 0) . 
                         ' | Cashier Disc: ' . $currency . ' ' . number_format($new_discount, 0);
                if ($total_premium_all > 0) {
                    $notes .= ' | Premium: ' . $currency . ' ' . number_format($total_premium_all, 0);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, reference_number, received_by, branch_id, received_at, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $receipt_number,
                    $bill_id_post,
                    $current_bill['patient_id_ref'] ?? $current_bill['patient_id'],
                    $amount_to_pay,
                    $payment_method,
                    null,
                    $user_id,
                    $user_branch_id,
                    $notes
                ]);
                
                // ✅ UPDATE VISIT
                if ($visit_id > 0) {
                    updateVisitPaymentStatus($db, $visit_id, $user_branch_id);
                    updateVisitCompletionStatus($db, $visit_id, $user_branch_id);
                }
                
                $db->commit();
                
                $message = "Payment successful! Amount: " . $currency . " " . number_format($amount_to_pay, 0);
                if ($new_premium > 0) {
                    $message .= " | 👑 Premium: " . $currency . " " . number_format($new_premium, 0);
                }
                
                $is_all_paid = ($new_balance <= 0.01);
                if ($is_all_paid) {
                    $message .= " | Bill FULLY PAID! 🎉";
                } else {
                    $message .= " | Remaining: " . $currency . " " . number_format($new_balance, 0);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_number' => $receipt_number,
                    'amount_paid' => $amount_to_pay,
                    'new_balance' => $new_balance,
                    'new_status' => $new_status,
                    'is_paid' => $is_all_paid,
                    'is_partial' => !$is_all_paid,
                    'visit_id' => $visit_id
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
    // GET BILL
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            b.*, b.subtotal, b.total_amount, b.paid_amount, b.balance,
            b.discount_amount, b.pharmacy_discount, b.cashier_discount, b.total_discount,
            b.premium_amount, b.premium_note, b.patient_id as patient_id_ref,
            b.visit_id,
            v.visit_number, v.visit_type, v.visit_date, v.status as visit_status,
            v.payment_status as visit_payment_status,
            u.full_name as doctor_name,
            p.full_name as patient_name, p.patient_id as patient_number,
            p.phone, p.email, p.gender, p.date_of_birth, p.address, p.blood_group,
            b.created_at as bill_created, b.updated_at as bill_updated
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE b.id = ? AND b.branch_id = ? AND b.status != 'cancelled'
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: partial_payments.php?error=bill_not_found');
        exit;
    }

    $subtotal = (float)($bill['subtotal'] ?? 0);
    $paid_amount = (float)($bill['paid_amount'] ?? 0);
    
    $pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0) {
        $pharmacy_discount = (float)($bill['discount_amount'] ?? 0);
    }
    $cashier_discount = (float)($bill['cashier_discount'] ?? 0);
    $total_discount = $pharmacy_discount + $cashier_discount;
    
    $existing_premium = (float)($bill['premium_amount'] ?? 0);
    $existing_premium_note = $bill['premium_note'] ?? '';
    
    $remaining_balance = $subtotal + $existing_premium - $total_discount - $paid_amount;
    if ($remaining_balance < 0) $remaining_balance = 0;
    
    // ✅ AUTO-REPAIR
    $visit_id_ref = (int)($bill['visit_id'] ?? 0);
    if ($visit_id_ref > 0) {
        updateVisitPaymentStatus($db, $visit_id_ref, $user_branch_id);
        updateVisitCompletionStatus($db, $visit_id_ref, $user_branch_id);
    }
    
    $other_bills_premium = 0;
    $other_bills_premium_details = [];
    $patient_id_ref = $bill['patient_id_ref'] ?? $bill['patient_id'];
    
    try {
        $stmt_other = $db->prepare("
            SELECT id, bill_number, premium_amount, premium_note, subtotal, paid_amount, balance, status
            FROM bills 
            WHERE patient_id = ? AND branch_id = ? AND id != ? AND status != 'cancelled' AND premium_amount > 0
            ORDER BY created_at ASC
        ");
        $stmt_other->execute([$patient_id_ref, $user_branch_id, $bill_id]);
        $other_premium_bills = $stmt_other->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($other_premium_bills as $ob) {
            $op = (float)($ob['premium_amount'] ?? 0);
            if ($op > 0) {
                $other_bills_premium += $op;
                $other_bills_premium_details[] = [
                    'bill_number' => $ob['bill_number'],
                    'premium' => $op,
                    'note' => $ob['premium_note'] ?? ''
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Other bills premium error: " . $e->getMessage());
    }
    
    $is_locked = false;

    $stmt = $db->prepare("
        SELECT bi.*,
            (SELECT status FROM prescriptions WHERE id = bi.reference_id AND reference_type = 'prescription') as prescription_status
        FROM bill_items bi
        WHERE bi.bill_id = ? AND bi.status != 'cancelled'
        ORDER BY bi.status DESC, bi.item_type ASC, bi.created_at ASC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bill_status = $bill['status'] ?? 'pending';
    $is_paid = ($remaining_balance <= 0.01);
    $is_partial = ($remaining_balance > 0 && $paid_amount > 0);
    $is_pending = ($paid_amount == 0 && $remaining_balance > 0);
    
    $pending_items = 0;
    $paid_items = 0;
    $partial_items = 0;
    $medication_items = 0;
    $unconfirmed_medication = 0;
    
    foreach ($bill_items as $item) {
        if ($item['status'] === 'paid') $paid_items++;
        elseif ($item['status'] === 'partial') $partial_items++;
        elseif ($item['status'] === 'pending') $pending_items++;
        
        if ($item['item_type'] === 'medication') {
            $medication_items++;
            if ($item['prescription_status'] !== 'dispensed' && $item['prescription_status'] !== 'confirmed') {
                $unconfirmed_medication++;
            }
        }
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bill = null;
    $bill_items = [];
    $subtotal = 0;
    $paid_amount = 0;
    $remaining_balance = 0;
    $total_discount = 0;
    $pharmacy_discount = 0;
    $cashier_discount = 0;
    $existing_premium = 0;
    $existing_premium_note = '';
    $other_bills_premium = 0;
    $other_bills_premium_details = [];
    $is_locked = false;
    $bill_status = 'pending';
    $is_paid = false;
    $is_partial = false;
    $is_pending = true;
    $pending_items = 0;
    $paid_items = 0;
    $partial_items = 0;
    $medication_items = 0;
    $unconfirmed_medication = 0;
    $currency = 'TSh';
    $visit_id_ref = 0;
    error_log("Make payment error: " . $e->getMessage());
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
    <title>Make Payment - Braick Dispensary</title>
    
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
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --gold: #D97706;
            --gold-bg: #FEF3C7;
            --gold-dark: #B45309;
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-header-bg: #059669;
            --table-header-text: #FFFFFF;
            --table-stripe: #D1FAE5;
            --table-hover: #A7F3D0;
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
            --locked-color: #DC2626;
            --locked-bg: #FEE2E2;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
            --table-header-bg: #047857;
            --table-stripe: #1A3A2A;
            --table-hover: #065F46;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --warning-bg: #3D2E0A;
            --gold-bg: #3D2E0A;
            --locked-bg: #3A1A1A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            max-width: 1400px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--page-header-bg-from), var(--page-header-bg-to));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px var(--page-header-shadow);
            position: relative;
            overflow: hidden;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
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
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* BILL CARD */
        .bill-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            margin: 0 auto 20px;
            max-width: 1100px;
            width: 100%;
        }
        
        .bill-card .card-header {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .bill-card .card-body { padding: 20px 24px; }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
            margin-bottom: 16px;
            padding: 16px 20px;
            background: var(--primary-bg);
            border-radius: 12px;
            border: 1px solid var(--success-light);
        }
        
        .patient-info-grid .info-item span:first-child {
            display: block;
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .patient-info-grid .info-item span:last-child {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .bill-summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .bill-summary-card .label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            display: block;
        }
        
        .bill-summary-card .value {
            font-size: 1.1rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
            font-family: var(--font-mono);
            font-variant-numeric: tabular-nums;
        }
        
        .bill-summary-card.total .value { color: var(--primary); }
        .bill-summary-card.discount .value { color: var(--warning); }
        .bill-summary-card.premium .value { color: var(--gold); }
        .bill-summary-card.paid .value { color: var(--success); }
        .bill-summary-card.balance .value { color: var(--danger); }
        .bill-summary-card.balance.zero .value { color: var(--success); }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 600px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            background: var(--table-header-bg);
            color: var(--table-header-text);
            white-space: nowrap;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-family: var(--font-mono);
            font-variant-numeric: tabular-nums;
        }
        
        .data-table tbody tr:nth-child(even) { background: var(--table-stripe); }
        .data-table tbody tr:hover td { background: var(--table-hover); }
        
        .data-table tbody tr.locked-item td {
            background: var(--locked-bg) !important;
            opacity: 0.85;
        }
        
        /* ================================================================
           ✅ PAYMENT CONTROLS CARD - IMPROVED
           ✅ Width inalingana na table (max-width 1100px)
           ✅ Height imeongezwa kidogo
           ✅ Centered, haigusi sidebar
           ================================================================ */
        .payment-controls-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--success);
            overflow: hidden;
            margin: 20px auto 0;
            max-width: 1100px;
            width: 100%;
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.18);
            position: sticky;
            bottom: 12px;
            z-index: 20;
            transition: all 0.3s ease;
        }
        
        .payment-controls-card:hover {
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.25);
        }
        
        .payment-controls-card .pc-header {
            background: linear-gradient(135deg, #059669, #047857);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            color: white;
            min-height: 62px;
        }
        
        .payment-controls-card .pc-header > div:first-child {
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-controls-card .pc-body {
            padding: 20px 28px;
        }
        
        .payment-inputs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }
        
        .payment-input-box {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            min-height: 95px;
        }
        
        .payment-input-box:hover {
            border-color: var(--success-light);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.08);
        }
        
        .payment-input-box > div:first-child {
            font-size: 0.62rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.04em;
        }
        
        .payment-input-box select,
        .payment-input-box input[type="text"] {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            height: 40px;
            font-family: var(--font-mono);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .payment-input-box select:focus,
        .payment-input-box input[type="text"]:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
            outline: none;
        }
        
        .payment-input-box select {
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        
        .payment-input-box input#premiumNote {
            margin-top: 6px;
            font-size: 0.7rem;
            height: 32px;
            padding: 6px 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }
        
        .payment-buttons-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            padding-top: 18px;
            border-top: 2px dashed var(--border-color);
            margin-top: 4px;
        }
        
        .payment-buttons-row .btn {
            padding: 12px 28px;
            font-size: 0.88rem;
            border-radius: 10px;
            min-height: 46px;
            letter-spacing: 0.02em;
            box-shadow: 0 3px 10px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            color: white;
        }
        
        .payment-buttons-row .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(0,0,0,0.18);
        }
        
        .payment-buttons-row .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }
        
        /* ✅ Remaining Badge - Improved */
        .payment-controls-card .pc-header .remaining-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.18);
            padding: 8px 20px;
            border-radius: 22px;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(6px);
        }
        
        .payment-controls-card .pc-header .remaining-badge .label {
            font-size: 0.58rem;
            font-weight: 700;
            color: rgba(255,255,255,0.75);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        
        .payment-controls-card .pc-header .remaining-badge .value {
            font-size: 1.05rem;
            font-weight: 800;
            font-family: var(--font-mono);
            color: #FCD34D;
            letter-spacing: -0.02em;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .toast-custom {
            position: fixed;
            bottom: 80px;
            right: 24px;
            padding: 12px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* ================================================================
           ✅ RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .payment-controls-card {
                max-width: 100%;
                margin-left: 0;
                margin-right: 0;
            }
        }
        
        @media (max-width: 768px) {
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .payment-inputs-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .patient-info-grid { grid-template-columns: 1fr; }
            .payment-controls-card .pc-header { padding: 14px 18px; }
            .payment-controls-card .pc-body { padding: 16px 18px; }
            .payment-buttons-row .btn {
                padding: 11px 20px;
                font-size: 0.8rem;
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .payment-inputs-grid { grid-template-columns: 1fr; }
            .payment-input-box { min-height: auto; }
            .payment-controls-card .pc-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .payment-controls-card .pc-header .remaining-badge {
                width: 100%;
                justify-content: center;
            }
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
                Make Payment
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($existing_premium > 0): ?>
                    <span class="header-badge premium-badge-header">
                        <i class="fas fa-crown"></i> Premium: <?= $currency ?> <?= number_format($existing_premium, 0) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Complete payment for bill
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill"></i> Remaining: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="partial_payments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- BILL CARD -->
    <div class="bill-card">
        <div class="card-header">
            <div>
                <span class="bill-number" style="font-weight:700;font-family:var(--font-mono);">
                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                <span style="font-size:0.7rem;opacity:0.8;margin-left:12px;">
                    <?= date('d/m/Y H:i', strtotime($bill['bill_created'] ?? 'now')) ?>
                </span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="padding:4px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.2);">
                    <?= ucfirst($bill_status) ?>
                </span>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Patient Info -->
            <div class="patient-info-grid">
                <div class="info-item"><span>Patient Name</span><span><strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong></span></div>
                <div class="info-item"><span>Patient ID</span><span><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Phone</span><span><?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Gender</span><span><?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Doctor</span><span>Dr. <?= htmlspecialchars($bill['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div class="info-item"><span>Visit Type</span><span><?= ucfirst($bill['visit_type'] ?? 'N/A') ?></span></div>
            </div>
            
            <!-- 5 SUMMARY CARDS -->
            <div class="bill-summary-grid">
                <div class="bill-summary-card total">
                    <span class="label">📋 Subtotal</span>
                    <span class="value" id="totalAmountDisplay"><?= $currency ?> <?= number_format($subtotal, 0) ?></span>
                    <span style="font-size:0.5rem;color:var(--text-secondary);display:block;margin-top:1px;">Bill amount</span>
                </div>
                
                <div class="bill-summary-card discount">
                    <span class="label">🏷️ Discount</span>
                    <span class="value" id="discountDisplay"><?= $currency ?> <?= number_format($total_discount, 0) ?></span>
                    <span style="font-size:0.5rem;color:var(--text-secondary);display:block;margin-top:1px;" id="discountBreakdown">
                        Pharm: <?= $currency ?> <?= number_format($pharmacy_discount, 0) ?>
                    </span>
                </div>
                
                <div class="bill-summary-card premium">
                    <span class="label">👑 Premium</span>
                    <span class="value" id="premiumDisplay"><?= $currency ?> <?= number_format($existing_premium, 0) ?></span>
                    <span style="font-size:0.5rem;color:var(--text-secondary);display:block;margin-top:1px;" id="premiumBreakdown">
                        <?= !empty($existing_premium_note) ? htmlspecialchars($existing_premium_note) : 'No premium' ?>
                    </span>
                </div>
                
                <div class="bill-summary-card paid">
                    <span class="label">✅ Paid Amount</span>
                    <span class="value" id="paidAmountDisplay"><?= $currency ?> <?= number_format($paid_amount, 0) ?></span>
                    <span style="font-size:0.5rem;color:var(--text-secondary);display:block;margin-top:1px;" id="paidStatus">
                        <?php if ($paid_amount > 0): ?>Partial<?php else: ?>No payments<?php endif; ?>
                    </span>
                </div>
                
                <div class="bill-summary-card balance <?= $remaining_balance <= 0 ? 'zero' : '' ?>">
                    <span class="label">📊 Remaining</span>
                    <span class="value" id="balanceDisplay"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                    <span style="font-size:0.5rem;color:var(--text-secondary);display:block;margin-top:1px;" id="balanceStatus">
                        <?= $remaining_balance <= 0 ? '✅ Fully paid!' : 'Pending' ?>
                    </span>
                </div>
            </div>
            
            <!-- Bill Items Table -->
            <h4 style="font-size:0.85rem;font-weight:600;margin-top:16px;margin-bottom:10px;">
                <i class="fas fa-list" style="color:var(--primary);"></i> Bill Items
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(<?= count($bill_items) ?> items)</span>
            </h4>
            
            <div style="overflow-x:auto;border-radius:12px;border:1px solid var(--border-color);">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th style="width:30%;">Item Name</th>
                            <th style="width:12%;">Type</th>
                            <th style="width:8%;text-align:center;">Qty</th>
                            <th style="width:15%;text-align:right;">Unit Price</th>
                            <th style="width:15%;text-align:right;">Total</th>
                            <th style="width:15%;text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bill_items) > 0): ?>
                            <?php $i = 1; foreach ($bill_items as $item): 
                                $is_item_paid = ($item['status'] === 'paid');
                                $is_item_partial = ($item['status'] === 'partial');
                                $is_medication = ($item['item_type'] === 'medication');
                                $pres_status = $item['prescription_status'] ?? 'pending';
                                $is_item_locked = ($is_medication && $item['reference_type'] === 'prescription' && $pres_status !== 'confirmed' && $pres_status !== 'dispensed');
                                $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                $unit_price = (float)($item['unit_price'] ?? 0);
                                $qty = (int)($item['quantity'] ?? 1);
                            ?>
                            <tr class="<?= $is_item_paid ? 'paid-item' : '' ?> <?= $is_item_locked ? 'locked-item' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                    <?php if ($is_item_locked): ?>
                                        <div style="font-size:0.5rem;color:var(--locked-color);margin-top:2px;">
                                            <i class="fas fa-lock"></i> Waiting pharmacy
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.55rem;background:var(--bg-body);padding:1px 8px;border-radius:4px;border:1px solid var(--border-color);">
                                        <?= ucfirst($item['item_type'] ?? 'item') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= $qty ?></td>
                                <td style="text-align:right;"><?= $currency ?> <?= number_format($unit_price, 0) ?></td>
                                <td style="text-align:right;font-weight:600;<?= $is_item_paid ? 'color:var(--success);' : ($is_item_partial ? 'color:var(--warning);' : 'color:var(--danger);') ?>">
                                    <?= $currency ?> <?= number_format($price, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_item_locked): ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--locked-bg);color:var(--locked-color);border:1px solid var(--locked-color);">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    <?php elseif ($is_item_paid): ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--success-bg);color:var(--success);">✅ Paid</span>
                                    <?php elseif ($is_item_partial): ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--warning-bg);color:var(--warning);">🔄 Partial</span>
                                    <?php else: ?>
                                        <span style="font-size:0.55rem;font-weight:600;padding:3px 12px;border-radius:12px;background:var(--warning-bg);color:var(--warning);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-secondary);">No items found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Info Box: Other Bills Premium -->
            <?php if ($other_bills_premium > 0): ?>
            <div style="background:var(--gold-bg);border:2px solid var(--gold);border-radius:12px;padding:12px 16px;margin-top:12px;">
                <div style="font-size:0.75rem;font-weight:700;color:var(--gold);margin-bottom:6px;">
                    <i class="fas fa-info-circle"></i> Premium from Other Bills (INFO ONLY)
                </div>
                <div style="font-size:0.7rem;color:var(--text-secondary);line-height:1.6;">
                    <strong>This bill's premium:</strong> <?= $currency ?> <?= number_format($existing_premium, 0) ?><br>
                    <strong>Other bills premium:</strong> <?= $currency ?> <?= number_format($other_bills_premium, 0) ?><br>
                    <span style="font-size:0.65rem;">ℹ️ Premium kutoka bills zingine HAIJUMUISHWI kwenye bill hii.</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAYMENT CONTROLS CARD - IMPROVED -->
    <?php if (!$is_paid && $remaining_balance > 0): ?>
    <div class="payment-controls-card" id="paymentControls">
        <!-- ✅ HEADER - IMPROVED -->
        <div class="pc-header">
            <div>
                <i class="fas fa-cash-register"></i> 
                Payment Controls
            </div>
            <div class="remaining-badge">
                <span class="label">Remaining</span>
                <span class="value" id="displayBalanceGrand"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
            </div>
        </div>
        
        <!-- ✅ BODY - IMPROVED -->
        <div class="pc-body">
            <div class="payment-inputs-grid">
                <!-- Payment Method -->
                <div class="payment-input-box">
                    <div>
                        <i class="fas fa-hand-holding-usd" style="color:var(--success);"></i> 
                        Payment Method
                    </div>
                    <select id="paymentMethod">
                        <option value="cash">💰 Cash</option>
                        <option value="m-pesa">📱 M-Pesa</option>
                        <option value="airtel_money">📱 Airtel Money</option>
                        <option value="tigo_pesa">📱 Tigo Pesa</option>
                        <option value="halopesa">📱 HaloPesa</option>
                        <option value="card">💳 Card</option>
                        <option value="bank">🏦 Bank Transfer</option>
                        <option value="insurance">🏥 Insurance</option>
                    </select>
                </div>
                
                <!-- Discount -->
                <div class="payment-input-box">
                    <div>
                        <i class="fas fa-percent" style="color:var(--warning);"></i> 
                        Add Discount
                    </div>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:0.7rem;color:var(--text-secondary);font-weight:700;font-family:var(--font-mono);pointer-events:none;z-index:2;"><?= $currency ?></span>
                        <input type="text" id="discountAmount" placeholder="0" value="0" 
                               style="padding-left:52px;text-align:right;" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                </div>
                
                <!-- Premium -->
                <div class="payment-input-box">
                    <div>
                        <i class="fas fa-crown" style="color:var(--gold);"></i> 
                        Add Premium
                    </div>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:0.7rem;color:var(--text-secondary);font-weight:700;font-family:var(--font-mono);pointer-events:none;z-index:2;"><?= $currency ?></span>
                        <input type="text" id="premiumAmount" placeholder="0" value="0" 
                               style="padding-left:52px;text-align:right;" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                    <input type="text" id="premiumNote" placeholder="Note (optional)" 
                           oninput="updateTotals();">
                </div>
                
                <!-- Partial -->
                <div class="payment-input-box">
                    <div>
                        <i class="fas fa-hand-holding-heart" style="color:var(--primary);"></i> 
                        Partial Payment
                    </div>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:0.7rem;color:var(--text-secondary);font-weight:700;font-family:var(--font-mono);pointer-events:none;z-index:2;"><?= $currency ?></span>
                        <input type="text" id="partialAmount" placeholder="Enter Amount" value="0" 
                               style="padding-left:52px;text-align:right;" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                </div>
            </div>
            
            <!-- ✅ BUTTONS - IMPROVED -->
            <div class="payment-buttons-row">
                <div style="flex:1;display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
                    <button onclick="processPayment('partial')" class="btn btn-warning" id="partialPayBtn">
                        <i class="fas fa-hand-holding-heart"></i> PAY PARTIAL
                    </button>
                    <button onclick="processPayment('full')" class="btn btn-success" id="fullPayBtn">
                        <i class="fas fa-check-circle"></i> PAY FULL (<?= $currency ?> <?= number_format($remaining_balance, 0) ?>)
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span style="color:var(--success);font-weight:600;">Braick Dispensary</span> Management System
            <span>|</span> Make Payment
            <span>|</span>
            <span>👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span>|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var billId = <?= $bill_id ?>;
    var patientId = <?= $bill['patient_id_ref'] ?? $bill['patient_id'] ?? 0 ?>;
    var subtotal = <?= $subtotal ?>;
    var paidAmount = <?= $paid_amount ?>;
    var balance = <?= $remaining_balance ?>;
    var totalDiscount = <?= $total_discount ?>;
    var pharmacyDiscount = <?= $pharmacy_discount ?>;
    var cashierDiscount = <?= $cashier_discount ?>;
    var existingPremium = <?= $existing_premium ?>;
    var otherBillsPremium = <?= $other_bills_premium ?>;
    var existingPremiumNote = '<?= addslashes($existing_premium_note) ?>';
    var currency = '<?= $currency ?>';
    var isPaid = <?= $is_paid ? 'true' : 'false' ?>;
    var visitId = <?= $visit_id_ref ?? 0 ?>;

    console.log('💰 Make Payment v7.0');
    console.log('✅ Visit Status Auto-Update Enabled');
    console.log('📋 Bill ID: ' + billId + ' | Visit ID: ' + visitId);

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
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDarkMode();
        });
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

    function updateTotals() {
        if (isPaid) return;
        
        var discountInput = document.getElementById('discountAmount');
        var premiumInput = document.getElementById('premiumAmount');
        var partialInput = document.getElementById('partialAmount');
        
        var newDiscount = getRawValue(discountInput);
        var newPremium = getRawValue(premiumInput);
        var partial = getRawValue(partialInput);
        
        var totalPremium = existingPremium + newPremium;
        var totalDiscountVal = totalDiscount + newDiscount;
        
        var remainingBalance = subtotal + totalPremium - totalDiscountVal - paidAmount;
        if (remainingBalance < 0) remainingBalance = 0;
        
        var amountToPay = remainingBalance;
        if (partial > 0 && partial < remainingBalance) {
            amountToPay = partial;
        } else if (partial > 0 && partial >= remainingBalance) {
            amountToPay = remainingBalance;
        }
        
        document.getElementById('totalAmountDisplay').textContent = currency + ' ' + subtotal.toFixed(0);
        document.getElementById('discountDisplay').textContent = currency + ' ' + totalDiscountVal.toFixed(0);
        document.getElementById('premiumDisplay').textContent = currency + ' ' + totalPremium.toFixed(0);
        document.getElementById('paidAmountDisplay').textContent = currency + ' ' + paidAmount.toFixed(0);
        document.getElementById('balanceDisplay').textContent = currency + ' ' + remainingBalance.toFixed(0);
        document.getElementById('displayBalanceGrand').textContent = currency + ' ' + remainingBalance.toFixed(0);
        
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        
        if (remainingBalance <= 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> Already Paid';
            partialBtn.disabled = true;
        } else {
            fullBtn.disabled = false;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> PAY FULL (' + currency + ' ' + remainingBalance.toFixed(0) + ')';
            
            if (partial > 0 && partial <= remainingBalance) {
                partialBtn.disabled = false;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL (' + currency + ' ' + amountToPay.toFixed(0) + ')';
            } else {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Enter Partial Amount';
            }
        }
    }

    function processPayment(type) {
        if (isPaid) {
            showToast('✅ Already Paid', 'Bill is fully paid', 'info');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var newDiscount = getRawValue(document.getElementById('discountAmount'));
        var newPremium = getRawValue(document.getElementById('premiumAmount'));
        var newPremiumNote = document.getElementById('premiumNote') ? document.getElementById('premiumNote').value.trim() : '';
        var partialAmount = getRawValue(document.getElementById('partialAmount'));
        
        var totalPremium = existingPremium + newPremium;
        var totalDiscountVal = totalDiscount + newDiscount;
        var remainingBalance = subtotal + totalPremium - totalDiscountVal - paidAmount;
        if (remainingBalance < 0) remainingBalance = 0;
        
        var amountToPay = remainingBalance;
        
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return;
            }
            if (partialAmount > remainingBalance) {
                showToast('⚠️ Amount Exceeds', 'Partial amount exceeds remaining balance', 'warning');
                return;
            }
            amountToPay = partialAmount;
        }
        
        if (amountToPay <= 0) {
            showToast('⚠️ Invalid Amount', 'Amount to pay must be greater than 0', 'warning');
            return;
        }
        
        var confirmMsg = '💳 ' + (type === 'partial' ? 'PARTIAL' : 'FULL') + ' PAYMENT\n' +
                         '═══════════════════════\n' +
                         'Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>\n' +
                         'Subtotal: ' + currency + ' ' + subtotal.toFixed(0) + '\n' +
                         'Premium: ' + currency + ' ' + totalPremium.toFixed(0) + '\n' +
                         'Discount: ' + currency + ' ' + totalDiscountVal.toFixed(0) + '\n' +
                         'Already Paid: ' + currency + ' ' + paidAmount.toFixed(0) + '\n' +
                         '───────────────────────\n' +
                         'Amount to Pay: ' + currency + ' ' + amountToPay.toFixed(0) + '\n' +
                         'Method: ' + paymentMethod.toUpperCase() + '\n\n' +
                         'Confirm payment?';
        
        if (!confirm(confirmMsg)) return;
        
        var btn = type === 'partial' ? document.getElementById('partialPayBtn') : document.getElementById('fullPayBtn');
        var originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        
        var formData = new FormData();
        formData.append('action', 'make_payment');
        formData.append('bill_id', billId);
        formData.append('payment_method', paymentMethod);
        formData.append('payment_type', type);
        if (newDiscount > 0) formData.append('discount_amount', newDiscount);
        if (newPremium > 0) {
            formData.append('premium_amount', newPremium);
            if (newPremiumNote) formData.append('premium_note', newPremiumNote);
        }
        formData.append('partial_amount', amountToPay);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                if (data.is_paid) {
                    setTimeout(function() { window.location.href = 'partial_payments.php?success=paid'; }, 2000);
                } else {
                    setTimeout(function() { window.location.reload(); }, 1500);
                }
            } else {
                if (data.locked) {
                    showToast('🔒 LOCKED', data.message, 'error');
                } else {
                    showToast('❌ Error', data.message, 'error');
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
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
        }, 6000);
    }

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }

    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('DOMContentLoaded', function() {
        updateTotals();
        console.log('✅ Make Payment v7.0 loaded');
        console.log('✅ Payment card improved');
        console.log('✅ Visit status auto-update enabled');
    });
</script>

</body>
</html>