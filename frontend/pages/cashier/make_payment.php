<?php
// ================================================================
// FILE: frontend/pages/cashier/process_payment.php
// CASHIER - PROCESS PAYMENT WITH PATIENT CARD DESIGN
// FIXED: Partial payment uses exact amount entered (8000 shows 8000)
// DISCOUNT: Amount in TSh (not percentage)
// FIXED: Undefined array key "bills" error
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// DEFAULT SESSION - Cashier Dodoma (ID: 10)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 10;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Cashier Dodoma';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

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
    $payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : 'full';
    
    // ================================================================
    // FULL PAYMENT - Pay entire bills with discount
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
            
            // First, calculate total balance of all selected bills
            foreach ($bill_ids as $bill_id) {
                $stmt = $db->prepare("SELECT balance FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($bill) {
                    $total_original_balance += (float)$bill['balance'];
                }
            }
            
            foreach ($bill_ids as $bill_id) {
                $bill_id = (int)$bill_id;
                
                // Get bill details
                $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
                
                // Calculate discount per bill proportionally
                $bill_discount = 0;
                if ($discount_amount > 0 && $total_original_balance > 0) {
                    $bill_discount = ($remaining / $total_original_balance) * $discount_amount;
                    $bill_discount = round($bill_discount, 2);
                    $total_discount_applied += $bill_discount;
                }
                
                $amount_to_pay = $remaining - $bill_discount;
                if ($amount_to_pay < 0) $amount_to_pay = 0;
                
                // Generate receipt number
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Update bill with discount
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
                
                // Mark all items as paid
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET is_paid = 1, 
                        payment_status = 'paid', 
                        paid_at = NOW()
                    WHERE bill_id = ? AND (is_paid = 0 OR is_paid IS NULL)
                ");
                $stmt->execute([$bill_id]);
                
                // Insert payment record
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
                    $user_branch_id,
                    $bill_discount > 0 ? 'Discount: TSh ' . number_format($bill_discount, 2) : ''
                ]);
                
                $total_amount_paid += $amount_to_pay;
                $receipt_numbers[] = $receipt_number;
                $success_count++;
            }
            
            $message = $success_count . " bill(s) paid successfully!";
            if ($total_discount_applied > 0) {
                $message .= " Total Discount: TSh " . number_format($total_discount_applied, 2);
            }
            $message .= " Total Paid: TSh " . number_format($total_amount_paid, 2);
            
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
    // PARTIAL PAYMENT - Pay exact amount entered by user
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
            
            // Calculate total balance for selected bills
            foreach ($bill_ids as $bill_id) {
                $stmt = $db->prepare("SELECT balance FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($bill) {
                    $total_original_balance += (float)$bill['balance'];
                }
            }
            
            if ($total_original_balance <= 0) {
                echo json_encode(['success' => false, 'message' => 'Selected bills are already fully paid']);
                exit;
            }
            
            // Use the exact partial amount entered
            $amount_to_pay = min($partial_amount, $total_original_balance);
            
            // Calculate total discount proportionally
            $total_discount = 0;
            if ($discount_amount > 0) {
                $total_discount = min($discount_amount, $total_original_balance);
            }
            
            // If partial amount + discount exceeds total balance, adjust
            if ($amount_to_pay + $total_discount > $total_original_balance) {
                $total_discount = $total_original_balance - $amount_to_pay;
                if ($total_discount < 0) $total_discount = 0;
            }
            
            foreach ($bill_ids as $bill_id) {
                $bill_id = (int)$bill_id;
                $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bill) {
                    $failed_bills[] = $bill_id;
                    continue;
                }
                
                $remaining = (float)$bill['balance'];
                if ($remaining <= 0) continue;
                
                // Calculate proportional payment and discount
                $bill_portion = $remaining / $total_original_balance;
                $bill_payment = $amount_to_pay * $bill_portion;
                $bill_discount = $total_discount * $bill_portion;
                
                $bill_payment = round($bill_payment, 2);
                $bill_discount = round($bill_discount, 2);
                
                // Check if this bill can be fully paid
                if ($bill_payment + $bill_discount >= $remaining) {
                    // Full payment for this bill
                    $bill_payment = $remaining - $bill_discount;
                    if ($bill_payment < 0) {
                        $bill_payment = 0;
                        $bill_discount = $remaining;
                    }
                    $new_balance = 0;
                    $new_status = 'paid';
                } else {
                    $new_balance = $remaining - $bill_payment - $bill_discount;
                    if ($new_balance < 0) $new_balance = 0;
                    $new_status = 'partial';
                }
                
                // Generate receipt number
                $receipt_number = 'RCP-PARTIAL-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Update bill
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
                
                // Insert payment record
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
                    $user_branch_id,
                    'Partial payment - Discount: TSh ' . number_format($bill_discount, 2)
                ]);
                
                $total_amount_paid += $bill_payment;
                $total_discount_applied += $bill_discount;
                $receipt_numbers[] = $receipt_number;
                $success_count++;
            }
            
            $message = "✅ Partial payment of TSh " . number_format($total_amount_paid, 2) . " completed!";
            if ($total_discount_applied > 0) {
                $message .= " Discount: TSh " . number_format($total_discount_applied, 2);
            }
            
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
                
                // Check if bill exists and is not already paid or cancelled
                $stmt = $db->prepare("SELECT id, status FROM patient_bills WHERE id = ? AND branch_id = ? AND status NOT IN ('paid', 'cancelled')");
                $stmt->execute([$bill_id, $user_branch_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bill) {
                    $failed_bills[] = $bill_id;
                    continue;
                }
                
                // Update bill status to cancelled
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET status = 'cancelled', 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$bill_id]);
                
                // Update bill items
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET payment_status = 'cancelled', 
                        status = 'cancelled'
                    WHERE bill_id = ? AND (is_paid = 0 OR is_paid IS NULL)
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
// GET ALL PENDING PATIENTS WITH BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT 
        p.id as patient_id,
        p.full_name,
        p.patient_id as patient_number,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.address,
        p.blood_group,
        p.email,
        pb.branch_id,
        u.full_name as doctor_name,
        v.doctor_id
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE pb.branch_id = ? AND pb.status NOT IN ('paid', 'cancelled')
    ORDER BY p.full_name
");
$stmt->execute([$user_branch_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILLS FOR EACH PATIENT
// ================================================================
$patient_bills_data = [];
foreach ($patients as $patient) {
    $stmt = $db->prepare("
        SELECT 
            pb.*,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            u.full_name as doctor_name,
            (
                SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND (is_paid = 0 OR is_paid IS NULL)
            ) as pending_items,
            (
                SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND is_paid = 1
            ) as paid_items
        FROM patient_bills pb
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE pb.patient_id = ? AND pb.branch_id = ? AND pb.status NOT IN ('paid', 'cancelled')
        ORDER BY pb.created_at ASC
    ");
    $stmt->execute([$patient['patient_id'], $user_branch_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get items for each bill
    foreach ($bills as &$bill) {
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? 
            ORDER BY is_paid ASC, created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $bill['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $patient['bills'] = $bills;
    $patient_bills_data[] = $patient;
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_patients = count($patients);
$total_bills = 0;
$total_balance = 0;
$total_amount = 0;

foreach ($patient_bills_data as $patient) {
    if (isset($patient['bills']) && is_array($patient['bills'])) {
        foreach ($patient['bills'] as $bill) {
            $total_bills++;
            $total_balance += (float)$bill['balance'];
            $total_amount += (float)$bill['total_amount'];
        }
    }
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       PROCESS PAYMENT STYLES - PATIENT CARD DESIGN
       ================================================================ */
    :root {
        --primary: #059669;
        --primary-dark: #047857;
        --primary-light: #10b981;
        --primary-bg: #ecfdf5;
        --primary-border: #d1fae5;
        --success: #059669;
        --success-bg: #ecfdf5;
        --warning: #d97706;
        --warning-bg: #fef3c7;
        --danger: #dc2626;
        --danger-bg: #fee2e2;
        --purple: #7c3aed;
        --purple-bg: #ede9fe;
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
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
    }
    
    .stat-box .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .stat-box .number.green { color: #059669; }
    .stat-box .number.orange { color: #d97706; }
    .stat-box .number.red { color: #dc2626; }
    .stat-box .number.purple { color: #7c3aed; }
    .stat-box .label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
    
    /* Patient Card */
    .patient-card {
        background: var(--bg-card);
        border-radius: 14px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .patient-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.08);
    }
    
    .patient-card .card-header {
        background: var(--primary);
        color: white;
        padding: 12px 20px;
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
    }
    
    .patient-card .card-header .bill-summary .amount { font-weight: 700; font-size: 0.9rem; }
    
    .patient-card .card-body { padding: 0; }
    .patient-card .card-body.collapsed { display: none; }
    
    /* Bills Table inside Card */
    .bills-table-wrap { overflow-x: auto; }
    
    .bills-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 700px;
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
        accent-color: #059669;
        border-radius: 4px;
    }
    .bills-table .bill-checkbox:disabled { opacity: 0.3; cursor: not-allowed; }
    
    .bill-status {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .bill-status.pending { background: var(--warning-bg); color: var(--warning); }
    .bill-status.partial { background: var(--warning-bg); color: var(--warning); }
    .bill-status.paid { background: var(--success-bg); color: var(--success); }
    .bill-status.cancelled { background: var(--danger-bg); color: var(--danger); }
    
    .amount-total { font-weight: 700; color: #059669; font-family: monospace; }
    .amount-balance { font-weight: 600; font-family: monospace; }
    .amount-balance.positive { color: #dc2626; }
    .amount-balance.zero { color: #059669; }
    
    /* Payment Controls */
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
    .payment-controls input[type="number"] {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        width: 140px;
    }
    
    .payment-controls select:focus,
    .payment-controls input[type="number"]:focus {
        border-color: #059669;
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
    
    /* Total Display */
    .total-display {
        display: flex;
        align-items: center;
        gap: 16px;
        background: var(--primary-bg);
        padding: 4px 16px;
        border-radius: 10px;
        border: 2px solid var(--primary);
    }
    
    .total-display .total-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 4px 8px;
    }
    
    .total-display .total-item .label { font-size: 0.6rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
    .total-display .total-item .value { font-size: 1rem; font-weight: 700; color: var(--primary); font-family: monospace; }
    .total-display .total-item .value.grand { color: #dc2626; font-size: 1.2rem; }
    
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
    
    .btn-success { background: #059669; color: white; }
    .btn-success:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
    
    .btn-warning { background: #d97706; color: white; }
    .btn-warning:hover { background: #b45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
    
    .btn-danger { background: #dc2626; color: white; }
    .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
    
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: #059669; color: #059669; }
    
    .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
    .btn-lg { padding: 10px 28px; font-size: 0.95rem; }
    .btn-block { width: 100%; justify-content: center; }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }
    
    /* Expand items */
    .expand-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--primary);
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .expand-btn:hover { background: var(--primary-bg); }
    
    .items-container { display: none; padding: 6px 0 6px 20px; border-left: 2px solid var(--primary); margin-top: 4px; background: var(--bg-body); border-radius: 0 4px 4px 0; }
    .items-container.open { display: block; }
    
    .item-row { display: flex; justify-content: space-between; align-items: center; padding: 3px 0; font-size: 0.7rem; border-bottom: 1px dashed var(--border-color); }
    .item-row:last-child { border-bottom: none; }
    .item-row.paid-item { opacity: 0.6; }
    .item-row .item-name { font-weight: 500; color: var(--text-primary); }
    .item-row .item-price { font-weight: 600; font-family: monospace; }
    .item-row .item-price.paid { color: #059669; }
    .item-row .item-price.pending { color: #dc2626; }
    
    .item-badge { padding: 1px 8px; border-radius: 10px; font-size: 0.55rem; font-weight: 600; display: inline-flex; align-items: center; gap: 3px; }
    .item-badge.paid { background: var(--success-bg); color: var(--success); }
    .item-badge.pending { background: var(--warning-bg); color: var(--warning); }
    
    .discount-input { max-width: 120px; }
    .partial-input { max-width: 140px; }
    
    .total-row td { font-weight: 700; border-top: 2px solid var(--primary); background: var(--primary-bg); }
    
    .card-toggle {
        background: none;
        border: none;
        color: rgba(255,255,255,0.7);
        cursor: pointer;
        transition: transform 0.3s ease;
        font-size: 0.9rem;
    }
    .card-toggle:hover { color: white; }
    .card-toggle.rotated { transform: rotate(180deg); }
    
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
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #dc2626; }
    .toast-custom.info { background: #0b5ed7; }
    .toast-custom.warning { background: #d97706; }
    
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
    
    @media (max-width: 768px) {
        .bills-table { font-size: 0.7rem; min-width: 500px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .payment-controls { flex-direction: column; align-items: stretch; }
        .payment-controls .control-group { justify-content: center; }
        .payment-controls .btn { width: 100%; justify-content: center; }
        .patient-card .card-header { flex-direction: column; align-items: stretch; }
        .patient-card .card-header .patient-details { font-size: 0.65rem; }
        .total-display { flex-wrap: wrap; justify-content: center; }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients by name or ID...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%23059669%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EC%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave mr-2" style="color: #059669;"></i> Process Payments
                <span class="role-badge-display ml-2">CASHIER</span>
            </h1>
            <p class="page-subtitle">
                Select bills to pay, apply discount or partial payment
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-file-invoice mr-1"></i> <?= $total_bills ?> pending bill(s)
                </span>
                <span class="ml-2 inline-flex bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs border border-orange-200">
                    <i class="fas fa-money-bill mr-1"></i> Balance: <?= number_format($total_balance, 2) ?>
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-users mr-1"></i> <?= $total_patients ?> patient(s)
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="number purple"><?= $total_patients ?></p>
            <p class="label">Patients</p>
        </div>
        <div class="stat-box">
            <p class="number orange"><?= $total_bills ?></p>
            <p class="label">Pending Bills</p>
        </div>
        <div class="stat-box">
            <p class="number red" id="totalBalance"><?= number_format($total_balance, 2) ?></p>
            <p class="label">Total Balance</p>
        </div>
        <div class="stat-box">
            <p class="number green" id="selectedTotal">0.00</p>
            <p class="label">Selected Amount</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT CARDS WITH BILLS -->
    <!-- ================================================================ -->
    <?php if (count($patient_bills_data) > 0): ?>
        <?php foreach ($patient_bills_data as $patient): 
            // Check if bills exists and is array
            $patient_bills = isset($patient['bills']) && is_array($patient['bills']) ? $patient['bills'] : [];
            $patient_total_balance = 0;
            $patient_total_amount = 0;
            foreach ($patient_bills as $bill) {
                $patient_total_balance += (float)$bill['balance'];
                $patient_total_amount += (float)$bill['total_amount'];
            }
            $doctor_name = $patient['doctor_name'] ?? 'Not Assigned';
        ?>
        <div class="patient-card" data-patient-id="<?= $patient['patient_id'] ?>">
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
                        <?php if (!empty($patient['date_of_birth'])): ?>
                            <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($patient['date_of_birth'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div class="bill-summary">
                        <span>Bills: <strong><?= count($patient_bills) ?></strong></span>
                        <span>|</span>
                        <span>Total: <strong class="amount">TSh <?= number_format($patient_total_amount, 2) ?></strong></span>
                        <span>|</span>
                        <span>Balance: <strong class="amount" style="color: <?= $patient_total_balance > 0 ? '#fcd34d' : '#34d399' ?>;">
                            TSh <?= number_format($patient_total_balance, 2) ?>
                        </strong></span>
                    </div>
                    <button class="card-toggle" onclick="event.stopPropagation(); togglePatientCard(this.closest('.card-header'))">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body">
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
                                <th>Items</th>
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
                                $bill_doctor = $bill['doctor_name'] ?? $doctor_name;
                            ?>
                            <tr class="bill-row <?= $is_fully_paid ? 'bill-paid' : '' ?>" 
                                data-bill-id="<?= $bill['id'] ?>" 
                                data-balance="<?= $balance ?>" 
                                data-total="<?= $bill['total_amount'] ?>">
                                <td style="text-align:center;">
                                    <?php if (!$is_fully_paid && $balance > 0): ?>
                                        <input type="checkbox" class="bill-checkbox bill-select" 
                                               data-id="<?= $bill['id'] ?>" 
                                               data-patient-id="<?= $patient['patient_id'] ?>"
                                               onchange="updateSelectedTotal()">
                                    <?php else: ?>
                                        <span style="color:#059669; font-size:0.8rem;" title="All paid">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:#059669;">
                                        <?= htmlspecialchars($bill['bill_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs">
                                        <?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>
                                    </span>
                                    <span class="text-xs text-gray-400 block">
                                        <?= date('d/m/Y', strtotime($bill['created_at'])) ?>
                                    </span>
                                    <?php if ($bill_doctor && $bill_doctor !== 'Not Assigned'): ?>
                                        <span class="text-xs text-primary block">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($bill_doctor) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="expand-btn" onclick="toggleItems(this)">
                                        <i class="fas fa-chevron-right"></i>
                                        <?= $total_items ?> items
                                        <?php if ($paid_items > 0): ?>
                                            <span style="color:#059669;">(<?= $paid_items ?> paid)</span>
                                        <?php endif; ?>
                                        <?php if ($pending_items > 0): ?>
                                            <span style="color:#d97706;">(<?= $pending_items ?> pending)</span>
                                        <?php endif; ?>
                                    </button>
                                    <div class="items-container" style="display:none;">
                                        <?php foreach ($items as $item): 
                                            $is_paid = ($item['is_paid'] ?? 0) == 1;
                                            $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                        ?>
                                        <div class="item-row <?= $is_paid ? 'paid-item' : '' ?>">
                                            <span class="item-name">
                                                <?= htmlspecialchars($item['item_name']) ?>
                                                <span class="item-badge <?= $is_paid ? 'paid' : 'pending' ?>">
                                                    <?= $is_paid ? '✅ Paid' : '⏳ Pending' ?>
                                                </span>
                                            </span>
                                            <span class="item-price <?= $is_paid ? 'paid' : 'pending' ?>">
                                                TSh <?= number_format($price, 2) ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td style="text-align:right; font-weight:700; color:#059669; font-family:monospace;">
                                    <?= number_format($bill['total_amount'] ?? 0, 2) ?>
                                </td>
                                <td style="text-align:right; font-weight:600; color:<?= $balance > 0 ? '#dc2626' : '#059669' ?>; font-family:monospace;">
                                    <?= number_format($balance, 2) ?>
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
                                <td style="font-weight:600; color:#059669; font-family:monospace;">
                                    TSh <?= number_format($patient_total_amount, 2) ?>
                                </td>
                                <td style="font-weight:600; color:<?= $patient_total_balance > 0 ? '#dc2626' : '#059669' ?>; font-family:monospace;">
                                    TSh <?= number_format($patient_total_balance, 2) ?>
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
        <div class="text-center py-12">
            <i class="fas fa-check-circle text-5xl text-green-500 mb-4 block"></i>
            <h3 class="text-xl font-semibold text-gray-600">No Pending Bills</h3>
            <p class="text-gray-400 mt-2">All bills have been paid. Great job! 🎉</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PAYMENT CONTROLS - STICKY BOTTOM -->
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
            <label><i class="fas fa-percent"></i> Discount (TSh):</label>
            <input type="number" id="discountAmount" class="discount-input" placeholder="0.00" 
                   min="0" step="0.01" value="0" oninput="updateSelectedTotal()">
        </div>
        
        <div class="divider"></div>
        
        <div class="control-group">
            <label><i class="fas fa-hand-holding-heart"></i> Partial:</label>
            <input type="number" id="partialAmount" class="partial-input" placeholder="0.00" 
                   min="0" step="0.01" value="0" oninput="updateSelectedTotal()">
        </div>
        
        <div class="divider"></div>
        
        <!-- TOTAL DISPLAY -->
        <div class="total-display" id="totalDisplay">
            <div class="total-item">
                <span class="label">Total</span>
                <span class="value" id="displayTotal">TSh 0.00</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Discount</span>
                <span class="value" style="color:#d97706;" id="displayDiscount">TSh 0.00</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Grand Total</span>
                <span class="value grand" id="displayGrandTotal">TSh 0.00</span>
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

    <!-- Footer -->
    <footer class="footer" style="margin-top: 100px;">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Process Payments
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle toast-icon"></i>
    <div class="toast-content">
        <p class="toast-title" id="toastTitle">Notification</p>
        <p class="toast-message" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FIXED: Partial shows exact amount (8000) -->
<!-- ================================================================ -->
<script>
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
        if (container) {
            if (container.classList.contains('open')) {
                container.classList.remove('open');
                if (icon) icon.style.transform = 'rotate(0deg)';
            } else {
                container.classList.add('open');
                if (icon) icon.style.transform = 'rotate(90deg)';
            }
        }
    }

    // ================================================================
    // SELECT ALL BILLS FOR A PATIENT
    // ================================================================
    function selectPatientBills(checkbox, patientId) {
        var checkboxes = document.querySelectorAll('.bill-select[data-patient-id="' + patientId + '"]');
        checkboxes.forEach(function(cb) {
            cb.checked = checkbox.checked;
        });
        updateSelectedTotal();
    }

    // ================================================================
    // SELECT / DESELECT ALL BILLS
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
    // UPDATE SELECTED TOTAL - PARTIAL SHOWS EXACT AMOUNT
    // ================================================================
    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var count = checkboxes.length;
        var total_balance = 0;
        var total_amount = 0;
        var discount = parseFloat(document.getElementById('discountAmount').value) || 0;
        var partial = parseFloat(document.getElementById('partialAmount').value) || 0;
        
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
        
        // Update displays
        document.getElementById('selectedCountNum').textContent = count;
        document.getElementById('selectedTotal').textContent = total_balance.toFixed(2);
        document.getElementById('displayTotal').textContent = 'TSh ' + total_balance.toFixed(2);
        document.getElementById('displayDiscount').textContent = 'TSh ' + discount.toFixed(2);
        document.getElementById('displayGrandTotal').textContent = 'TSh ' + grand_total.toFixed(2);
        
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
            fullBtn.innerHTML = '<i class="fas fa-check-circle"></i> PAY FULL (TSh ' + grand_total.toFixed(2) + ')';
            cancelBtn.disabled = false;
            cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> CANCEL (' + count + ' bills)';
            
            // ================================================================
            // MUHIMU: Partial inaonyesha kiasi HALISI ulichoandika
            // Kwa mfano: ukiandika 8000, button inaonyesha 8000
            // ================================================================
            if (partial > 0 && partial <= total_balance) {
                partialBtn.disabled = false;
                // Onyesha kiasi halisi ulichoandika (sio kugawanywa)
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL (TSh ' + partial.toFixed(2) + ')';
            } else if (partial > total_balance) {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Amount exceeds balance';
            } else if (partial <= 0) {
                partialBtn.disabled = true;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Enter Partial Amount';
            } else {
                partialBtn.disabled = false;
                partialBtn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> PAY PARTIAL (TSh ' + partial.toFixed(2) + ')';
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
        var discount = parseFloat(document.getElementById('discountAmount').value) || 0;
        var partialAmount = parseFloat(document.getElementById('partialAmount').value) || 0;
        
        // Calculate total balance
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
        
        // PARTIAL PAYMENT - Inatumia kiasi HALISI ulichoandika
        if (type === 'partial') {
            if (partialAmount <= 0) {
                showToast('⚠️ Invalid Amount', 'Please enter a valid partial amount', 'warning');
                return;
            }
            if (partialAmount > totalBalance) {
                showToast('⚠️ Amount Exceeds', 'Partial amount exceeds total balance', 'warning');
                return;
            }
            if (discount > 0 && discount >= totalBalance) {
                showToast('⚠️ Discount', 'Discount cannot exceed or equal total balance', 'warning');
                return;
            }
            
            // MUHIMU: TUMIA KIASI HALISI (partialAmount) - HAKIWE KUGWAWANYWA
            var finalAmount = partialAmount;
            if (discount > 0) {
                var discountRatio = partialAmount / totalBalance;
                var discountApplied = discount * discountRatio;
                finalAmount = partialAmount - discountApplied;
                if (finalAmount < 0) finalAmount = 0;
            }
            
            if (!confirm('Pay TSh ' + partialAmount.toFixed(2) + ' for ' + billIds.length + ' bill(s)' + 
                        (discount > 0 ? ' (Discount: TSh ' + (partialAmount - finalAmount).toFixed(2) + ')' : '') + '?')) {
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
            
            if (!confirm('Pay TSh ' + grandTotal.toFixed(2) + ' for ' + billIds.length + ' bill(s)' + 
                        (discount > 0 ? ' (Discount: TSh ' + discount.toFixed(2) + ')' : '') + '?')) {
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
            // Tuma kiasi HALISI ulichoandika
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
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
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
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            var partialAmount = parseFloat(document.getElementById('partialAmount').value) || 0;
            if (partialAmount > 0) {
                processPayment('partial');
            } else {
                processPayment('full');
            }
        }
    });

    // ================================================================
    // AUTO-SELECT ALL WHEN DISCOUNT IS ADDED
    // ================================================================
    document.getElementById('discountAmount')?.addEventListener('input', function() {
        var val = parseFloat(this.value) || 0;
        if (val > 0) {
            var selected = document.querySelectorAll('.bill-select:checked');
            if (selected.length === 0) {
                selectAllBills();
            }
        }
        updateSelectedTotal();
    });

    document.getElementById('partialAmount')?.addEventListener('input', function() {
        updateSelectedTotal();
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedTotal();
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💰 Braick - Process Payments (FULLY FIXED)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📊 Total Patients: <?= $total_patients ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Bills: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Balance: <?= number_format($total_balance, 2) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Discount is TSh amount (not percentage)', 'font-size:12px; color:#34D399;');
    console.log('%c📊 Total + Grand Total displayed', 'font-size:12px; color:#34D399;');
    console.log('%c🔴 CANCEL button moves bills to cancelled status', 'font-size:12px; color:#DC2626;');
    console.log('%c👨‍⚕️ Doctor name shown in patient details', 'font-size:12px; color:#059669;');
    console.log('%c💵 Partial uses EXACT amount entered (8000 shows 8000)', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>