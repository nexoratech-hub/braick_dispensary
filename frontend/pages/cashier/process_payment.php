<?php
// ================================================================
// FILE: frontend/pages/cashier/process_payment.php
// CASHIER - PROCESS PAYMENT WITH PATIENT CARD DESIGN
// FIXED: Uses shared header with clock
// FIXED: Dark mode fully working with header
// FIXED: Green theme applied throughout
// FIXED: Default user: Rose Mwangi (ID: 11)
// FIXED: Partial amount stays as entered (not reduced by discount)
// FIXED: All items displayed in table with prices
// FIXED: Comma formatting for amount fields
// FIXED: Updates OTC sales payment_status when bill is paid
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Default to Rose Mwangi (ID: 11)
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 11;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.rose';
    $_SESSION['email'] = 'rose@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

// ================================================================
// ALLOW RECEPTION TO ACCESS CASHIER PAGES
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
$is_admin = ($_SESSION['role'] === 'admin' || $_SESSION['is_admin'] === true);

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_id = $_SESSION['user_id'] ?? 11;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';

// ================================================================
// GET SELECTED BILL ID FROM URL
// ================================================================
$selected_bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    $db = getDB();

    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // HANDLE AJAX REQUESTS
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $bill_ids = isset($_POST['bill_ids']) ? $_POST['bill_ids'] : [];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
        
        // ================================================================
        // FULL PAYMENT
        // ================================================================
        if ($action === 'complete_payment') {
            if (empty($bill_ids) || !is_array($bill_ids)) {
                echo json_encode(['success' => false, 'message' => 'No bills selected for payment']);
                exit;
            }
            
            try {
                $success_count = 0;
                $failed_bills = [];
                $receipt_numbers = [];
                $total_amount_paid = 0;
                $total_discount_applied = 0;
                $total_original_balance = 0;
                
                foreach ($bill_ids as $bill_id) {
                    $stmt = $db->prepare("SELECT balance FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                    $stmt->execute([$bill_id, $selected_branch_id]);
                    $bill = $stmt->fetch();
                    if ($bill) {
                        $total_original_balance += (float)$bill['balance'];
                    }
                }
                
                foreach ($bill_ids as $bill_id) {
                    $bill_id = (int)$bill_id;
                    
                    $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                    $stmt->execute([$bill_id, $selected_branch_id]);
                    $bill = $stmt->fetch();
                    
                    if (!$bill) {
                        $failed_bills[] = $bill_id;
                        continue;
                    }
                    
                    $remaining = (float)$bill['balance'];
                    if ($remaining <= 0) {
                        $stmt = $db->prepare("UPDATE patient_bills SET status = 'paid', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$bill_id]);
                        $success_count++;
                        continue;
                    }
                    
                    $bill_discount = 0;
                    if ($discount_amount > 0 && $total_original_balance > 0) {
                        $bill_discount = ($remaining / $total_original_balance) * $discount_amount;
                        $bill_discount = round($bill_discount, 2);
                        $total_discount_applied += $bill_discount;
                    }
                    
                    $amount_to_pay = $remaining - $bill_discount;
                    if ($amount_to_pay < 0) $amount_to_pay = 0;
                    
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $db->prepare("
                        UPDATE patient_bills 
                        SET paid_amount = paid_amount + ?,
                            balance = ?,
                            discount_amount = discount_amount + ?,
                            status = 'paid',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$amount_to_pay, 0, $bill_discount, $bill_id]);
                    
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET is_paid = 1, 
                            payment_status = 'paid', 
                            paid_at = NOW()
                        WHERE bill_id = ? AND (is_paid = 0 OR is_paid IS NULL)
                    ");
                    $stmt->execute([$bill_id]);
                    
                    // Update OTC sales when bill is paid
                    $stmt = $db->prepare("
                        SELECT id FROM otc_sales 
                        WHERE bill_id = ? AND payment_status IN ('pending', 'partial')
                    ");
                    $stmt->execute([$bill_id]);
                    $otc_sale = $stmt->fetch();
                    
                    if ($otc_sale) {
                        $stmt = $db->prepare("
                            UPDATE otc_sales 
                            SET payment_status = 'paid',
                                updated_at = NOW()
                            WHERE bill_id = ?
                        ");
                        $stmt->execute([$bill_id]);
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $receipt_number,
                        $bill_id,
                        $bill['patient_id'],
                        $amount_to_pay,
                        $payment_method,
                        $user_id,
                        $selected_branch_id,
                        $bill_discount > 0 ? 'Discount: ' . $currency . ' ' . number_format($bill_discount, 2) : ''
                    ]);
                    
                    $total_amount_paid += $amount_to_pay;
                    $receipt_numbers[] = $receipt_number;
                    $success_count++;
                }
                
                $message = $success_count . " bill(s) paid successfully!";
                if ($total_discount_applied > 0) {
                    $message .= " Total Discount: " . $currency . " " . number_format($total_discount_applied, 2);
                }
                $message .= " Total Paid: " . $currency . " " . number_format($total_amount_paid, 2);
                
                if (!empty($failed_bills)) {
                    $message .= " Failed bills: " . implode(', ', $failed_bills);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_numbers' => $receipt_numbers,
                    'total_paid' => $total_amount_paid,
                    'total_discount' => $total_discount_applied,
                    'count' => $success_count,
                    'payment_type' => 'full'
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        // ================================================================
        // PARTIAL PAYMENT
        // ================================================================
        if ($action === 'partial_payment') {
            if (empty($bill_ids) || !is_array($bill_ids)) {
                echo json_encode(['success' => false, 'message' => 'No bills selected for payment']);
                exit;
            }
            
            if ($partial_amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid partial amount']);
                exit;
            }
            
            try {
                $success_count = 0;
                $failed_bills = [];
                $receipt_numbers = [];
                $total_amount_paid = 0;
                $total_discount_applied = 0;
                $total_original_balance = 0;
                
                foreach ($bill_ids as $bill_id) {
                    $stmt = $db->prepare("SELECT balance FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                    $stmt->execute([$bill_id, $selected_branch_id]);
                    $bill = $stmt->fetch();
                    if ($bill) {
                        $total_original_balance += (float)$bill['balance'];
                    }
                }
                
                if ($total_original_balance <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Selected bills are already fully paid']);
                    exit;
                }
                
                $amount_to_pay = min($partial_amount, $total_original_balance);
                
                $total_discount = 0;
                if ($discount_amount > 0) {
                    $total_discount = min($discount_amount, $total_original_balance - $amount_to_pay);
                    if ($total_discount < 0) $total_discount = 0;
                }
                
                if ($amount_to_pay + $total_discount > $total_original_balance) {
                    $total_discount = $total_original_balance - $amount_to_pay;
                    if ($total_discount < 0) $total_discount = 0;
                }
                
                foreach ($bill_ids as $bill_id) {
                    $bill_id = (int)$bill_id;
                    $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                    $stmt->execute([$bill_id, $selected_branch_id]);
                    $bill = $stmt->fetch();
                    
                    if (!$bill) {
                        $failed_bills[] = $bill_id;
                        continue;
                    }
                    
                    $remaining = (float)$bill['balance'];
                    if ($remaining <= 0) continue;
                    
                    $bill_portion = $remaining / $total_original_balance;
                    $bill_payment = $amount_to_pay * $bill_portion;
                    $bill_discount = $total_discount * $bill_portion;
                    
                    $bill_payment = round($bill_payment, 2);
                    $bill_discount = round($bill_discount, 2);
                    
                    $new_balance = $remaining - $bill_payment - $bill_discount;
                    if ($new_balance < 0) $new_balance = 0;
                    
                    if ($new_balance <= 0) {
                        $new_status = 'paid';
                    } else {
                        $new_status = 'partial';
                    }
                    
                    $receipt_number = 'RCP-PARTIAL-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $db->prepare("
                        UPDATE patient_bills 
                        SET paid_amount = paid_amount + ?,
                            balance = ?,
                            discount_amount = discount_amount + ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$bill_payment, $new_balance, $bill_discount, $new_status, $bill_id]);
                    
                    if ($new_status === 'paid') {
                        $stmt = $db->prepare("
                            SELECT id FROM otc_sales 
                            WHERE bill_id = ? AND payment_status IN ('pending', 'partial')
                        ");
                        $stmt->execute([$bill_id]);
                        $otc_sale = $stmt->fetch();
                        
                        if ($otc_sale) {
                            $stmt = $db->prepare("
                                UPDATE otc_sales 
                                SET payment_status = 'paid',
                                    updated_at = NOW()
                                WHERE bill_id = ?
                            ");
                            $stmt->execute([$bill_id]);
                        }
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $receipt_number,
                        $bill_id,
                        $bill['patient_id'],
                        $bill_payment,
                        $payment_method,
                        $user_id,
                        $selected_branch_id,
                        'Partial payment - Discount: ' . $currency . ' ' . number_format($bill_discount, 2)
                    ]);
                    
                    $total_amount_paid += $bill_payment;
                    $total_discount_applied += $bill_discount;
                    $receipt_numbers[] = $receipt_number;
                    $success_count++;
                }
                
                $message = "✅ Partial payment of " . $currency . " " . number_format($total_amount_paid, 2) . " completed!";
                if ($total_discount_applied > 0) {
                    $message .= " Discount: " . $currency . " " . number_format($total_discount_applied, 2);
                }
                $message .= " Remaining balance: " . $currency . " " . number_format($total_original_balance - $total_amount_paid - $total_discount_applied, 2);
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_numbers' => $receipt_numbers,
                    'total_paid' => $total_amount_paid,
                    'total_discount' => $total_discount_applied,
                    'count' => $success_count,
                    'payment_type' => 'partial'
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        // ================================================================
        // CANCEL BILLS
        // ================================================================
        if ($action === 'cancel_bills') {
            if (empty($bill_ids) || !is_array($bill_ids)) {
                echo json_encode(['success' => false, 'message' => 'No bills selected for cancellation']);
                exit;
            }
            
            try {
                $success_count = 0;
                $failed_bills = [];
                
                foreach ($bill_ids as $bill_id) {
                    $bill_id = (int)$bill_id;
                    
                    $stmt = $db->prepare("SELECT id, status FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                    $stmt->execute([$bill_id, $selected_branch_id]);
                    $bill = $stmt->fetch();
                    
                    if (!$bill) {
                        $failed_bills[] = $bill_id;
                        continue;
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE patient_bills 
                        SET status = 'cancelled', 
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$bill_id]);
                    
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET payment_status = 'cancelled', 
                            status = 'cancelled'
                        WHERE bill_id = ? AND (is_paid = 0 OR is_paid IS NULL)
                    ");
                    $stmt->execute([$bill_id]);
                    
                    $stmt = $db->prepare("
                        UPDATE otc_sales 
                        SET payment_status = 'cancelled',
                            updated_at = NOW()
                        WHERE bill_id = ? AND payment_status IN ('pending', 'partial')
                    ");
                    $stmt->execute([$bill_id]);
                    
                    $success_count++;
                }
                
                $message = $success_count . " bill(s) cancelled successfully!";
                if (!empty($failed_bills)) {
                    $message .= " Failed bills: " . implode(', ', $failed_bills);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'count' => $success_count
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // ================================================================
    // GET BILLS - FILTER BY SELECTED BILL ID IF PROVIDED
    // ================================================================

    $bills_query = "
        SELECT 
            pb.*,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            u.full_name as doctor_name,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.address,
            p.blood_group,
            p.email,
            (
                SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND (is_paid = 0 OR is_paid IS NULL) AND status != 'cancelled'
            ) as pending_items,
            (
                SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND is_paid = 1 AND status != 'cancelled'
            ) as paid_items
        FROM patient_bills pb
        JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE pb.branch_id = ? AND pb.status NOT IN ('paid', 'cancelled')
    ";

    $params = [$selected_branch_id];

    if ($selected_bill_id > 0) {
        $bills_query .= " AND pb.id = ?";
        $params[] = $selected_bill_id;
    }

    if (!empty($search)) {
        $bills_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $bills_query .= " ORDER BY pb.created_at ASC";

    $stmt = $db->prepare($bills_query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll();

    // ================================================================
    // GET ITEMS FOR EACH BILL
    // ================================================================
    foreach ($bills as &$bill) {
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY is_paid ASC, created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $bill['items'] = $stmt->fetchAll();
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
        
        $patient_map[$patient_id]['bills'][] = $bill;
    }

    $patient_bills_data = array_values($patient_map);

    // ================================================================
    // CALCULATE TOTALS
    // ================================================================
    $total_patients = count($patient_bills_data);
    $total_bills = count($bills);
    $total_balance = 0;
    $total_amount = 0;

    foreach ($bills as $bill) {
        $total_balance += (float)$bill['balance'];
        $total_amount += (float)$bill['total_amount'];
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
    $total_amount = 0;
    $has_selected_bill = false;
    $selected_bill = null;
    $currency = 'TSh';
    error_log("Process payment error: " . $e->getMessage());
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME
           ================================================================ */
        :root {
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
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #D1FAE5;
            --table-hover: #A7F3D0;
            --toast-bg: #FFFFFF;
            --toast-text: #1E293B;
            --input-bg: #FFFFFF;
            --input-border: #E2E8F0;
            --input-text: #1E293B;
            --empty-state-color: #64748B;
            --footer-border: #E2E8F0;
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #D97706;
            --badge-partial-bg: #DBEAFE;
            --badge-partial-text: #2563EB;
            --badge-paid-bg: #D1FAE5;
            --badge-paid-text: #059669;
            --badge-cancelled-bg: #FEE2E2;
            --badge-cancelled-text: #DC2626;
            --bill-header-bg: #F8FAFC;
            --bill-footer-bg: #F8FAFC;
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
            --patient-card-header-bg: #059669;
            --patient-card-header-text: #FFFFFF;
        }
        
        /* ================================================================
           ROOT VARIABLES - DARK MODE
           ================================================================ */
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1A3A2A;
            --table-hover: #1A4A3A;
            --toast-bg: #1E293B;
            --toast-text: #F1F5F9;
            --input-bg: #1E293B;
            --input-border: #334155;
            --input-text: #F1F5F9;
            --empty-state-color: #94A3B8;
            --footer-border: #334155;
            --badge-pending-bg: #3D2E0A;
            --badge-pending-text: #FBBF24;
            --badge-partial-bg: #1E3A5F;
            --badge-partial-text: #60A5FA;
            --badge-paid-bg: #1A3A2A;
            --badge-paid-text: #34D399;
            --badge-cancelled-bg: #3A1A1A;
            --badge-cancelled-text: #F87171;
            --bill-header-bg: #1E293B;
            --bill-footer-bg: #1E293B;
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
            --patient-card-header-bg: #047857;
            --patient-card-header-text: #FFFFFF;
        }
        
        /* ================================================================
           GLOBAL STYLES
           ================================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        /* ================================================================
           MAIN CONTENT OVERRIDE
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
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
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
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
        
        /* ================================================================
           STATS GRID
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            border-color: var(--success);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-box .number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .number.green { color: var(--success); }
        .stat-box .number.orange { color: var(--warning); }
        .stat-box .number.red { color: var(--danger); }
        .stat-box .number.purple { color: var(--purple); }
        .stat-box .label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
        
        /* ================================================================
           PATIENT CARD
           ================================================================ */
        .patient-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .patient-card .card-header {
            background: var(--patient-card-header-bg);
            color: var(--patient-card-header-text);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .patient-card .card-header:hover {
            background: var(--primary-dark);
        }
        
        .patient-card .card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .patient-card .card-header .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .patient-card .card-header .patient-name { font-weight: 600; font-size: 1rem; }
        .patient-card .card-header .patient-id { font-size: 0.75rem; opacity: 0.8; font-family: monospace; }
        
        .patient-card .card-header .patient-details {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            opacity: 0.85;
            flex-wrap: wrap;
        }
        
        .patient-card .card-header .patient-details span { display: flex; align-items: center; gap: 4px; }
        
        .patient-card .card-header .bill-summary {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            background: rgba(255,255,255,0.1);
            padding: 4px 14px;
            border-radius: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-card .card-header .bill-summary .amount { font-weight: 700; font-size: 0.9rem; }
        
        .patient-card .card-body { padding: 0; }
        .patient-card .card-body.collapsed { display: none; }
        
        /* ================================================================
           BILLS TABLE
           ================================================================ */
        .bills-table-wrap { overflow-x: auto; }
        
        .bills-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 900px;
        }
        
        .bills-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .bills-table thead th:first-child { text-align: center; width: 40px; }
        .bills-table tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
        .bills-table tbody tr:hover td { background: var(--table-hover); }
        .bills-table tbody tr.selected td { background: var(--primary-bg); }
        .bills-table tbody tr.bill-paid td { opacity: 0.6; background: var(--success-bg); }
        
        .bills-table .bill-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--success);
            border-radius: 4px;
        }
        .bills-table .bill-checkbox:disabled { opacity: 0.3; cursor: not-allowed; }
        
        .bills-table .total-row td {
            font-weight: 700;
            background: var(--primary-bg);
            border-top: 2px solid var(--success);
            padding: 8px 12px;
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .bill-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .bill-status.pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .bill-status.partial { background: var(--badge-partial-bg); color: var(--badge-partial-text); }
        .bill-status.paid { background: var(--badge-paid-bg); color: var(--badge-paid-text); }
        .bill-status.cancelled { background: var(--badge-cancelled-bg); color: var(--badge-cancelled-text); }
        
        .amount-total { font-weight: 700; color: var(--success); font-family: monospace; }
        .amount-balance { font-weight: 600; font-family: monospace; }
        .amount-balance.positive { color: var(--danger); }
        .amount-balance.zero { color: var(--success); }
        
        /* ================================================================
           ITEMS TABLE
           ================================================================ */
        .items-table-wrap {
            margin: 10px 0 10px 30px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            background: var(--bg-body);
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 450px;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 6px 10px;
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        .items-table thead th:last-child { text-align: right; }
        .items-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .items-table tbody td:last-child { text-align: right; font-weight: 600; font-family: monospace; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .items-table tbody tr.paid-item td { opacity: 0.6; background: var(--success-bg); }
        .items-table tbody tr.pending-item td { background: var(--warning-bg); }
        
        .items-table .item-badge {
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .items-table .item-badge.paid { background: var(--success-bg); color: var(--success); }
        .items-table .item-badge.pending { background: var(--warning-bg); color: var(--warning); }
        
        .items-table .items-total-row td {
            font-weight: 700;
            border-top: 2px solid var(--success);
            background: var(--primary-bg);
            padding: 6px 10px;
        }
        .items-table .items-total-row td:last-child {
            color: var(--danger);
            font-size: 0.85rem;
        }
        
        .items-container {
            display: none;
            padding: 8px 0;
            transition: all 0.3s ease;
        }
        .items-container.open { display: block; }
        
        .expand-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary);
            font-size: 0.7rem;
            padding: 4px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-body);
            border: 1px solid var(--border-color);
        }
        .expand-btn:hover { 
            background: var(--primary-bg);
            border-color: var(--success);
        }
        .expand-btn .badge-count {
            background: var(--success);
            color: white;
            border-radius: 50%;
            padding: 0 6px;
            font-size: 0.55rem;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
        }
        
        /* ================================================================
           PAYMENT CONTROLS
           ================================================================ */
        .payment-controls {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            position: sticky;
            bottom: 0;
            z-index: 20;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
        }
        
        .payment-controls .control-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .payment-controls .control-group label { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }
        
        .payment-controls select,
        .payment-controls input[type="text"] {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 160px;
            font-family: monospace;
        }
        
        .payment-controls select:focus,
        .payment-controls input[type="text"]:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }
        
        .payment-controls .selected-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding: 4px 14px;
            background: var(--bg-body);
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        
        .payment-controls .selected-count strong { color: var(--primary); }
        .payment-controls .divider { width: 1px; height: 30px; background: var(--border-color); }
        
        .total-display {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 10px;
            border: 2px solid var(--success);
        }
        
        .total-display .total-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 4px 8px;
        }
        
        .total-display .total-item .label { font-size: 0.6rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
        .total-display .total-item .value { font-size: 1rem; font-weight: 700; color: var(--primary); font-family: monospace; }
        .total-display .total-item .value.grand { color: var(--danger); font-size: 1.2rem; }
        
        /* ================================================================
           BUTTONS - GREEN THEME
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
        
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
        
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--success); color: var(--success); }
        
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        .btn-lg { padding: 10px 28px; font-size: 0.95rem; }
        .btn-block { width: 100%; justify-content: center; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }
        
        .amount-input-wrap {
            position: relative;
            display: inline-block;
        }
        .amount-input-wrap .currency-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .amount-input-wrap input {
            padding-left: 32px !important;
            text-align: right;
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 600;
            width: 160px;
        }
        
        /* ================================================================
           SELECTED BILL ALERT
           ================================================================ */
        .selected-bill-alert {
            background: var(--primary-bg);
            border: 2px solid var(--success);
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .selected-bill-alert .alert-title {
            font-weight: 600;
            color: var(--success-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--footer-border);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .bills-table { font-size: 0.7rem; min-width: 600px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .payment-controls { flex-direction: column; align-items: stretch; }
            .payment-controls .control-group { justify-content: center; }
            .payment-controls .btn { width: 100%; justify-content: center; }
            .patient-card .card-header { flex-direction: column; align-items: stretch; }
            .patient-card .card-header .patient-details { font-size: 0.65rem; }
            .total-display { flex-wrap: wrap; justify-content: center; }
            .items-table-wrap { margin-left: 10px; }
            .items-table { min-width: 300px; font-size: 0.65rem; }
            .amount-input-wrap input { width: 120px; }
            .selected-bill-alert { flex-direction: column; align-items: stretch; text-align: center; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .page-header .btn-outline-light { padding: 4px 10px; font-size: 0.7rem; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - FROM HEADER (Already included) -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Process Payments
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN VIEW
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Select bills to pay, apply discount or partial payment
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills ?> pending bill(s)
                </span>
                
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);">
                    <i class="fas fa-money-bill"></i>
                    Balance: <?= $currency ?> <?= number_format($total_balance, 0) ?>
                </span>
                
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                    <i class="fas fa-users"></i>
                    <?= $total_patients ?> patient(s)
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="partial_payments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Partial Payments
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Selected Bill Alert -->
    <?php if ($has_selected_bill && $selected_bill): ?>
        <div class="selected-bill-alert animate-fade-in-up">
            <div class="alert-title">
                <i class="fas fa-check-circle"></i>
                Processing Bill: <strong><?= htmlspecialchars($selected_bill['bill_number']) ?></strong>
                <span style="font-size:0.85rem;font-weight:normal;opacity:0.8;margin-left:8px;">
                    Patient: <?= htmlspecialchars($selected_bill['patient_name']) ?> 
                    (<?= htmlspecialchars($selected_bill['patient_number']) ?>)
                </span>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:0.8rem;">
                <span>Total: <strong><?= $currency ?> <?= number_format($selected_bill['total_amount'], 0) ?></strong></span>
                <span style="color:var(--success);">Paid: <strong><?= $currency ?> <?= number_format($selected_bill['paid_amount'], 0) ?></strong></span>
                <span style="color:var(--danger);">Balance: <strong><?= $currency ?> <?= number_format($selected_bill['balance'], 0) ?></strong></span>
                <span>Status: <span class="bill-status <?= $selected_bill['status'] ?>"><?= ucfirst($selected_bill['status']) ?></span></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="number purple"><?= $total_patients ?></p>
            <p class="label">👤 Patients</p>
        </div>
        <div class="stat-box">
            <p class="number orange"><?= $total_bills ?></p>
            <p class="label">📋 Pending Bills</p>
        </div>
        <div class="stat-box">
            <p class="number red" id="totalBalance"><?= $currency ?> <?= number_format($total_balance, 0) ?></p>
            <p class="label">💰 Total Balance</p>
        </div>
        <div class="stat-box">
            <p class="number green" id="selectedTotal"><?= $currency ?> 0</p>
            <p class="label">✅ Selected Amount</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT CARDS WITH BILLS -->
    <!-- ================================================================ -->
    <?php if (count($patient_bills_data) > 0): ?>
        <?php foreach ($patient_bills_data as $patient): 
            $patient_bills = isset($patient['bills']) && is_array($patient['bills']) ? $patient['bills'] : [];
            $patient_total_balance = 0;
            $patient_total_amount = 0;
            foreach ($patient_bills as $bill) {
                $patient_total_balance += (float)$bill['balance'];
                $patient_total_amount += (float)$bill['total_amount'];
            }
            $doctor_name = $patient['doctor_name'] ?? 'Not Assigned';
            $is_selected_patient = $has_selected_bill && $selected_bill && $selected_bill['patient_id'] == $patient['patient_id'];
        ?>
        <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient['patient_id'] ?>" style="animation-delay:0.1s;">
            <div class="card-header" onclick="togglePatientCard(this)">
                <div class="patient-info">
                    <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></div>
                        <div class="patient-id"><?= htmlspecialchars($patient['patient_number']) ?></div>
                        <div style="font-size:0.65rem; opacity:0.8;">
                            <i class="fas fa-user-md"></i> Doctor: <?= htmlspecialchars($doctor_name) ?>
                        </div>
                    </div>
                    <div class="patient-details">
                        <?php if (!empty($patient['phone'])): ?>
                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($patient['gender'])): ?>
                            <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($patient['blood_group'])): ?>
                            <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div class="bill-summary">
                        <span>Bills: <strong><?= count($patient_bills) ?></strong></span>
                        <span>|</span>
                        <span>Total: <strong class="amount"><?= $currency ?> <?= number_format($patient_total_amount, 0) ?></strong></span>
                        <span>|</span>
                        <span>Balance: <strong class="amount" style="color: <?= $patient_total_balance > 0 ? '#fcd34d' : '#34d399' ?>;">
                            <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                        </strong></span>
                    </div>
                    <button class="card-toggle" onclick="event.stopPropagation(); togglePatientCard(this.closest('.card-header'))">
                        <i class="fas fa-chevron-<?= $is_selected_patient ? 'up' : 'down' ?>"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body <?= $is_selected_patient ? '' : 'collapsed' ?>">
                <div class="bills-table-wrap">
                    <table class="bills-table">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" class="bill-checkbox patient-select-all" 
                                           data-patient-id="<?= $patient['patient_id'] ?>" 
                                           onchange="selectPatientBills(this, <?= $patient['patient_id'] ?>)"
                                           title="Select all bills for this patient">
                                </th>
                                <th>Bill #</th>
                                <th>Visit</th>
                                <th style="min-width:200px;">Items</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:right;">Balance</th>
                                <th style="text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_bills as $bill): 
                                $balance = (float)$bill['balance'];
                                $pending_items = (int)$bill['pending_items'];
                                $paid_items = (int)$bill['paid_items'];
                                $total_items = $pending_items + $paid_items;
                                $is_fully_paid = $balance <= 0;
                                $items = isset($bill['items']) && is_array($bill['items']) ? $bill['items'] : [];
                                $total_pending_price = 0;
                                foreach ($items as $item) {
                                    if (($item['is_paid'] ?? 0) == 0) {
                                        $total_pending_price += (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                    }
                                }
                            ?>
                            <tr class="bill-row <?= $is_fully_paid ? 'bill-paid' : '' ?> <?= ($has_selected_bill && $bill['id'] == $selected_bill_id) ? 'selected' : '' ?>" 
                                data-bill-id="<?= $bill['id'] ?>" 
                                data-balance="<?= $balance ?>" 
                                data-total="<?= $bill['total_amount'] ?>">
                                <td style="text-align:center;">
                                    <?php if (!$is_fully_paid && $balance > 0): ?>
                                        <input type="checkbox" class="bill-checkbox bill-select" 
                                               data-id="<?= $bill['id'] ?>" 
                                               data-patient-id="<?= $patient['patient_id'] ?>"
                                               <?= ($has_selected_bill && $bill['id'] == $selected_bill_id) ? 'checked' : '' ?>
                                               onchange="updateSelectedTotal()">
                                    <?php else: ?>
                                        <span style="color:var(--success); font-size:0.8rem;" title="All paid">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:var(--success);">
                                        <?= htmlspecialchars($bill['bill_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs">
                                        <?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>
                                    </span>
                                    <span class="text-xs block" style="color:var(--text-secondary);">
                                        <?= date('d/m/Y', strtotime($bill['created_at'])) ?>
                                    </span>
                                    <?php if ($bill['doctor_name'] && $bill['doctor_name'] !== 'Not Assigned'): ?>
                                        <span class="text-xs block" style="color:var(--primary);">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($bill['doctor_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="bill-items">
                                        <button class="expand-btn" onclick="toggleItems(this)" 
                                                id="items-btn-<?= $bill['id'] ?>" 
                                                data-count="<?= $total_items ?>">
                                            <i class="fas fa-chevron-right" id="items-icon-<?= $bill['id'] ?>"></i>
                                            <span>Show Items</span>
                                            <span class="badge-count"><?= $total_items ?></span>
                                            <?php if ($paid_items > 0): ?>
                                                <span style="color:var(--success); font-size:0.6rem;">✅ <?= $paid_items ?> paid</span>
                                            <?php endif; ?>
                                            <?php if ($pending_items > 0): ?>
                                                <span style="color:var(--warning); font-size:0.6rem;">⏳ <?= $pending_items ?> pending</span>
                                            <?php endif; ?>
                                        </button>
                                        
                                        <!-- ITEMS TABLE -->
                                        <div class="items-container" id="items-container-<?= $bill['id'] ?>" style="display:none;">
                                            <div class="items-table-wrap">
                                                <table class="items-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:40%;">Item Name</th>
                                                            <th style="width:15%;">Type</th>
                                                            <th style="width:10%; text-align:center;">Qty</th>
                                                            <th style="width:15%; text-align:right;">Unit Price</th>
                                                            <th style="width:20%; text-align:right;">Total</th>
                                                            <th style="width:15%; text-align:center;">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $total_pending = 0;
                                                        foreach ($items as $item): 
                                                            $is_paid = ($item['is_paid'] ?? 0) == 1;
                                                            $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                                            $unit_price = (float)($item['unit_price'] ?? $item['total_price'] ?? 0);
                                                            $qty = (int)($item['quantity'] ?? 1);
                                                            if ($unit_price == 0 && $qty > 0) {
                                                                $unit_price = $price / $qty;
                                                            }
                                                            if (!$is_paid) {
                                                                $total_pending += $price;
                                                            }
                                                        ?>
                                                            <tr class="<?= $is_paid ? 'paid-item' : 'pending-item' ?>">
                                                                <td>
                                                                    <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                                                    <?php if (!empty($item['description'])): ?>
                                                                        <br><small style="color:var(--text-secondary);"><?= htmlspecialchars($item['description']) ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span style="font-size:0.6rem; background:var(--bg-body); padding:1px 8px; border-radius:4px; border:1px solid var(--border-color);">
                                                                        <?= ucfirst($item['item_type'] ?? 'item') ?>
                                                                    </span>
                                                                </td>
                                                                <td style="text-align:center;"><?= $qty ?></td>
                                                                <td style="text-align:right; font-family:monospace;">
                                                                    <?= $currency ?> <?= number_format($unit_price, 0) ?>
                                                                </td>
                                                                <td style="text-align:right; font-family:monospace; font-weight:600; <?= $is_paid ? 'color:var(--success);' : 'color:var(--danger);' ?>">
                                                                    <?= $currency ?> <?= number_format($price, 0) ?>
                                                                </td>
                                                                <td style="text-align:center;">
                                                                    <span class="item-badge <?= $is_paid ? 'paid' : 'pending' ?>">
                                                                        <?= $is_paid ? '✅ Paid' : '⏳ Pending' ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="items-total-row">
                                                            <td colspan="4" style="text-align:right; font-weight:700; color:var(--text-primary);">
                                                                <i class="fas fa-calculator"></i> Pending Items Total:
                                                            </td>
                                                            <td style="text-align:right; font-weight:700; color:var(--danger); font-family:monospace; font-size:0.9rem;">
                                                                <?= $currency ?> <?= number_format($total_pending, 0) ?>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <?php if ($paid_items > 0): ?>
                                                        <tr style="background:var(--success-bg);">
                                                            <td colspan="4" style="text-align:right; font-weight:600; color:var(--success);">
                                                                <i class="fas fa-check-circle"></i> Paid Items Total:
                                                            </td>
                                                            <td style="text-align:right; font-weight:600; color:var(--success); font-family:monospace;">
                                                                <?= $currency ?> <?= number_format($bill['total_amount'] - $total_pending, 0) ?>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <tr style="background:var(--primary-bg); border-top:3px solid var(--success);">
                                                            <td colspan="4" style="text-align:right; font-weight:700; font-size:0.85rem; color:var(--text-primary);">
                                                                <i class="fas fa-receipt"></i> GRAND TOTAL:
                                                            </td>
                                                            <td style="text-align:right; font-weight:700; font-size:0.95rem; color:var(--success); font-family:monospace;">
                                                                <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:right; font-weight:700; color:var(--success); font-family:monospace;">
                                    <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                </td>
                                <td style="text-align:right; font-weight:600; color:<?= $balance > 0 ? 'var(--danger)' : 'var(--success)' ?>; font-family:monospace;">
                                    <?= $currency ?> <?= number_format($balance, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($balance <= 0): ?>
                                        <span class="bill-status paid">✅ Paid</span>
                                    <?php elseif ($pending_items > 0 && $paid_items > 0): ?>
                                        <span class="bill-status partial">🔄 Partial</span>
                                    <?php else: ?>
                                        <span class="bill-status pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Total row for this patient -->
                            <tr class="total-row">
                                <td colspan="3" style="text-align:right; font-weight:700; color:var(--text-primary);">
                                    Patient Total:
                                </td>
                                <td style="font-weight:600; color:var(--success); font-family:monospace;">
                                    <?= $currency ?> <?= number_format($patient_total_amount, 0) ?>
                                </td>
                                <td style="font-weight:600; color:<?= $patient_total_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>; font-family:monospace;">
                                    <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-12" style="background:var(--bg-card);border-radius:16px;border:2px solid var(--border-color);padding:60px 20px;">
            <i class="fas fa-check-circle text-5xl" style="color:var(--success);display:block;margin-bottom:16px;"></i>
            <h3 class="text-xl font-semibold" style="color:var(--text-primary);">No Pending Bills</h3>
            <p style="color:var(--text-secondary);margin-top:8px;">All bills have been paid. Great job! 🎉</p>
            <a href="dashboard.php" class="btn btn-primary mt-4">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PAYMENT CONTROLS -->
    <!-- ================================================================ -->
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
                <option value="bank">🏦 Bank Transfer</option>
                <option value="insurance">🏥 Insurance</option>
                <option value="other">📦 Other</option>
            </select>
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-percent"></i> Discount:</label>
            <div class="amount-input-wrap">
                <span class="currency-prefix"><?= $currency ?></span>
                <input type="text" id="discountAmount" class="discount-input" placeholder="0" 
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
        
        <!-- TOTAL DISPLAY -->
        <div class="total-display" id="totalDisplay">
            <div class="total-item">
                <span class="label">Total</span>
                <span class="value" id="displayTotal"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Discount</span>
                <span class="value" style="color:var(--warning);" id="displayDiscount"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Grand Total</span>
                <span class="value grand" id="displayGrandTotal"><?= $currency ?> 0</span>
            </div>
        </div>
        
        <div style="flex:1; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
            <span class="selected-count" id="selectedCount">
                Selected: <strong id="selectedCountNum">0</strong> bills
            </span>
            <button onclick="selectAllBills()" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <button onclick="deselectAllBills()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Deselect All
            </button>
            <button onclick="processPayment('cancel')" class="btn btn-danger" id="cancelBtn">
                <i class="fas fa-times-circle"></i> CANCEL
            </button>
            <button onclick="processPayment('partial')" class="btn btn-warning" id="partialPayBtn">
                <i class="fas fa-hand-holding-heart"></i> PAY PARTIAL
            </button>
            <button onclick="processPayment('full')" class="btn btn-success" id="fullPayBtn">
                <i class="fas fa-check-circle"></i> PAY FULL
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Process Payments
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
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
            if (e.key === 'darkMode') {
                syncDarkMode();
            }
        });
        
        document.addEventListener('darkModeChanged', function(e) {
            var isDark = e.detail && e.detail.isDark;
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        });
    })();

    // ================================================================
    // FORMAT AMOUNT WITH COMMAS
    // ================================================================
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

    // ================================================================
    // TOGGLE PATIENT CARD
    // ================================================================
    function togglePatientCard(header) {
        var card = header.closest('.patient-card');
        var body = card.querySelector('.card-body');
        var icon = header.querySelector('.card-toggle i');
        
        if (body.classList.contains('collapsed')) {
            body.classList.remove('collapsed');
            if (icon) icon.className = 'fas fa-chevron-up';
        } else {
            body.classList.add('collapsed');
            if (icon) icon.className = 'fas fa-chevron-down';
        }
    }

    // ================================================================
    // TOGGLE ITEMS EXPAND
    // ================================================================
    function toggleItems(element) {
        var container = element.parentElement.querySelector('.items-container');
        var icon = element.querySelector('.fa-chevron-right');
        if (!icon) {
            icon = element.querySelector('.fa-chevron-down');
        }
        
        if (container) {
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                container.classList.add('open');
                if (icon) {
                    icon.className = 'fas fa-chevron-down';
                }
                var text = element.querySelector('span:not(.badge-count)');
                if (text) text.textContent = ' Hide Items';
            } else {
                container.style.display = 'none';
                container.classList.remove('open');
                if (icon) {
                    icon.className = 'fas fa-chevron-right';
                }
                var text = element.querySelector('span:not(.badge-count)');
                if (text) text.textContent = ' Show Items';
            }
        }
    }

    // ================================================================
    // SELECT PATIENT BILLS
    // ================================================================
    function selectPatientBills(checkbox, patientId) {
        var checkboxes = document.querySelectorAll('.bill-select[data-patient-id="' + patientId + '"]');
        checkboxes.forEach(function(cb) {
            cb.checked = checkbox.checked;
        });
        updateSelectedTotal();
    }

    // ================================================================
    // SELECT / DESELECT ALL
    // ================================================================
    function selectAllBills() {
        var checkboxes = document.querySelectorAll('.bill-select:not(:disabled)');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
        });
        document.querySelectorAll('.patient-select-all').forEach(function(cb) {
            var patientId = cb.dataset.patientId;
            var patientCheckboxes = document.querySelectorAll('.bill-select[data-patient-id="' + patientId + '"]');
            var allChecked = true;
            patientCheckboxes.forEach(function(pcb) {
                if (!pcb.checked) allChecked = false;
            });
            cb.checked = allChecked && patientCheckboxes.length > 0;
        });
        updateSelectedTotal();
    }

    function deselectAllBills() {
        document.querySelectorAll('.bill-select').forEach(function(cb) {
            cb.checked = false;
        });
        document.querySelectorAll('.patient-select-all').forEach(function(cb) {
            cb.checked = false;
        });
        updateSelectedTotal();
    }

    // ================================================================
    // UPDATE SELECTED TOTAL
    // ================================================================
    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var count = checkboxes.length;
        var total_balance = 0;
        var total_amount = 0;
        
        var discountInput = document.getElementById('discountAmount');
        var partialInput = document.getElementById('partialAmount');
        var discount = getRawValue(discountInput);
        var partial = getRawValue(partialInput);
        
        checkboxes.forEach(function(cb) {
            var row = cb.closest('.bill-row');
            if (row) {
                var balance = parseFloat(row.dataset.balance || 0);
                total_balance += balance;
                var amount = parseFloat(row.dataset.total || 0);
                total_amount += amount;
            }
        });
        
        var grand_total = total_balance - discount;
        if (grand_total < 0) grand_total = 0;
        
        var currency = '<?= $currency ?>';
        document.getElementById('selectedCountNum').textContent = count;
        document.getElementById('selectedTotal').textContent = currency + ' ' + total_balance.toFixed(0);
        document.getElementById('displayTotal').textContent = currency + ' ' + total_balance.toFixed(0);
        document.getElementById('displayDiscount').textContent = currency + ' ' + discount.toFixed(0);
        document.getElementById('displayGrandTotal').textContent = currency + ' ' + grand_total.toFixed(0);
        
        // Update patient select all checkboxes
        var patients = document.querySelectorAll('.patient-card');
        patients.forEach(function(card) {
            var patientId = card.dataset.patientId;
            var selectAll = card.querySelector('.patient-select-all');
            if (selectAll) {
                var patientCheckboxes = card.querySelectorAll('.bill-select');
                var allChecked = true;
                patientCheckboxes.forEach(function(cb) {
                    if (!cb.checked) allChecked = false;
                });
                selectAll.checked = allChecked && patientCheckboxes.length > 0;
                if (patientCheckboxes.length === 0) {
                    selectAll.checked = false;
                }
            }
        });
        
        // Enable/disable payment buttons
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        var cancelBtn = document.getElementById('cancelBtn');
        
        if (count === 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> Select Bills First';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Select Bills First';
            cancelBtn.disabled = true;
            cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> Select Bills First';
        } else {
            fullBtn.disabled = false;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> PAY FULL (' + currency + ' ' + grand_total.toFixed(0) + ')';
            cancelBtn.disabled = false;
            cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> CANCEL (' + count + ' bills)';
            
            if (partial > 0) {
                if (partial <= total_balance) {
                    partialBtn.disabled = false;
                    var displayAmount = partial.toFixed(0);
                    partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL (' + currency + ' ' + displayAmount + ')';
                } else {
                    partialBtn.disabled = true;
                    partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Amount exceeds balance';
                }
            } else {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Enter Partial Amount';
            }
        }
    }

    // ================================================================
    // PROCESS PAYMENT
    // ================================================================
    function processPayment(type) {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var billIds = [];
        checkboxes.forEach(function(cb) {
            billIds.push(parseInt(cb.dataset.id));
        });
        
        if (billIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one bill', 'warning');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var discount = getRawValue(document.getElementById('discountAmount'));
        var partialAmount = getRawValue(document.getElementById('partialAmount'));
        
        var totalBalance = 0;
        checkboxes.forEach(function(cb) {
            var row = cb.closest('.bill-row');
            if (row) {
                totalBalance += parseFloat(row.dataset.balance || 0);
            }
        });
        
        // CANCEL BILLS
        if (type === 'cancel') {
            if (!confirm('Cancel ' + billIds.length + ' selected bill(s)?\n\nThis action cannot be undone!')) {
                return;
            }
            
            var btn = document.getElementById('cancelBtn');
            var originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span> Cancelling...';
            btn.disabled = true;
            
            var formData = new FormData();
            formData.append('action', 'cancel_bills');
            billIds.forEach(function(id) {
                formData.append('bill_ids[]', id);
            });
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('✅ Success', data.message, 'success');
                    setTimeout(function() { window.location.reload(); }, 2000);
                } else {
                    showToast('❌ Error', data.message, 'error');
                }
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            })
            .catch(function(error) {
                showToast('❌ Error', 'Network error: ' + error.message, 'error');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
            return;
        }
        
        // PARTIAL PAYMENT
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return;
            }
            if (partialAmount > totalBalance) {
                showToast('⚠️ Amount Exceeds', 'Partial amount exceeds total balance', 'warning');
                return;
            }
            if (discount > 0 && discount > totalBalance) {
                showToast('⚠️ Discount', 'Discount cannot exceed total balance', 'warning');
                return;
            }
            
            var remainingAfterPartial = totalBalance - partialAmount - discount;
            if (remainingAfterPartial < 0) remainingAfterPartial = 0;
            
            var currency = '<?= $currency ?>';
            var confirmMsg = '💳 PARTIAL PAYMENT CONFIRMATION\n' +
                             '═══════════════════════════════\n' +
                             'Total Balance: ' + currency + ' ' + totalBalance.toFixed(0) + '\n' +
                             'Partial Amount: ' + currency + ' ' + partialAmount.toFixed(0) + '\n' +
                             (discount > 0 ? 'Discount: ' + currency + ' ' + discount.toFixed(0) + '\n' : '') +
                             '───────────────────────────────\n' +
                             'Remaining Balance: ' + currency + ' ' + remainingAfterPartial.toFixed(0) + '\n\n' +
                             'Confirm partial payment for ' + billIds.length + ' bill(s)?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
        }
        
        // FULL PAYMENT
        if (type === 'full') {
            var grandTotal = totalBalance - discount;
            if (grandTotal < 0) grandTotal = 0;
            if (discount > 0 && discount > totalBalance) {
                showToast('⚠️ Discount', 'Discount cannot exceed total balance', 'warning');
                return;
            }
            
            var currency = '<?= $currency ?>';
            var confirmMsg = '💰 FULL PAYMENT CONFIRMATION\n' +
                             '═══════════════════════════════\n' +
                             'Total Balance: ' + currency + ' ' + totalBalance.toFixed(0) + '\n' +
                             (discount > 0 ? 'Discount: ' + currency + ' ' + discount.toFixed(0) + '\n' : '') +
                             '───────────────────────────────\n' +
                             'Amount to Pay: ' + currency + ' ' + grandTotal.toFixed(0) + '\n\n' +
                             'Confirm full payment for ' + billIds.length + ' bill(s)?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
        }
        
        var btn = type === 'partial' ? document.getElementById('partialPayBtn') : document.getElementById('fullPayBtn');
        var originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', type === 'partial' ? 'partial_payment' : 'complete_payment');
        formData.append('payment_method', paymentMethod);
        if (discount > 0) {
            formData.append('discount_amount', discount);
        }
        if (type === 'partial') {
            formData.append('partial_amount', partialAmount);
        }
        billIds.forEach(function(id) {
            formData.append('bill_ids[]', id);
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
                showToast('❌ Error', data.message, 'error');
            }
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            updateSelectedTotal();
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'process_payment.php?search=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var clockDisplay = document.getElementById('clockDisplay');
        if (clockDisplay) {
            clockDisplay.textContent = dateStr + ' • ' + timeStr;
        }
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($has_selected_bill && $selected_bill): ?>
            var billCheckbox = document.querySelector('.bill-select[data-id="<?= $selected_bill_id ?>"]');
            if (billCheckbox) {
                billCheckbox.checked = true;
            }
            var patientCard = document.querySelector('.patient-card[data-patient-id="<?= $selected_bill['patient_id'] ?>"]');
            if (patientCard) {
                var body = patientCard.querySelector('.card-body');
                if (body) {
                    body.classList.remove('collapsed');
                }
                var header = patientCard.querySelector('.card-header');
                if (header) {
                    var icon = header.querySelector('.card-toggle i');
                    if (icon) icon.className = 'fas fa-chevron-up';
                }
            }
            setTimeout(function() {
                toggleItemsByBillId(<?= $selected_bill_id ?>);
            }, 500);
        <?php endif; ?>
        
        updateSelectedTotal();
    });

    function toggleItemsByBillId(billId) {
        var container = document.getElementById('items-container-' + billId);
        var btn = document.getElementById('items-btn-' + billId);
        var icon = document.getElementById('items-icon-' + billId);
        
        if (container) {
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                container.classList.add('open');
                if (icon) {
                    icon.className = 'fas fa-chevron-down';
                }
                if (btn) {
                    var text = btn.querySelector('span:not(.badge-count)');
                    if (text) text.textContent = ' Hide Items';
                }
            } else {
                container.style.display = 'none';
                container.classList.remove('open');
                if (icon) {
                    icon.className = 'fas fa-chevron-right';
                }
                if (btn) {
                    var text = btn.querySelector('span:not(.badge-count)');
                    if (text) text.textContent = ' Show Items';
                }
            }
        }
    }

    console.log('%c💰 Braick - Process Payments (Green Theme)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: Rose Mwangi (ID: 11)', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Partial amount stays as entered (not reduced by discount)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ All items displayed in table with prices', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Comma formatting for amount fields', 'font-size:13px; color:#34D399;');
    console.log('%c✅ OTC Sales payment_status updated when bill is paid', 'font-size:13px; color:#34D399;');
    console.log('%c🌓 Dark mode synced with header via localStorage', 'font-size:13px; color:#8B5CF6;');
    <?php if ($has_selected_bill && $selected_bill): ?>
        console.log('%c📋 Selected Bill: <?= $selected_bill['bill_number'] ?>', 'font-size:13px; color:#3B82F6;');
        console.log('%c👤 Patient: <?= $selected_bill['patient_name'] ?>', 'font-size:13px; color:#64748B;');
        console.log('%c💰 Balance: <?= $currency ?> <?= number_format($selected_bill['balance'], 0) ?>', 'font-size:13px; color:#DC2626;');
    <?php else: ?>
        console.log('%c📋 Total Bills: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
        console.log('%c💰 Total Balance: <?= $currency ?> <?= number_format($total_balance, 0) ?>', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
</script>

</body>
</html>