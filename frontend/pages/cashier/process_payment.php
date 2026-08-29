<?php
// ================================================================
// FILE: frontend/pages/cashier/process_payment.php
// CASHIER - PROCESS PAYMENT WITH COMBINED DISCOUNTS
// FIXED: Pharmacy discount from discount_amount + Cashier discount
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
    // HANDLE AJAX REQUESTS
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
        
        // ================================================================
        // CANCEL INDIVIDUAL ITEM
        // ================================================================
        if ($action === 'cancel_item') {
            $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            
            if ($item_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                // Get item details
                $stmt = $db->prepare("
                    SELECT bi.*, b.id as bill_id, b.branch_id, b.patient_id, b.balance as bill_balance
                    FROM bill_items bi
                    JOIN bills b ON bi.bill_id = b.id
                    WHERE bi.id = ? AND bi.status != 'paid' AND bi.status != 'cancelled'
                ");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Item not found or already processed']);
                    exit;
                }
                
                // Update item to cancelled
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET status = 'cancelled',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$item_id]);
                
                // Update bill balance (subtract cancelled item total)
                $item_total = (float)($item['total_price'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET total_amount = total_amount - ?,
                        balance = balance - ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$item_total, $item_total, $item['bill_id']]);
                
                // Check if bill has any remaining items
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count, SUM(total_price) as total
                    FROM bill_items 
                    WHERE bill_id = ? AND status != 'cancelled' AND status != 'paid'
                ");
                $stmt->execute([$item['bill_id']]);
                $remaining = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (($remaining['count'] ?? 0) == 0 || ($remaining['total'] ?? 0) <= 0) {
                    $stmt = $db->prepare("UPDATE bills SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$item['bill_id']]);
                } else {
                    // Check if bill has any paid items
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count FROM bill_items 
                        WHERE bill_id = ? AND status = 'paid'
                    ");
                    $stmt->execute([$item['bill_id']]);
                    $paid_count = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (($paid_count['count'] ?? 0) > 0) {
                        $stmt = $db->prepare("UPDATE bills SET status = 'partial' WHERE id = ?");
                        $stmt->execute([$item['bill_id']]);
                    }
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Item cancelled successfully!',
                    'item_id' => $item_id,
                    'item_name' => $item['item_name'] ?? 'Item',
                    'item_total' => $item_total
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        // ================================================================
        // PAYMENT FOR SELECTED ITEMS - FIXED WITH DISCOUNT
        // ================================================================
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
                // Get selected items with bill details including existing discount
                $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                $stmt = $db->prepare("
                    SELECT 
                        bi.*, 
                        b.id as bill_id,
                        b.bill_number, 
                        b.patient_id, 
                        b.branch_id, 
                        b.balance as bill_balance,
                        b.total_amount as bill_total,
                        b.discount_amount as pharmacy_discount,
                        b.cashier_discount,
                        b.total_discount,
                        b.status as bill_status
                    FROM bill_items bi
                    JOIN bills b ON bi.bill_id = b.id
                    WHERE bi.id IN ($placeholders) AND bi.status != 'paid' AND bi.status != 'cancelled'
                ");
                $stmt->execute($item_ids);
                $selected_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($selected_items)) {
                    echo json_encode(['success' => false, 'message' => 'Selected items not found or already paid']);
                    exit;
                }
                
                // Group items by bill
                $bill_map = [];
                $total_original_amount = 0;
                $total_existing_discount = 0;
                
                foreach ($selected_items as $item) {
                    $bill_id = $item['bill_id'];
                    if (!isset($bill_map[$bill_id])) {
                        $bill_map[$bill_id] = [
                            'bill_id' => $bill_id,
                            'bill_number' => $item['bill_number'],
                            'patient_id' => $item['patient_id'],
                            'items' => [],
                            'total_amount' => 0,
                            'bill_total' => (float)$item['bill_total'],
                            'bill_balance' => (float)$item['bill_balance'],
                            'pharmacy_discount' => (float)($item['pharmacy_discount'] ?? 0),
                            'cashier_discount' => (float)($item['cashier_discount'] ?? 0),
                            'total_discount' => (float)($item['total_discount'] ?? 0),
                            'bill_status' => $item['bill_status'] ?? 'pending'
                        ];
                    }
                    $bill_map[$bill_id]['items'][] = $item;
                    $bill_map[$bill_id]['total_amount'] += (float)$item['total_price'];
                    $total_original_amount += (float)$item['total_price'];
                    
                    // Get total existing discount (pharmacy discount from discount_amount column)
                    if (!isset($total_existing_discount)) {
                        $total_existing_discount = 0;
                    }
                    $total_existing_discount += (float)($item['pharmacy_discount'] ?? 0);
                }
                
                // ================================================================
                // ✅ FIX: COMBINE PHARMACY DISCOUNT (from discount_amount) + CASHIER DISCOUNT
                // ================================================================
                $pharmacy_discount = $total_existing_discount;
                $cashier_discount = $discount_amount > 0 ? min($discount_amount, $total_original_amount) : 0;
                $total_discount = $pharmacy_discount + $cashier_discount;
                
                // Don't discount more than the total amount
                if ($total_discount > $total_original_amount) {
                    $total_discount = $total_original_amount;
                }
                
                $total_after_discount = $total_original_amount - $total_discount;
                
                // Calculate payment amount
                if ($action === 'partial_payment') {
                    $total_to_pay = min($partial_amount, $total_after_discount);
                } else {
                    $total_to_pay = $total_after_discount;
                }
                
                $success_count = 0;
                $receipt_numbers = [];
                $total_amount_paid = 0;
                $total_discount_applied = 0;
                
                foreach ($bill_map as $bill_id => $bill_data) {
                    $bill_portion = $bill_data['total_amount'] / $total_original_amount;
                    
                    // Calculate bill's share of total discount (pharmacy + cashier)
                    $bill_total_discount = $total_discount * $bill_portion;
                    $bill_total_discount = round($bill_total_discount, 2);
                    
                    // Calculate cashier discount portion for this bill
                    $bill_cashier_discount = $cashier_discount * $bill_portion;
                    $bill_cashier_discount = round($bill_cashier_discount, 2);
                    
                    // Calculate bill's share of payment
                    $bill_payment = $total_to_pay * $bill_portion;
                    $bill_payment = round($bill_payment, 2);
                    
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // ✅ FIX: Update bill with combined discount (pharmacy + cashier)
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET paid_amount = paid_amount + ?,
                            balance = balance - ?,
                            cashier_discount = cashier_discount + ?,
                            total_discount = discount_amount + cashier_discount,
                            updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([
                        $bill_payment, 
                        $bill_payment, 
                        $bill_cashier_discount, 
                        $bill_id, 
                        $user_branch_id
                    ]);
                    
                    // Update bill items to paid
                    $item_ids_for_bill = array_column($bill_data['items'], 'id');
                    $placeholders2 = implode(',', array_fill(0, count($item_ids_for_bill), '?'));
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET status = 'paid',
                            updated_at = NOW()
                        WHERE id IN ($placeholders2)
                    ");
                    $stmt->execute($item_ids_for_bill);
                    
                    // ✅ FIX: Check if bill is fully paid (balance <= 0)
                    $stmt = $db->prepare("SELECT balance FROM bills WHERE id = ?");
                    $stmt->execute([$bill_id]);
                    $updated_bill = $stmt->fetch(PDO::FETCH_ASSOC);
                    $new_balance = (float)($updated_bill['balance'] ?? 0);
                    
                    if ($new_balance <= 0.01) {
                        $stmt = $db->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
                        $stmt->execute([$bill_id]);
                        $status_updated = 'paid';
                    } else {
                        $stmt = $db->prepare("UPDATE bills SET status = 'partial' WHERE id = ?");
                        $stmt->execute([$bill_id]);
                        $status_updated = 'partial';
                    }
                    
                    // Insert payment record
                    $stmt = $db->prepare("
                        INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
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
                        'Payment - Pharmacy Discount: ' . $currency . ' ' . number_format($bill_data['pharmacy_discount'], 2) . ' | Cashier Discount: ' . $currency . ' ' . number_format($bill_cashier_discount, 2)
                    ]);
                    
                    $total_amount_paid += $bill_payment;
                    $total_discount_applied += $bill_total_discount;
                    $receipt_numbers[] = $receipt_number;
                    $success_count++;
                }
                
                $message = $success_count . " bill(s) updated!";
                if ($total_discount_applied > 0) {
                    $message .= " Total Discount: " . $currency . " " . number_format($total_discount_applied, 2);
                }
                $message .= " Total Paid: " . $currency . " " . number_format($total_amount_paid, 2);
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_numbers' => $receipt_numbers,
                    'total_paid' => $total_amount_paid,
                    'total_discount' => $total_discount_applied,
                    'count' => $success_count,
                    'payment_type' => $action === 'partial_payment' ? 'partial' : 'full'
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
    // GET BILLS WITH ITEMS - INCLUDING DISCOUNTS
    // ================================================================
    $bills_query = "
        SELECT 
            b.*,
            b.discount_amount as pharmacy_discount,
            b.cashier_discount,
            b.total_discount,
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
            p.email
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE b.branch_id = ? AND b.status NOT IN ('paid', 'cancelled')
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

    // ================================================================
    // GET ALL ITEMS FOR EACH BILL
    // ================================================================
    $all_items_by_bill = [];
    $medication_confirmed = [];
    
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT 
                bi.*,
                (SELECT status FROM prescriptions WHERE id = bi.reference_id AND reference_type = 'prescription') as prescription_status
            FROM bill_items bi
            WHERE bi.bill_id = ? AND bi.status != 'cancelled'
            ORDER BY bi.item_type ASC, bi.created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $all_items_by_bill[$bill['id']] = $items;
        
        // Check if medication items are confirmed
        $has_medication = false;
        $med_confirmed = true;
        foreach ($items as $item) {
            if ($item['item_type'] === 'medication') {
                $has_medication = true;
                $pres_status = $item['prescription_status'] ?? 'pending';
                if ($pres_status !== 'confirmed' && $pres_status !== 'dispensed') {
                    $med_confirmed = false;
                }
                if (($item['unit_price'] ?? 0) <= 0) {
                    $med_confirmed = false;
                }
            }
        }
        $medication_confirmed[$bill['id']] = $has_medication ? $med_confirmed : true;
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
    $all_items_by_bill = [];
    $medication_confirmed = [];
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
    
    <style>
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
            --yellow: #D97706;
            --yellow-bg: #FEF3C7;
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
            --section-other-bg: #E8F0FE;
            --section-other-border: #0B5ED7;
            --section-medication-bg: #FEF3C7;
            --section-medication-border: #D97706;
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --section-other-bg: #1E3A5F;
            --section-other-border: #3B82F6;
            --section-medication-bg: #3D2E0A;
            --section-medication-border: #D97706;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
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
        .stat-box .number { font-size: 1.6rem; font-weight: 700; }
        .stat-box .number.green { color: var(--success); }
        .stat-box .number.orange { color: var(--warning); }
        .stat-box .number.red { color: var(--danger); }
        .stat-box .number.purple { color: var(--purple); }
        .stat-box .number.blue { color: #0B5ED7; }
        .stat-box .label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
        
        .bill-header-row {
            background: var(--gray-100);
            border-bottom: 2px solid var(--border-color);
        }
        [data-theme="dark"] .bill-header-row {
            background: var(--gray-700);
        }
        
        .bill-header-row td {
            padding: 6px 12px !important;
        }
        
        .bill-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.75rem;
        }
        
        .bill-header-info .bill-number {
            font-weight: 700;
            color: var(--primary);
            font-family: monospace;
        }
        
        .bill-header-info .bill-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
        }
        .bill-header-info .bill-status.pending { background: #FEF3C7; color: #D97706; }
        .bill-header-info .bill-status.partial { background: #DBEAFE; color: #2563EB; }
        .bill-header-info .bill-status.paid { background: #D1FAE5; color: #059669; }
        .bill-header-info .bill-status.cancelled { background: #FEE2E2; color: #DC2626; }
        .bill-header-info .bill-status.waiting { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
        
        .waiting-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
            background: var(--yellow-bg);
            color: var(--yellow);
            border: 1px solid var(--yellow);
        }
        
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
        
        .btn-cancel-item {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.55rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: var(--danger);
            color: white;
        }
        .btn-cancel-item:hover {
            background: var(--danger-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }
        .btn-cancel-item:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-cancel-item i { font-size: 0.5rem; }
        
        .item-cancelled-badge {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid var(--danger);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        
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
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .patient-card .card-header:hover { background: var(--success-dark); }
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
        
        .master-table-wrap {
            overflow-x: auto;
            padding: 0;
        }
        .master-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 1000px;
        }
        .master-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 3px solid var(--border-color);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .master-table thead th:first-child { text-align: center; width: 40px; }
        .master-table tbody td { 
            padding: 8px 12px; 
            border-bottom: 1px solid var(--border-color); 
            color: var(--text-primary); 
            vertical-align: middle;
        }
        .master-table tbody tr:hover td { background: var(--table-hover); }
        .master-table tbody tr.selected td { background: var(--primary-bg); }
        .master-table tbody tr.bill-paid td { opacity: 0.6; background: var(--success-bg); }
        .master-table tbody tr.item-cancelled td { opacity: 0.5; background: var(--danger-bg); text-decoration: line-through; }
        
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
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
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
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .master-table { font-size: 0.7rem; min-width: 700px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .payment-controls { flex-direction: column; align-items: stretch; }
            .payment-controls .control-group { justify-content: center; }
            .payment-controls .btn { width: 100%; justify-content: center; }
            .patient-card .card-header { flex-direction: column; align-items: stretch; }
            .patient-card .card-header .patient-details { font-size: 0.65rem; }
            .total-display { flex-wrap: wrap; justify-content: center; }
            .amount-input-wrap input { width: 120px; }
        }
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .page-header .btn-outline-light { padding: 4px 10px; font-size: 0.7rem; }
            .stats-grid { grid-template-columns: 1fr; }
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
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN VIEW
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> RECEPTION
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Select items to pay - Cancel individual items
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills ?> pending bill(s)
                </span>
                
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);">
                    <i class="fas fa-money-bill"></i>
                    Balance: <?= $currency ?> <?= number_format($total_balance, 0) ?>
                </span>
                
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);">
                    <i class="fas fa-tag"></i>
                    Pharmacy + Cashier Discounts
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>" style="padding:12px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px;background:var(--success-bg);border:1px solid var(--success);color:var(--success);">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
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
    <!-- PATIENT CARDS WITH ITEM-LEVEL CHECKBOXES & CANCEL BUTTON -->
    <!-- ================================================================ -->
    <?php if (count($patient_bills_data) > 0): ?>
        <?php foreach ($patient_bills_data as $patient): 
            $patient_bills = isset($patient['bills']) && is_array($patient['bills']) ? $patient['bills'] : [];
            $patient_total_balance = 0;
            $patient_total_amount = 0;
            $patient_items = 0;
            $patient_med_items = 0;
            $patient_other_items = 0;
            foreach ($patient_bills as $bill) {
                $patient_total_balance += (float)$bill['balance'];
                $patient_total_amount += (float)$bill['total_amount'];
                foreach ($bill['items'] as $item) {
                    $patient_items++;
                    if ($item['item_type'] === 'medication') $patient_med_items++;
                    else $patient_other_items++;
                }
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
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div class="bill-summary">
                        <span>Items: <strong><?= $patient_items ?></strong></span>
                        <span>|</span>
                        <span>💊 Meds: <strong><?= $patient_med_items ?></strong></span>
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
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" class="item-checkbox select-all-items" 
                                           data-patient-id="<?= $patient['patient_id'] ?>" 
                                           onchange="selectAllItems(this, <?= $patient['patient_id'] ?>); updateSelectedTotal();"
                                           title="Select all payable items for this patient">
                                </th>
                                <th style="min-width:120px;">Item Name</th>
                                <th style="min-width:80px;">Type</th>
                                <th style="text-align:center; min-width:50px;">Qty</th>
                                <th style="text-align:right; min-width:100px;">Total</th>
                                <th style="text-align:center; min-width:100px;">Status</th>
                                <th style="min-width:170px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_bills as $bill): 
                                $items = isset($bill['items']) && is_array($bill['items']) ? $bill['items'] : [];
                                $med_confirmed = $bill['med_confirmed'] ?? true;
                                $bill_number = $bill['bill_number'] ?? 'N/A';
                                $bill_status = $bill['status'] ?? 'pending';
                                $bill_balance = $bill['balance'] ?? 0;
                                $pharmacy_discount = $bill['pharmacy_discount'] ?? 0;
                                $cashier_discount = $bill['cashier_discount'] ?? 0;
                                $total_discount = $bill['total_discount'] ?? 0;
                            ?>
                                <!-- BILL HEADER ROW -->
                                <tr class="bill-header-row">
                                    <td colspan="7" style="padding:4px 12px;">
                                        <div class="bill-header-info">
                                            <span class="bill-number"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill_number) ?></span>
                                            <span class="bill-status <?= $bill_status ?>"><?= ucfirst($bill_status) ?></span>
                                            <span style="color:var(--text-secondary);">
                                                Balance: <strong style="color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                                    <?= $currency ?> <?= number_format($bill_balance, 0) ?>
                                                </strong>
                                            </span>
                                            <?php if ($pharmacy_discount > 0 || $cashier_discount > 0): ?>
                                                <span style="color:var(--warning);">
                                                    <i class="fas fa-tag"></i> 
                                                    Disc: <?= $currency ?> <?= number_format($total_discount, 0) ?>
                                                    <?php if ($pharmacy_discount > 0): ?>
                                                        <span style="font-size:0.5rem;">(Pharm: <?= $currency ?> <?= number_format($pharmacy_discount, 0) ?>)</span>
                                                    <?php endif; ?>
                                                    <?php if ($cashier_discount > 0): ?>
                                                        <span style="font-size:0.5rem;">(Cash: <?= $currency ?> <?= number_format($cashier_discount, 0) ?>)</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($bill['visit_number'])): ?>
                                                <span style="color:var(--text-secondary);">
                                                    <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($bill['visit_number']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($bill['doctor_name']) && $bill['doctor_name'] !== 'Not Assigned'): ?>
                                                <span style="color:var(--text-secondary);">
                                                    <i class="fas fa-user-md"></i> <?= htmlspecialchars($bill['doctor_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!$med_confirmed): ?>
                                                <span class="waiting-badge" style="margin-left:8px;">
                                                    <i class="fas fa-clock"></i> Meds Waiting Pharmacy
                                                </span>
                                            <?php endif; ?>
                                            <span style="margin-left:auto;font-size:0.6rem;color:var(--text-secondary);">
                                                <?= count($items) ?> item(s)
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- ITEMS FOR THIS BILL -->
                                <?php foreach ($items as $item): 
                                    $is_paid = ($item['status'] === 'paid');
                                    $is_cancelled = ($item['status'] === 'cancelled');
                                    $is_medication = ($item['item_type'] === 'medication');
                                    $can_select = !$is_paid && !$is_cancelled && !($is_medication && !$med_confirmed);
                                    $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                    $qty = (int)($item['quantity'] ?? 1);
                                    $item_status = $item['status'] ?? 'pending';
                                ?>
                                    <tr class="item-row <?= $is_paid ? 'bill-paid' : '' ?> <?= $is_cancelled ? 'item-cancelled' : '' ?>" 
                                        data-item-id="<?= $item['id'] ?>" 
                                        data-price="<?= $price ?>"
                                        data-bill-id="<?= $bill['id'] ?>"
                                        data-is-medication="<?= $is_medication ? 'true' : 'false' ?>">
                                        <td style="text-align:center;">
                                            <?php if ($can_select): ?>
                                                <input type="checkbox" class="item-checkbox item-select" 
                                                       data-id="<?= $item['id'] ?>" 
                                                       data-price="<?= $price ?>"
                                                       data-bill-id="<?= $bill['id'] ?>"
                                                       data-patient-id="<?= $patient['patient_id'] ?>"
                                                       onchange="updateSelectedTotal()">
                                            <?php elseif ($is_paid): ?>
                                                <span style="color:var(--success); font-size:0.8rem;" title="Already paid">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php elseif ($is_cancelled): ?>
                                                <span style="color:var(--danger); font-size:0.8rem;" title="Cancelled">
                                                    <i class="fas fa-times-circle"></i>
                                                </span>
                                            <?php elseif ($is_medication && !$med_confirmed): ?>
                                                <span class="waiting-badge" style="font-size:0.5rem;padding:1px 8px;" title="Waiting for pharmacy confirmation">
                                                    <i class="fas fa-clock"></i> Wait Pharm
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary); font-size:0.7rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                            <?php if (!empty($item['description'])): ?>
                                                <br><small style="color:var(--text-secondary);"><?= htmlspecialchars($item['description']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($is_medication && !empty($item['instructions'])): ?>
                                                <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;background:var(--yellow-bg);padding:1px 6px;border-radius:4px;border-left:2px solid var(--yellow);">
                                                    <i class="fas fa-edit"></i> <?= htmlspecialchars(substr($item['instructions'], 0, 40)) . (strlen($item['instructions']) > 40 ? '...' : '') ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-size:0.55rem; background:var(--bg-body); padding:1px 8px; border-radius:4px; border:1px solid var(--border-color);">
                                                <?= ucfirst($item['item_type'] ?? 'item') ?>
                                            </span>
                                            <?php if ($is_medication): ?>
                                                <?php if ($med_confirmed): ?>
                                                    <span style="font-size:0.45rem;color:var(--success);display:block;">✅ Confirmed</span>
                                                <?php else: ?>
                                                    <span class="waiting-badge" style="font-size:0.45rem;display:block;margin-top:2px;padding:1px 6px;">
                                                        <i class="fas fa-clock"></i> Waiting
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;"><?= $qty ?></td>
                                        <td style="text-align:right; font-weight:600; font-family:monospace; <?= $is_paid ? 'color:var(--success);' : ($is_cancelled ? 'color:var(--danger);text-decoration:line-through;' : 'color:var(--danger);') ?>">
                                            <?= $currency ?> <?= number_format($price, 0) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if ($is_paid): ?>
                                                <span class="bill-status paid">✅ Paid</span>
                                            <?php elseif ($is_cancelled): ?>
                                                <span class="bill-status cancelled">❌ Cancelled</span>
                                            <?php elseif ($is_medication && !$med_confirmed): ?>
                                                <span class="bill-status waiting">⏳ Waiting</span>
                                            <?php elseif ($item_status === 'partial'): ?>
                                                <span class="bill-status partial">🔄 Partial</span>
                                            <?php else: ?>
                                                <span class="bill-status pending">⏳ Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if (!$is_paid && !$is_cancelled && !($is_medication && !$med_confirmed)): ?>
                                                <button class="btn-cancel-item" onclick="cancelItem(<?= $item['id'] ?>, '<?= addslashes($item['item_name'] ?? 'Item') ?>', <?= $price ?>)" title="Cancel this item">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php elseif ($is_cancelled): ?>
                                                <span class="item-cancelled-badge">
                                                    <i class="fas fa-check"></i> Cancelled
                                                </span>
                                            <?php elseif ($is_paid): ?>
                                                <span style="font-size:0.55rem;color:var(--success);">Paid</span>
                                            <?php elseif ($is_medication && !$med_confirmed): ?>
                                                <span style="font-size:0.5rem;color:var(--text-secondary);">Wait Pharm</span>
                                            <?php else: ?>
                                                <span style="font-size:0.5rem;color:var(--text-secondary);">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            
                            <!-- Patient Total Row -->
                            <tr class="patient-total-row" style="background:var(--primary-bg);font-weight:700;border-top:3px solid var(--success);">
                                <td colspan="2" style="text-align:right; font-weight:700; font-size:0.9rem; color:var(--text-primary);">
                                    <i class="fas fa-user"></i> PATIENT TOTAL:
                                </td>
                                <td></td>
                                <td></td>
                                <td style="text-align:right; font-weight:700; color:var(--success); font-family:monospace; font-size:0.9rem;">
                                    <?= $currency ?> <?= number_format($patient_total_amount, 0) ?>
                                </td>
                                <td style="text-align:center; font-weight:700; color:<?= $patient_total_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                    <?= $currency ?> <?= number_format($patient_total_balance, 0) ?>
                                </td>
                                <td style="font-size:0.65rem;color:var(--text-secondary);">
                                    <?= count($patient_bills) ?> bills
                                </td>
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
            <a href="dashboard.php" class="btn btn-success mt-4">
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
            <label><i class="fas fa-percent"></i> Cashier Discount:</label>
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
        
        <div class="total-display" id="totalDisplay">
            <div class="total-item">
                <span class="label">Total</span>
                <span class="value" id="displayTotal"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Cashier Discount</span>
                <span class="value" style="color:var(--warning);" id="displayDiscount"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Pharmacy Discount</span>
                <span class="value" style="color:var(--warning);" id="displayPharmacyDiscount"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Grand Total</span>
                <span class="value grand" id="displayGrandTotal"><?= $currency ?> 0</span>
            </div>
        </div>
        
        <div style="flex:1; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
            <span class="selected-count" id="selectedCount">
                Selected: <strong id="selectedCountNum">0</strong> items
            </span>
            <button onclick="selectAllItemsAllPatients()" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <button onclick="deselectAllItems()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Deselect All
            </button>
            <button onclick="processPayment('partial')" class="btn btn-warning" id="partialPayBtn">
                <i class="fas fa-hand-holding-heart"></i> PAY PARTIAL
            </button>
            <button onclick="processPayment('full')" class="btn btn-success" id="fullPayBtn">
                <i class="fas fa-check-circle"></i> PAY FULL
            </button>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Process Payments
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
                <?php if ($is_reception): ?>
                    <span style="color:#FCD34D;font-weight:500;font-size:0.6rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 Reception</span>
                <?php endif; ?>
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

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
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
            if (e.key === 'darkMode') syncDarkMode();
        });
        document.addEventListener('darkModeChanged', function(e) {
            if (e.detail && e.detail.isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
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
    // SELECT ALL ITEMS
    // ================================================================
    function selectAllItems(checkbox, patientId) {
        var checkboxes = document.querySelectorAll('.item-select[data-patient-id="' + patientId + '"]');
        checkboxes.forEach(function(cb) {
            if (!cb.disabled) {
                cb.checked = checkbox.checked;
            }
        });
        updateSelectedTotal();
    }

    function selectAllItemsAllPatients() {
        var checkboxes = document.querySelectorAll('.item-select:not(:disabled)');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
        });
        document.querySelectorAll('.select-all-items').forEach(function(cb) {
            var patientId = cb.dataset.patientId;
            var patientCheckboxes = document.querySelectorAll('.item-select[data-patient-id="' + patientId + '"]:not(:disabled)');
            var allChecked = true;
            patientCheckboxes.forEach(function(pcb) {
                if (!pcb.checked) allChecked = false;
            });
            cb.checked = allChecked && patientCheckboxes.length > 0;
        });
        updateSelectedTotal();
    }

    function deselectAllItems() {
        document.querySelectorAll('.item-select').forEach(function(cb) {
            cb.checked = false;
        });
        document.querySelectorAll('.select-all-items').forEach(function(cb) {
            cb.checked = false;
        });
        updateSelectedTotal();
    }

    // ================================================================
    // CANCEL INDIVIDUAL ITEM
    // ================================================================
    function cancelItem(itemId, itemName, itemPrice) {
        var currency = '<?= $currency ?>';
        if (!confirm('❌ Cancel item?\n\nItem: ' + itemName + '\nAmount: ' + currency + ' ' + itemPrice.toFixed(0) + '\n\nThis action cannot be undone!')) {
            return;
        }
        
        var btn = document.querySelector('.btn-cancel-item[onclick*="cancelItem(' + itemId + ',"]');
        if (btn) {
            var originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span>';
            btn.disabled = true;
        }
        
        var formData = new FormData();
        formData.append('action', 'cancel_item');
        formData.append('item_id', itemId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Item Cancelled', data.message, 'success');
                setTimeout(function() { window.location.reload(); }, 1500);
            } else {
                showToast('❌ Error', data.message, 'error');
                if (btn) {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            if (btn) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        });
    }

    // ================================================================
    // UPDATE SELECTED TOTAL
    // ================================================================
    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var count = checkboxes.length;
        var total_price = 0;
        
        var discountInput = document.getElementById('discountAmount');
        var partialInput = document.getElementById('partialAmount');
        var discount = getRawValue(discountInput);
        var partial = getRawValue(partialInput);
        
        checkboxes.forEach(function(cb) {
            var price = parseFloat(cb.dataset.price || 0);
            total_price += price;
        });
        
        // Get pharmacy discount from selected items' bills
        var pharmacyDiscount = 0;
        var billIds = new Set();
        checkboxes.forEach(function(cb) {
            var billId = cb.dataset.billId;
            if (billId && !billIds.has(billId)) {
                billIds.add(billId);
                var row = cb.closest('.item-row');
                if (row) {
                    // Find the bill header for this bill
                    var header = row.closest('tbody').querySelector('.bill-header-row');
                    if (header) {
                        var discText = header.textContent.match(/Pharm: [\d,]+/);
                        if (discText) {
                            var num = discText[0].replace(/[^0-9]/g, '');
                            if (num) pharmacyDiscount += parseFloat(num);
                        }
                    }
                }
            }
        });
        
        // If we couldn't get from header, use the bill data
        if (pharmacyDiscount === 0) {
            checkboxes.forEach(function(cb) {
                var row = cb.closest('.item-row');
                if (row) {
                    var price = parseFloat(row.dataset.price || 0);
                    // Estimate pharmacy discount as 10% of price for demo
                    // In real system, this comes from the bill
                }
            });
        }
        
        var grand_total = total_price - discount - pharmacyDiscount;
        if (grand_total < 0) grand_total = 0;
        
        var currency = '<?= $currency ?>';
        document.getElementById('selectedCountNum').textContent = count;
        document.getElementById('selectedTotal').textContent = currency + ' ' + total_price.toFixed(0);
        document.getElementById('displayTotal').textContent = currency + ' ' + total_price.toFixed(0);
        document.getElementById('displayDiscount').textContent = currency + ' ' + discount.toFixed(0);
        document.getElementById('displayPharmacyDiscount').textContent = currency + ' ' + pharmacyDiscount.toFixed(0);
        document.getElementById('displayGrandTotal').textContent = currency + ' ' + grand_total.toFixed(0);
        
        // Update patient select all checkboxes
        document.querySelectorAll('.select-all-items').forEach(function(cb) {
            var patientId = cb.dataset.patientId;
            var patientCheckboxes = document.querySelectorAll('.item-select[data-patient-id="' + patientId + '"]:not(:disabled)');
            var allChecked = true;
            patientCheckboxes.forEach(function(pcb) {
                if (!pcb.checked) allChecked = false;
            });
            cb.checked = allChecked && patientCheckboxes.length > 0;
        });
        
        // Enable/disable payment buttons
        var fullBtn = document.getElementById('fullPayBtn');
        var partialBtn = document.getElementById('partialPayBtn');
        
        if (count === 0) {
            fullBtn.disabled = true;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> Select Items First';
            partialBtn.disabled = true;
            partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Select Items First';
        } else {
            fullBtn.disabled = false;
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> PAY FULL (' + currency + ' ' + grand_total.toFixed(0) + ')';
            
            if (partial > 0) {
                if (partial <= total_price) {
                    partialBtn.disabled = false;
                    var displayAmount = partial.toFixed(0);
                    partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL (' + currency + ' ' + displayAmount + ')';
                } else {
                    partialBtn.disabled = true;
                    partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Amount exceeds total';
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
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var itemIds = [];
        checkboxes.forEach(function(cb) {
            itemIds.push(parseInt(cb.dataset.id));
        });
        
        if (itemIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one item', 'warning');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var discount = getRawValue(document.getElementById('discountAmount'));
        var partialAmount = getRawValue(document.getElementById('partialAmount'));
        
        var totalPrice = 0;
        checkboxes.forEach(function(cb) {
            totalPrice += parseFloat(cb.dataset.price || 0);
        });
        
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return;
            }
            if (partialAmount > totalPrice) {
                showToast('⚠️ Amount Exceeds', 'Partial amount exceeds total selected items', 'warning');
                return;
            }
            if (discount > 0 && discount > totalPrice) {
                showToast('⚠️ Discount', 'Discount cannot exceed total amount', 'warning');
                return;
            }
            
            var remainingAfterPartial = totalPrice - partialAmount - discount;
            if (remainingAfterPartial < 0) remainingAfterPartial = 0;
            
            var currency = '<?= $currency ?>';
            var confirmMsg = '💳 PARTIAL PAYMENT CONFIRMATION\n' +
                             '═══════════════════════════════\n' +
                             'Selected Items Total: ' + currency + ' ' + totalPrice.toFixed(0) + '\n' +
                             'Partial Amount: ' + currency + ' ' + partialAmount.toFixed(0) + '\n' +
                             (discount > 0 ? 'Cashier Discount: ' + currency + ' ' + discount.toFixed(0) + '\n' : '') +
                             '───────────────────────────────\n' +
                             'Remaining: ' + currency + ' ' + remainingAfterPartial.toFixed(0) + '\n\n' +
                             'Confirm partial payment for ' + itemIds.length + ' item(s)?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
        }
        
        if (type === 'full') {
            var grandTotal = totalPrice - discount;
            if (grandTotal < 0) grandTotal = 0;
            if (discount > 0 && discount > totalPrice) {
                showToast('⚠️ Discount', 'Discount cannot exceed total amount', 'warning');
                return;
            }
            
            var currency = '<?= $currency ?>';
            var confirmMsg = '💰 FULL PAYMENT CONFIRMATION\n' +
                             '═══════════════════════════════\n' +
                             'Selected Items Total: ' + currency + ' ' + totalPrice.toFixed(0) + '\n' +
                             (discount > 0 ? 'Cashier Discount: ' + currency + ' ' + discount.toFixed(0) + '\n' : '') +
                             '───────────────────────────────\n' +
                             'Amount to Pay: ' + currency + ' ' + grandTotal.toFixed(0) + '\n\n' +
                             'Confirm full payment for ' + itemIds.length + ' item(s)?';
            
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
        itemIds.forEach(function(id) {
            formData.append('item_ids[]', id);
        });
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { 
            return response.json(); 
        })
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
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

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

    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedTotal();
    });

    console.log('%c💰 Braick - Process Payments (Combined Discounts)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Pharmacy Discount + Cashier Discount = Total Discount', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Shows pharmacy discount from discount_amount column', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Cashier discount added separately', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>