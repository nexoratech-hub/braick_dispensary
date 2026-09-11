<?php
// ================================================================
// FILE: frontend/pages/cashier/make_payment.php
// CASHIER - MAKE PAYMENT - FULLY FIXED v4.0
// ================================================================
// ✅ Formula: remaining = subtotal 
//            + premium_amount(bill hii pekee) 
//            + premium_new(cashier) 
//            - total_discount 
//            - paid_amount
// ================================================================
// ✅ 5 Cards: Subtotal | Discount | Premium | Paid | Remaining
// ✅ Payment Controls: Method | Discount | Premium | Partial
// ✅ Other bills premium = INFO ONLY (not in calculation)
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

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

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
                
                // ============================================================
                // GET CURRENT VALUES
                // ============================================================
                $subtotal = (float)($current_bill['subtotal'] ?? 0);
                $paid_amount = (float)($current_bill['paid_amount'] ?? 0);
                $existing_premium = (float)($current_bill['premium_amount'] ?? 0);
                $existing_premium_note = $current_bill['premium_note'] ?? '';
                
                // Get pharmacy & cashier discount
                $existing_pharmacy_discount = (float)($current_bill['pharmacy_discount'] ?? 0);
                if ($existing_pharmacy_discount == 0) {
                    $existing_pharmacy_discount = (float)($current_bill['discount_amount'] ?? 0);
                }
                $existing_cashier_discount = (float)($current_bill['cashier_discount'] ?? 0);
                $existing_total_discount = $existing_pharmacy_discount + $existing_cashier_discount;
                
                $new_premium = max(0, $premium_amount);
                $new_discount = max(0, $discount_amount);
                
                // ============================================================
                // ✅ TOTAL PREMIUM = existing (bill hii) + new (cashier)
                //    HAIJUMUISHI premium kutoka bills zingine
                // ============================================================
                $total_premium_all = $existing_premium + $new_premium;
                
                // ============================================================
                // ✅ TOTAL DISCOUNT
                // ============================================================
                $updated_cashier_discount = $existing_cashier_discount + $new_discount;
                $updated_total_discount = $existing_pharmacy_discount + $updated_cashier_discount;
                
                // ============================================================
                // ✅ FORMULA: remaining = subtotal + premium(bill) - discount - paid
                // ============================================================
                $remaining_balance = $subtotal + $total_premium_all - $updated_total_discount - $paid_amount;
                if ($remaining_balance < 0) $remaining_balance = 0;
                
                // ============================================================
                // PAYMENT CALCULATION
                // ============================================================
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
                
                // ============================================================
                // ✅ NEW TOTAL AMOUNT = subtotal + total_premium - total_discount
                // ============================================================
                $new_total_amount = $subtotal + $total_premium_all - $updated_total_discount;
                if ($new_total_amount < 0) $new_total_amount = 0;
                
                // ============================================================
                // ✅ NEW BALANCE = new_total_amount - new_paid
                // ============================================================
                $new_balance = $new_total_amount - $new_paid;
                if ($new_balance < 0) $new_balance = 0;
                
                // Build premium note
                $premium_note_final = $existing_premium_note;
                if (!empty($premium_note) && $new_premium > 0) {
                    if (!empty($premium_note_final)) {
                        $premium_note_final .= ', ' . $premium_note;
                    } else {
                        $premium_note_final = $premium_note;
                    }
                }
                
                // Determine status
                if ($new_balance <= 0.01) {
                    $new_status = 'paid';
                } elseif ($new_paid > 0 && $new_balance > 0) {
                    $new_status = 'partial';
                } else {
                    $new_status = $current_bill['status'] ?? 'pending';
                }
                
                // ============================================================
                // UPDATE BILL
                // ============================================================
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
                
                // ============================================================
                // UPDATE BILL ITEMS
                // ============================================================
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
                
                // ============================================================
                // INSERT PAYMENT RECORD
                // ============================================================
                $notes = 'Payment | Pharm Disc: ' . $currency . ' ' . number_format($existing_pharmacy_discount, 0) . 
                         ' | Cashier Disc: ' . $currency . ' ' . number_format($new_discount, 0);
                if ($total_premium_all > 0) {
                    $notes .= ' | Premium: ' . $currency . ' ' . number_format($total_premium_all, 0);
                    if ($new_premium > 0) {
                        $notes .= ' (New: +' . number_format($new_premium, 0) . ')';
                    }
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
                
                $db->commit();
                
                $message = "Payment successful!";
                $message .= " Amount: " . $currency . " " . number_format($amount_to_pay, 0);
                if ($new_premium > 0) {
                    $message .= " | 👑 New Premium: " . $currency . " " . number_format($new_premium, 0);
                }
                if ($new_discount > 0) {
                    $message .= " | New Discount: " . $currency . " " . number_format($new_discount, 0);
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
                    'cashier_discount' => $updated_cashier_discount,
                    'total_discount' => $updated_total_discount,
                    'premium_amount' => $total_premium_all,
                    'premium_note' => $premium_note_final,
                    'new_balance' => $new_balance,
                    'new_status' => $new_status,
                    'is_paid' => $is_all_paid,
                    'is_partial' => !$is_all_paid,
                    'updated_totals' => [
                        'subtotal' => $subtotal,
                        'premium_bill' => $existing_premium,
                        'premium_new' => $new_premium,
                        'premium_total' => $total_premium_all,
                        'total_amount' => $new_total_amount,
                        'total_paid' => $new_paid,
                        'total_balance' => $new_balance,
                        'total_discount' => $updated_total_discount,
                        'cashier_discount' => $updated_cashier_discount
                    ]
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
    // GET ONLY THIS BILL
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            b.*,
            b.subtotal,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.discount_amount,
            b.pharmacy_discount,
            b.cashier_discount,
            b.total_discount,
            b.premium_amount,
            b.premium_note,
            b.patient_id as patient_id_ref,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            v.status as visit_status,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone,
            p.email,
            p.gender,
            p.date_of_birth,
            p.address,
            p.blood_group,
            p.allergies,
            p.emergency_contact,
            b.created_at as bill_created,
            b.updated_at as bill_updated
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

    // ================================================================
    // GET BILL VALUES - PREMIUM BILL HII PEKEE
    // ================================================================
    $subtotal = (float)($bill['subtotal'] ?? 0);
    $paid_amount = (float)($bill['paid_amount'] ?? 0);
    
    // Get pharmacy discount correctly
    $pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0) {
        $pharmacy_discount = (float)($bill['discount_amount'] ?? 0);
    }
    $cashier_discount = (float)($bill['cashier_discount'] ?? 0);
    $discount_amount = $pharmacy_discount;
    $total_discount = $pharmacy_discount + $cashier_discount;
    
    $existing_premium = (float)($bill['premium_amount'] ?? 0);
    $existing_premium_note = $bill['premium_note'] ?? '';
    
    // ================================================================
    // ✅ FORMULA: remaining = subtotal + premium(bill hii) - discount - paid
    // ================================================================
    $remaining_balance = $subtotal + $existing_premium - $total_discount - $paid_amount;
    if ($remaining_balance < 0) $remaining_balance = 0;
    
    // ================================================================
    // ✅ GET OTHER BILLS PREMIUM - FOR INFO ONLY
    // ================================================================
    $other_bills_premium = 0;
    $other_bills_premium_details = [];
    $patient_id_ref = $bill['patient_id_ref'] ?? $bill['patient_id'];
    
    try {
        $stmt_other = $db->prepare("
            SELECT id, bill_number, premium_amount, premium_note, 
                   subtotal, paid_amount, balance, status
            FROM bills 
            WHERE patient_id = ? 
              AND branch_id = ? 
              AND id != ?
              AND status != 'cancelled'
              AND premium_amount > 0
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
                    'note' => $ob['premium_note'] ?? '',
                    'status' => $ob['status'] ?? 'pending'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Other bills premium error: " . $e->getMessage());
    }
    
    $is_locked = false;

    $stmt = $db->prepare("
        SELECT 
            bi.*,
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
        if ($item['status'] === 'paid') {
            $paid_items++;
        } elseif ($item['status'] === 'partial') {
            $partial_items++;
        } elseif ($item['status'] === 'pending') {
            $pending_items++;
        }
        
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
    $discount_amount = 0;
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
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --primary-gradient: linear-gradient(135deg, #059669 0%, #047857 100%);
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
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
            --table-header-text: #FFFFFF;
            --table-stripe: #1A3A2A;
            --table-hover: #065F46;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --warning-bg: #3D2E0A;
            --gold-bg: #3D2E0A;
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
            transition: background 0.3s ease;
        }
        
        .page-header {
            background: var(--primary-gradient);
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
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
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
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; color: rgba(255,255,255,0.9); }
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
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
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
        .page-header .header-badge.premium-badge {
            background: rgba(251,191,36,0.3);
            border-color: rgba(251,191,36,0.4);
            color: #FCD34D;
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
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .bill-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .bill-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        .bill-card .card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .bill-card .card-header .bill-number {
            font-weight: 700;
            font-family: monospace;
            font-size: 1rem;
            color: white;
        }
        .bill-card .card-header .bill-status {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .bill-card .card-header .bill-status.paid { background: rgba(5,150,105,0.4); border-color: #34D399; }
        .bill-card .card-header .bill-status.partial { background: rgba(217,119,6,0.4); border-color: #FBBF24; }
        .bill-card .card-header .bill-status.pending { background: rgba(11,94,215,0.4); border-color: #60A5FA; }
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
            letter-spacing: 0.05em;
        }
        .patient-info-grid .info-item span:last-child {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        /* 5 SUMMARY CARDS */
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
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .bill-summary-card .label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        .bill-summary-card .value {
            font-size: 1.1rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
        }
        .bill-summary-card .sub-text {
            font-size: 0.5rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 1px;
        }
        .bill-summary-card.total .value { color: var(--primary); }
        .bill-summary-card.discount .value { color: var(--warning); }
        .bill-summary-card.premium .value { color: var(--gold); }
        .bill-summary-card.paid .value { color: var(--success); }
        .bill-summary-card.balance .value { color: var(--danger); }
        .bill-summary-card.balance.zero .value { color: var(--success); }
        
        .table-wrap {
            overflow-x: auto;
            margin-top: 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
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
            letter-spacing: 0.05em;
            background: var(--table-header-bg);
            color: var(--table-header-text);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .data-table thead th i { margin-right: 6px; opacity: 0.8; color: rgba(255,255,255,0.8); }
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .data-table tbody tr:nth-child(even) { background: var(--table-stripe); }
        .data-table tbody tr:hover td { background: var(--table-hover); }
        .data-table tbody tr.paid-item td { opacity: 0.7; }
        .data-table tbody tr.paid-item td .item-status { color: var(--success); }
        .data-table tbody tr.paid-item td .item-price { color: var(--success); }
        
        .data-table tfoot tr {
            background: var(--primary-gradient);
            color: white;
            font-weight: 700;
        }
        .data-table tfoot td {
            padding: 10px 14px;
            color: white !important;
            border-top: 3px solid var(--primary-dark);
        }
        .data-table tfoot td i { color: rgba(255,255,255,0.8); }
        .data-table tfoot td .total-amount { color: #FCD34D; }
        
        .item-status {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        .item-status.paid { background: var(--success-bg); color: var(--success); }
        .item-status.pending { background: var(--warning-bg); color: var(--warning); }
        .item-status.partial { background: var(--primary-bg); color: var(--primary); }
        
        /* PAYMENT CONTROLS CARD */
        .payment-controls-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--success);
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.15);
            position: sticky;
            bottom: 10px;
            z-index: 20;
        }
        .payment-controls-card .pc-header {
            background: var(--primary-gradient);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            color: white;
        }
        .payment-controls-card .pc-header .pc-title {
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        .payment-controls-card .pc-header .pc-total-display {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.15);
            padding: 6px 16px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .payment-controls-card .pc-header .pc-total-display .pc-total-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 8px;
        }
        .payment-controls-card .pc-header .pc-total-display .pc-total-item .label {
            font-size: 0.5rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .payment-controls-card .pc-header .pc-total-display .pc-total-item .value {
            font-size: 0.95rem;
            font-weight: 700;
            font-family: monospace;
            color: white;
        }
        .payment-controls-card .pc-header .pc-total-display .pc-total-item .value.grand { color: #FCD34D; font-size: 1.1rem; }
        .payment-controls-card .pc-header .pc-total-display .pc-total-item .value.discount { color: #FCD34D; }
        .payment-controls-card .pc-header .pc-total-display .pc-total-item .value.premium { color: #FCD34D; }
        
        .payment-controls-card .pc-body {
            padding: 16px 20px;
        }
        
        .payment-inputs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        
        .payment-input-box {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .payment-input-box:hover { border-color: var(--primary); }
        .payment-input-box.discount-box { border-left: 4px solid var(--warning); }
        .payment-input-box.premium-box { border-left: 4px solid var(--gold); }
        .payment-input-box.partial-box { border-left: 4px solid var(--primary); }
        .payment-input-box.method-box { border-left: 4px solid var(--success); }
        
        .payment-input-box .pib-label {
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
        }
        .payment-input-box .pib-label i { font-size: 0.75rem; }
        
        .payment-input-box select,
        .payment-input-box input[type="text"] {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            font-family: inherit;
        }
        .payment-input-box select:focus,
        .payment-input-box input[type="text"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }
        
        .payment-input-box .amount-wrap { position: relative; }
        .payment-input-box .amount-wrap .currency-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            pointer-events: none;
        }
        .payment-input-box .amount-wrap input[type="text"] {
            padding-left: 32px;
            text-align: right;
            font-family: monospace;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .payment-input-box .note-input {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.65rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            margin-top: 4px;
        }
        .payment-input-box .note-input:focus { border-color: var(--primary); }
        
        .payment-buttons-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
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
            white-space: nowrap;
        }
        .btn-success { background: var(--success); color: white; border: 2px solid var(--success); }
        .btn-success:hover { background: var(--success-dark); border-color: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        .btn-warning { background: var(--warning); color: white; border: 2px solid var(--warning); }
        .btn-warning:hover { background: #B45309; border-color: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
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
        
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        
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
        .toast-custom.warning { background: var(--warning); }
        
        /* INFO BOX */
        .info-box-premium {
            background: var(--gold-bg);
            border: 2px solid var(--gold);
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 12px;
        }
        .info-box-premium .ib-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-box-premium .ib-content {
            font-size: 0.7rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        .info-box-premium .ib-content strong {
            color: var(--text-primary);
        }
        
        @media (max-width: 1200px) {
            .bill-summary-grid { grid-template-columns: repeat(3, 1fr); }
            .payment-inputs-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .payment-inputs-grid { grid-template-columns: 1fr; }
            .patient-info-grid { grid-template-columns: 1fr; }
            .payment-buttons-row { flex-direction: column; align-items: stretch; }
            .payment-buttons-row .btn { width: 100%; justify-content: center; }
            .payment-controls-card .pc-header .pc-total-display { flex-wrap: wrap; justify-content: center; }
        }
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .page-header .btn-outline-light { padding: 4px 10px; font-size: 0.7rem; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .bill-summary-grid { grid-template-columns: 1fr; }
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
                    <span class="header-badge premium-badge">
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
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);">
                    <i class="fas fa-money-bill"></i> Remaining: <?= $currency ?> <?= number_format($remaining_balance, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="partial_payments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Partial Bills
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($message) && $message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- BILL CARD -->
    <div class="bill-card">
        <div class="card-header">
            <div>
                <span class="bill-number"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span>
                <span style="font-size:0.7rem;opacity:0.8;margin-left:12px;">
                    <?= date('d/m/Y H:i', strtotime($bill['bill_created'] ?? 'now')) ?>
                </span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="bill-status <?= $bill_status ?>"><?= ucfirst($bill_status) ?></span>
                <?php if ($is_paid): ?>
                    <span style="background:rgba(52,211,153,0.3);padding:2px 12px;border-radius:20px;font-size:0.6rem;border:1px solid #34D399;">✅ ALL PAID</span>
                <?php elseif ($is_partial): ?>
                    <span style="background:rgba(251,191,36,0.3);padding:2px 12px;border-radius:20px;font-size:0.6rem;border:1px solid #FBBF24;">🔄 PARTIAL</span>
                <?php endif; ?>
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
                    <span class="sub-text">Bill amount</span>
                </div>
                
                <div class="bill-summary-card discount">
                    <span class="label">🏷️ Discount</span>
                    <span class="value" id="discountDisplay"><?= $currency ?> <?= number_format($total_discount, 0) ?></span>
                    <span class="sub-text" id="discountBreakdown">
                        Pharm: <?= $currency ?> <?= number_format($pharmacy_discount, 0) ?>
                        <?php if ($cashier_discount > 0): ?>
                            | Cash: <?= $currency ?> <?= number_format($cashier_discount, 0) ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <!-- ✅ PREMIUM CARD - Bill hii pekee -->
                <div class="bill-summary-card premium">
                    <span class="label">👑 Premium (This Bill)</span>
                    <span class="value" id="premiumDisplay"><?= $currency ?> <?= number_format($existing_premium, 0) ?></span>
                    <span class="sub-text" id="premiumBreakdown">
                        <?php if (!empty($existing_premium_note)): ?>
                            <?= htmlspecialchars($existing_premium_note) ?>
                        <?php else: ?>
                            No premium
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="bill-summary-card paid">
                    <span class="label">✅ Paid Amount</span>
                    <span class="value" id="paidAmountDisplay"><?= $currency ?> <?= number_format($paid_amount, 0) ?></span>
                    <span class="sub-text" id="paidStatus">
                        <?php if ($paid_amount > 0): ?>
                            <?php if ($remaining_balance <= 0): ?>✅ Fully paid<?php else: ?>🔄 Partial<?php endif; ?>
                        <?php else: ?>No payments yet<?php endif; ?>
                    </span>
                </div>
                
                <div class="bill-summary-card balance <?= $remaining_balance <= 0 ? 'zero' : '' ?>">
                    <span class="label">📊 Remaining</span>
                    <span class="value" id="balanceDisplay"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                    <span class="sub-text" id="balanceStatus">
                        <?= $remaining_balance <= 0 ? '✅ Fully paid!' : 'Pending' ?>
                    </span>
                </div>
            </div>
            
            <!-- Bill Items Table -->
            <h4 style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:16px;margin-bottom:10px;">
                <i class="fas fa-list" style="color:var(--primary);"></i> Bill Items
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(<?= count($bill_items) ?> items)</span>
            </h4>
            
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:5%;"><i class="fas fa-hashtag"></i> #</th>
                            <th style="width:30%;"><i class="fas fa-box"></i> Item Name</th>
                            <th style="width:12%;"><i class="fas fa-tag"></i> Type</th>
                            <th style="width:8%; text-align:center;"><i class="fas fa-cubes"></i> Qty</th>
                            <th style="width:15%; text-align:right;"><i class="fas fa-dollar-sign"></i> Unit Price</th>
                            <th style="width:15%; text-align:right;"><i class="fas fa-calculator"></i> Total</th>
                            <th style="width:15%; text-align:center;"><i class="fas fa-circle"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bill_items) > 0): ?>
                            <?php $i = 1; foreach ($bill_items as $item): 
                                $is_item_paid = ($item['status'] === 'paid');
                                $is_item_partial = ($item['status'] === 'partial');
                                $is_medication = ($item['item_type'] === 'medication');
                                $is_confirmed = ($item['prescription_status'] === 'dispensed' || $item['prescription_status'] === 'confirmed');
                                $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                $unit_price = (float)($item['unit_price'] ?? 0);
                                $qty = (int)($item['quantity'] ?? 1);
                            ?>
                            <tr class="<?= $is_item_paid ? 'paid-item' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                    <?php if ($is_medication && !$is_confirmed): ?>
                                        <div style="font-size:0.5rem;color:var(--warning);margin-top:2px;">
                                            <i class="fas fa-clock"></i> Waiting pharmacy
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.55rem;background:var(--bg-body);padding:1px 8px;border-radius:4px;border:1px solid var(--border-color);">
                                        <?= ucfirst($item['item_type'] ?? 'item') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= $qty ?></td>
                                <td style="text-align:right;font-family:monospace;"><?= $currency ?> <?= number_format($unit_price, 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:600;<?= $is_item_paid ? 'color:var(--success);' : ($is_item_partial ? 'color:var(--warning);' : 'color:var(--danger);') ?>">
                                    <?= $currency ?> <?= number_format($price, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_item_paid): ?>
                                        <span class="item-status paid">✅ Paid</span>
                                    <?php elseif ($is_item_partial): ?>
                                        <span class="item-status partial">🔄 Partial</span>
                                    <?php else: ?>
                                        <span class="item-status pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:20px;color:var(--text-secondary);">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align:right;font-size:0.9rem;">
                                <i class="fas fa-calculator"></i> SUBTOTAL:
                            </td>
                            <td style="text-align:right;font-family:monospace;font-size:1rem;color:#FCD34D;">
                                <?= $currency ?> <?= number_format($subtotal, 0) ?>
                            </td>
                            <td style="text-align:center;font-size:0.7rem;color:rgba(255,255,255,0.7);">
                                <?= count($bill_items) ?> items
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Info Box: Other Bills Premium (INFO ONLY) -->
            <?php if ($other_bills_premium > 0): ?>
            <div class="info-box-premium">
                <div class="ib-title">
                    <i class="fas fa-info-circle"></i> 
                    Premium from Other Bills (INFO ONLY - Not included in this bill)
                </div>
                <div class="ib-content">
                    <strong>This bill's premium:</strong> <?= $currency ?> <?= number_format($existing_premium, 0) ?>
                    <br>
                    <strong>Other bills premium (informational):</strong> <?= $currency ?> <?= number_format($other_bills_premium, 0) ?>
                    <br>
                    <span style="font-size:0.65rem;color:var(--text-secondary);margin-top:4px;display:inline-block;">
                        ℹ️ Premium kutoka bills zingine <strong>HAIJUMUISHWI</strong> kwenye bill hii.
                        Kila bill inahesabiwa yenyewe.
                    </span>
                    <div style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--gold);">
                        <?php foreach ($other_bills_premium_details as $idx => $detail): ?>
                            <div style="font-size:0.65rem;">
                                <i class="fas fa-file-invoice" style="color:var(--gold);"></i>
                                <strong><?= htmlspecialchars($detail['bill_number']) ?></strong>
                                — <?= ucfirst($detail['status']) ?>
                                — Premium: <?= $currency ?> <?= number_format($detail['premium'], 0) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAYMENT CONTROLS CARD -->
    <?php if (!$is_paid && $remaining_balance > 0): ?>
    <div class="payment-controls-card" id="paymentControls">
        
        <!-- Header with totals -->
        <div class="pc-header">
            <div class="pc-title">
                <i class="fas fa-cash-register"></i>
                Payment Controls
            </div>
            <div class="pc-total-display">
                <div class="pc-total-item">
                    <span class="label">Remaining</span>
                    <span class="value grand" id="displayBalanceGrand"><?= $currency ?> <?= number_format($remaining_balance, 0) ?></span>
                </div>
                <div style="color:rgba(255,255,255,0.3);">|</div>
                <div class="pc-total-item">
                    <span class="label">Discount</span>
                    <span class="value discount" id="displayTotalDiscount"><?= $currency ?> <?= number_format($total_discount, 0) ?></span>
                </div>
                <div style="color:rgba(255,255,255,0.3);">|</div>
                <div class="pc-total-item">
                    <span class="label">👑 Premium</span>
                    <span class="value premium" id="displayPremium"><?= $currency ?> <?= number_format($existing_premium, 0) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Body with inputs -->
        <div class="pc-body">
            <div class="payment-inputs-grid">
                
                <!-- 1. PAYMENT METHOD -->
                <div class="payment-input-box method-box">
                    <div class="pib-label">
                        <i class="fas fa-hand-holding-usd" style="color:var(--success);"></i> Payment Method
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
                        <option value="other">📦 Other</option>
                    </select>
                </div>
                
                <!-- 2. ADD DISCOUNT -->
                <div class="payment-input-box discount-box">
                    <div class="pib-label">
                        <i class="fas fa-percent" style="color:var(--warning);"></i> Add Discount
                    </div>
                    <div class="amount-wrap">
                        <span class="currency-prefix"><?= $currency ?></span>
                        <input type="text" id="discountAmount" placeholder="0" value="0" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                </div>
                
                <!-- 3. ADD PREMIUM -->
                <div class="payment-input-box premium-box">
                    <div class="pib-label">
                        <i class="fas fa-crown" style="color:var(--gold);"></i> Add Premium
                    </div>
                    <div class="amount-wrap">
                        <span class="currency-prefix"><?= $currency ?></span>
                        <input type="text" id="premiumAmount" placeholder="0" value="0" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                    <input type="text" id="premiumNote" class="note-input" placeholder="Premium note (optional)" oninput="updateTotals();">
                </div>
                
                <!-- 4. PARTIAL PAYMENT -->
                <div class="payment-input-box partial-box">
                    <div class="pib-label">
                        <i class="fas fa-hand-holding-heart" style="color:var(--primary);"></i> Partial Payment
                    </div>
                    <div class="amount-wrap">
                        <span class="currency-prefix"><?= $currency ?></span>
                        <input type="text" id="partialAmount" placeholder="Enter Partial Amount" value="0" 
                               oninput="formatAmount(this); updateTotals();">
                    </div>
                </div>
            </div>
            
            <!-- Buttons Row -->
            <div class="payment-buttons-row">
                <div style="flex:1;display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
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
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Make Payment
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
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
    var otherBillsPremium = <?= $other_bills_premium ?>;  // ✅ INFO ONLY
    var existingPremiumNote = '<?= addslashes($existing_premium_note) ?>';
    var currency = '<?= $currency ?>';
    var isPaid = <?= $is_paid ? 'true' : 'false' ?>;

    console.log('💰 Make Payment v4.0 - Premium Bill Hii Pekee');
    console.log('📋 Bill Premium (existing):', existingPremium);
    console.log('📋 Other Bills Premium (INFO ONLY):', otherBillsPremium);
    console.log('📋 Formula: remaining = subtotal + premium(bill) + premium(new) - discount - paid');

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
        var premiumNoteInput = document.getElementById('premiumNote');
        
        var newDiscount = getRawValue(discountInput);
        var newPremium = getRawValue(premiumInput);
        var newPremiumNote = premiumNoteInput ? premiumNoteInput.value.trim() : '';
        var partial = getRawValue(partialInput);
        
        // ✅ Total Premium = bill hii PEKEE + new (HAIJUMUISHI other bills)
        var totalPremium = existingPremium + newPremium;
        
        // ✅ Total Discount = existing + new
        var totalDiscountVal = totalDiscount + newDiscount;
        
        // ✅ FORMULA: remaining = subtotal + premium(bill hii) - discount - paid
        var remainingBalance = subtotal + totalPremium - totalDiscountVal - paidAmount;
        if (remainingBalance < 0) remainingBalance = 0;
        
        var amountToPay = remainingBalance;
        if (partial > 0 && partial < remainingBalance) {
            amountToPay = partial;
        } else if (partial > 0 && partial >= remainingBalance) {
            amountToPay = remainingBalance;
        }
        
        document.getElementById('totalAmountDisplay').textContent = currency + ' ' + subtotal.toFixed(0);
        
        var discountDisplay = document.getElementById('discountDisplay');
        if (discountDisplay) discountDisplay.textContent = currency + ' ' + totalDiscountVal.toFixed(0);
        
        var premiumDisplay = document.getElementById('premiumDisplay');
        if (premiumDisplay) premiumDisplay.textContent = currency + ' ' + totalPremium.toFixed(0);
        
        var premiumBreakdown = document.getElementById('premiumBreakdown');
        if (premiumBreakdown) {
            var parts = [];
            if (existingPremium > 0) parts.push('Bill: ' + currency + ' ' + existingPremium.toFixed(0));
            if (newPremium > 0) parts.push('New: +' + currency + ' ' + newPremium.toFixed(0));
            
            if (parts.length > 0) premiumBreakdown.textContent = parts.join(' | ');
            else premiumBreakdown.textContent = 'No premium';
        }
        
        document.getElementById('paidAmountDisplay').textContent = currency + ' ' + paidAmount.toFixed(0);
        document.getElementById('balanceDisplay').textContent = currency + ' ' + remainingBalance.toFixed(0);
        document.getElementById('balanceStatus').textContent = remainingBalance <= 0 ? '✅ Fully paid!' : 'Pending';
        
        document.getElementById('displayBalanceGrand').textContent = currency + ' ' + remainingBalance.toFixed(0);
        document.getElementById('displayTotalDiscount').textContent = currency + ' ' + totalDiscountVal.toFixed(0);
        
        var displayPremium = document.getElementById('displayPremium');
        if (displayPremium) displayPremium.textContent = currency + ' ' + totalPremium.toFixed(0);
        
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        
        if (remainingBalance <= 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> Already Paid';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Already Paid';
        } else {
            fullBtn.disabled = false;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> PAY FULL (' + currency + ' ' + remainingBalance.toFixed(0) + ')';
            
            if (partial > 0 && partial <= remainingBalance) {
                partialBtn.disabled = false;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL (' + currency + ' ' + amountToPay.toFixed(0) + ')';
            } else if (partial > 0 && partial > remainingBalance) {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Amount exceeds balance';
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
        
        // ✅ Total Premium = bill hii PEKEE + new
        var totalPremium = existingPremium + newPremium;
        
        // ✅ Total Discount = existing + new
        var totalDiscountVal = totalDiscount + newDiscount;
        
        // ✅ FORMULA: remaining = subtotal + premium(bill hii) - discount - paid
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
                         '───────────────────────\n' +
                         'Subtotal: ' + currency + ' ' + subtotal.toFixed(0) + '\n' +
                         '👑 Premium (Bill): ' + currency + ' ' + existingPremium.toFixed(0) + '\n';
        
        if (newPremium > 0) {
            confirmMsg += '👑 Premium (New): +' + currency + ' ' + newPremium.toFixed(0) + '\n';
        }
        if (newDiscount > 0) {
            confirmMsg += '🏷️ Discount (New): -' + currency + ' ' + newDiscount.toFixed(0) + '\n';
        }
        
        confirmMsg += '───────────────────────\n' +
                     'Total Amount: ' + currency + ' ' + (subtotal + totalPremium - totalDiscountVal).toFixed(0) + '\n' +
                     'Already Paid: ' + currency + ' ' + paidAmount.toFixed(0) + '\n' +
                     '───────────────────────\n' +
                     '💰 Amount to Pay: ' + currency + ' ' + amountToPay.toFixed(0) + '\n' +
                     '───────────────────────\n' +
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
                showToast('❌ Error', data.message, 'error');
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
        }, 4000);
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
        console.log('✅ Make Payment v4.0 loaded');
        console.log('📋 Formula: remaining = subtotal + premium(bill) + premium(new) - discount - paid');
    });
</script>

</body>
</html>