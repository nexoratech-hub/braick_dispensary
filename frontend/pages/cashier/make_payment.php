<?php
// ================================================================
// FILE: frontend/pages/cashier/process_payment.php
// CASHIER - PROCESS PAYMENT WITH PARTIAL PAYMENT SUPPORT
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
    $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
    $payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : 'full';
    $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
    
    // ================================================================
    // FULL PAYMENT - Pay entire bills
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
            
            foreach ($bill_ids as $bill_id) {
                $bill_id = (int)$bill_id;
                
                // Get bill details
                $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ? AND status != 'paid'");
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
                
                // Generate receipt number
                $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Update bill to paid
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET paid_amount = total_amount, 
                        balance = 0, 
                        status = 'paid',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$bill_id]);
                
                // Mark all items as paid
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET is_paid = 1, 
                        payment_status = 'paid', 
                        paid_at = NOW()
                    WHERE bill_id = ?
                ");
                $stmt->execute([$bill_id]);
                
                // Insert payment record
                $stmt = $db->prepare("
                    INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $receipt_number,
                    $bill_id,
                    $bill['patient_id'],
                    $remaining,
                    $payment_method,
                    $user_id,
                    $user_branch_id
                ]);
                
                $total_amount_paid += $remaining;
                $receipt_numbers[] = $receipt_number;
                $success_count++;
            }
            
            $message = $success_count . " bill(s) paid successfully! Total: TSh " . number_format($total_amount_paid);
            if (!empty($failed_bills)) {
                $message .= " Failed bills: " . implode(', ', $failed_bills);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'receipt_numbers' => $receipt_numbers,
                'total_paid' => $total_amount_paid,
                'count' => $success_count,
                'payment_type' => 'full'
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ================================================================
    // PARTIAL PAYMENT - Pay specific items only
    // ================================================================
    if ($action === 'partial_payment') {
        if (empty($item_ids) || !is_array($item_ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected for payment']);
            exit;
        }
        
        try {
            $total_selected = 0;
            $paid_items = [];
            $bill_id = 0;
            
            // Get items details - ONLY PENDING ITEMS (is_paid = 0)
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $stmt = $db->prepare("
                SELECT bi.*, pb.patient_id, pb.bill_number, pb.branch_id
                FROM bill_items bi
                JOIN patient_bills pb ON bi.bill_id = pb.id
                WHERE bi.id IN ($placeholders) AND (bi.is_paid = 0 OR bi.is_paid IS NULL)
            ");
            $stmt->execute($item_ids);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                echo json_encode(['success' => false, 'message' => 'No unpaid items selected']);
                exit;
            }
            
            $bill_id = $items[0]['bill_id'];
            $patient_id = $items[0]['patient_id'];
            $bill_number = $items[0]['bill_number'];
            
            // Calculate total for selected items
            foreach ($items as $item) {
                $item_price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                $total_selected += $item_price;
                $paid_items[] = $item['id'];
            }
            
            // Use calculated total
            $payment_amount = $total_selected;
            
            // Mark selected items as paid
            $item_placeholders = implode(',', array_fill(0, count($paid_items), '?'));
            $stmt = $db->prepare("
                UPDATE bill_items 
                SET is_paid = 1, 
                    payment_status = 'paid', 
                    paid_at = NOW()
                WHERE id IN ($item_placeholders)
            ");
            $stmt->execute($paid_items);
            
            // Generate receipt
            $receipt_number = 'RCP-PARTIAL-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insert payment
            $stmt = $db->prepare("
                INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $receipt_number,
                $bill_id,
                $patient_id,
                $payment_amount,
                $payment_method,
                $user_id,
                $user_branch_id,
                'Partial payment for ' . count($paid_items) . ' items'
            ]);
            
            // Update bill totals
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(total_price), 0) as total 
                FROM bill_items 
                WHERE bill_id = ? AND (is_paid = 0 OR is_paid IS NULL)
            ");
            $stmt->execute([$bill_id]);
            $remaining = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_balance = (float)$remaining['total'];
            
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET paid_amount = paid_amount + ?,
                    balance = ?,
                    status = CASE 
                        WHEN ? <= 0 THEN 'paid'
                        ELSE 'partial'
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$payment_amount, $new_balance, $new_balance, $bill_id]);
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Payment of TSh ' . number_format($payment_amount) . ' for ' . count($paid_items) . ' items completed!',
                'receipt_number' => $receipt_number,
                'total_paid' => $payment_amount,
                'items_paid' => count($paid_items),
                'remaining_balance' => $new_balance,
                'bill_id' => $bill_id,
                'payment_type' => 'partial'
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
// GET ALL BILLS FOR THIS BRANCH (Only Pending/Partial)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        v.visit_number,
        v.visit_type,
        v.visit_date,
        u.full_name as doctor_name,
        u2.full_name as created_by_name,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id
        ) as item_count,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND is_paid = 1
        ) as paid_item_count
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users u2 ON pb.created_by = u2.id
    WHERE pb.branch_id = ? AND pb.status != 'paid'
    ORDER BY pb.created_at ASC
");
$stmt->execute([$user_branch_id]);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL ITEMS WITH PRICES AND CORRECT STATUS
// ================================================================
$bill_items = [];
foreach ($bills as $bill) {
    $stmt = $db->prepare("
        SELECT bi.*, 
               CASE 
                   WHEN bi.is_paid = 1 OR bi.payment_status = 'paid' THEN 'paid'
                   ELSE 'pending'
               END as item_status,
               bi.total_price as price,
               bi.paid_at
        FROM bill_items bi
        WHERE bi.bill_id = ?
        ORDER BY bi.is_paid ASC, bi.created_at ASC
    ");
    $stmt->execute([$bill['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as &$item) {
        if (!isset($item['item_status'])) {
            $item['item_status'] = ($item['is_paid'] ?? 0) ? 'paid' : 'pending';
        }
        if (!isset($item['price']) || $item['price'] <= 0) {
            $item['price'] = $item['total_price'] ?? $item['unit_price'] ?? 0;
        }
    }
    $bill_items[$bill['id']] = $items;
}

// ================================================================
// GET PAID BILLS HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        v.visit_number,
        p.phone as patient_phone,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id
        ) as item_count
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    WHERE pb.branch_id = ? AND pb.status = 'paid'
    ORDER BY pb.updated_at DESC
    LIMIT 30
");
$stmt->execute([$user_branch_id]);
$paid_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PAYMENT HISTORY FOR PAID BILLS
// ================================================================
$payment_history = [];
foreach ($paid_bills as $paid) {
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE bill_id = ? 
        ORDER BY received_at DESC
    ");
    $stmt->execute([$paid['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payment_history[$paid['id']] = $payments;
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_bills = count($bills);
$total_amount = 0;
$total_pending_balance = 0;

foreach ($bills as $bill) {
    $total_amount += (float)$bill['total_amount'];
    $total_pending_balance += (float)$bill['balance'];
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       PROCESS PAYMENT STYLES - GREEN THEME
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
    
    .stat-box .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .page-title i { color: var(--primary) !important; }
    
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
    }
    
    .payment-controls .control-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .payment-controls .control-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .payment-controls select {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        cursor: pointer;
    }
    
    .payment-controls select:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
    }
    
    .payment-controls .selected-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
        padding: 4px 12px;
        background: var(--bg-body);
        border-radius: 20px;
        border: 1px solid var(--border-color);
    }
    
    .payment-controls .selected-count strong {
        color: var(--primary);
    }
    
    .bills-table-wrap {
        overflow-x: auto;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        margin-bottom: 20px;
    }
    
    .bills-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 900px;
    }
    
    .bills-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #059669;
        border-bottom: 3px solid #047857;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .bills-table thead th:first-child {
        border-radius: 8px 0 0 0;
        text-align: center;
    }
    
    .bills-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .bills-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .bills-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .bills-table tbody tr.selected td {
        background: #ecfdf5;
    }
    
    .bills-table tbody tr.bill-paid td {
        opacity: 0.7;
        background: #ecfdf5;
    }
    
    .bill-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #059669;
        border-radius: 4px;
    }
    
    .bill-checkbox:checked {
        background-color: #059669;
    }
    
    .bill-checkbox:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .bill-status {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 3px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .bill-status.pending {
        background: #fef3c7;
        color: #d97706;
    }
    
    .bill-status.partial {
        background: #fef3c7;
        color: #d97706;
    }
    
    .bill-status.paid {
        background: #d1fae5;
        color: #059669;
    }
    
    .bill-status.cancelled {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .amount-total {
        font-weight: 700;
        color: #059669;
        font-family: monospace;
    }
    
    .amount-balance {
        font-weight: 600;
        font-family: monospace;
    }
    
    .amount-balance.positive {
        color: #dc2626;
    }
    
    .amount-balance.zero {
        color: #059669;
    }
    
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
    
    .btn-success {
        background: #059669;
        color: white;
    }
    
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-warning {
        background: #d97706;
        color: white;
    }
    
    .btn-warning:hover {
        background: #b45309;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    
    .btn-danger {
        background: #dc2626;
        color: white;
    }
    
    .btn-danger:hover {
        background: #b91c1c;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #059669;
        color: #059669;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.75rem;
    }
    
    .btn-lg {
        padding: 10px 28px;
        font-size: 0.95rem;
    }
    
    .btn-block {
        width: 100%;
        justify-content: center;
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }
    
    .bill-items {
        position: relative;
    }
    
    .items-toggle {
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #059669;
        font-weight: 500;
        font-size: 0.75rem;
        padding: 2px 0;
        transition: color 0.2s;
    }
    
    .items-toggle:hover {
        color: #047857;
    }
    
    .items-toggle i {
        transition: transform 0.3s ease;
        font-size: 0.7rem;
    }
    
    .items-list {
        display: none;
        padding: 6px 0 6px 16px;
        border-left: 3px solid #059669;
        margin-top: 4px;
        background: var(--bg-body);
        border-radius: 0 4px 4px 0;
    }
    
    .items-list.open {
        display: block;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.75rem;
        border-bottom: 1px dashed var(--border-color);
    }
    
    .item-row:last-child {
        border-bottom: none;
    }
    
    .item-row.paid-item {
        background: #ecfdf5;
        border-bottom-color: #d1fae5;
    }
    
    .item-row.paid-item .item-name {
        text-decoration: line-through;
        color: #6b7280;
    }
    
    .item-row .item-name {
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .item-row .item-qty {
        color: var(--text-secondary);
        font-size: 0.65rem;
    }
    
    .item-row .item-price {
        font-weight: 600;
        font-family: monospace;
    }
    
    .item-row .item-price.paid {
        color: #059669;
    }
    
    .item-row .item-price.pending {
        color: #dc2626;
    }
    
    .item-badge {
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    
    .item-badge.paid {
        background: #d1fae5;
        color: #059669;
    }
    
    .item-badge.pending {
        background: #fef3c7;
        color: #d97706;
    }
    
    .item-checkbox {
        width: 14px;
        height: 14px;
        cursor: pointer;
        accent-color: #059669;
        margin-right: 6px;
    }
    
    .item-checkbox:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .partial-payment-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid #d97706;
        margin-bottom: 20px;
        display: none;
    }
    
    .partial-payment-section.show {
        display: block;
    }
    
    .partial-payment-section .partial-total {
        font-size: 1.2rem;
        font-weight: 700;
        color: #d97706;
    }
    
    .paid-bills-section {
        margin-top: 24px;
    }
    
    .paid-bills-section .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .paid-bills-section .section-title .badge {
        background: #d1fae5;
        color: #059669;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .payment-history-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.7rem;
        border-bottom: 1px dashed #e5e7eb;
        color: var(--text-secondary);
    }
    
    .payment-history-item:last-child {
        border-bottom: none;
    }
    
    .payment-history-item .amount {
        color: #059669;
        font-weight: 600;
        font-family: monospace;
    }
    
    .payment-history-item .method {
        font-size: 0.6rem;
        background: #e5e7eb;
        padding: 1px 8px;
        border-radius: 10px;
        color: #4b5563;
    }
    
    .payment-history-item .date {
        font-size: 0.6rem;
        color: #6b7280;
    }
    
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 20px;
        border-radius: 12px;
        z-index: 999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    
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
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .bills-table {
            font-size: 0.7rem;
            min-width: 700px;
        }
        .bills-table th,
        .bills-table td {
            padding: 6px 8px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .payment-controls {
            flex-direction: column;
            align-items: stretch;
        }
        .payment-controls .control-group {
            justify-content: center;
        }
        .payment-controls .btn {
            width: 100%;
            justify-content: center;
        }
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
            <input type="text" id="searchInput" placeholder="Search bills by patient name or bill number...">
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
                Select bills or individual items to pay
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-file-invoice mr-1"></i> <?= $total_bills ?> pending bill(s)
                </span>
                <span class="ml-2 inline-flex bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs border border-orange-200">
                    <i class="fas fa-money-bill mr-1"></i> Balance: <?= number_format($total_pending_balance, 2) ?>
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
            <p class="number orange"><?= $total_bills ?></p>
            <p class="label">Pending Bills</p>
        </div>
        <div class="stat-box">
            <p class="number red"><?= number_format($total_pending_balance, 2) ?></p>
            <p class="label">Total Balance</p>
        </div>
        <div class="stat-box">
            <p class="number purple"><?= number_format($total_amount, 2) ?></p>
            <p class="label">Total Amount</p>
        </div>
        <div class="stat-box">
            <p class="number green" id="selectedTotal">0.00</p>
            <p class="label">Selected Total</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT CONTROLS -->
    <!-- ================================================================ -->
    <div class="payment-controls" id="paymentControls">
        <div class="control-group">
            <label><i class="fas fa-hand-holding-usd"></i> Payment Method:</label>
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
        
        <div class="control-group">
            <span class="selected-count" id="selectedCount">Selected: <strong>0</strong> items</span>
        </div>
        
        <div class="control-group" style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;">
            <button onclick="togglePartialMode()" class="btn btn-warning btn-sm" id="partialModeBtn">
                <i class="fas fa-hand-holding-heart"></i> Partial Payment
            </button>
            <button onclick="selectAllItems()" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <button onclick="deselectAll()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Deselect All
            </button>
            <button onclick="processPayment()" class="btn btn-success" id="completeBtn">
                <i class="fas fa-check-circle"></i> Complete Payment
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PARTIAL PAYMENT SECTION -->
    <!-- ================================================================ -->
    <div class="partial-payment-section" id="partialSection">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h4 style="margin:0; color:#d97706;">
                    <i class="fas fa-hand-holding-heart"></i> Partial Payment Mode
                </h4>
                <p style="margin:4px 0 0; font-size:0.8rem; color:var(--text-secondary);">
                    Select individual unpaid items to pay. Paid items are disabled.
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:0.8rem; color:var(--text-secondary);">Total Selected:</span>
                <span class="partial-total" id="partialTotal">TSh 0.00</span>
                <button onclick="processPartialPayment()" class="btn btn-warning" id="partialPayBtn" disabled>
                    <i class="fas fa-hand-holding-usd"></i> Select Items First
                </button>
                <button onclick="togglePartialMode()" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS TABLE WITH ITEMS -->
    <!-- ================================================================ -->
    <div class="bills-table-wrap">
        <table class="bills-table" id="billsTable">
            <thead>
                <tr>
                    <th style="text-align:center; width:40px;">
                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this.checked)" title="Select All Bills">
                    </th>
                    <th style="width:45px;">#</th>
                    <th>Bill Number</th>
                    <th>Patient</th>
                    <th style="min-width:250px;">Items / Services</th>
                    <th style="text-align:right;">Total (TSh)</th>
                    <th style="text-align:right;">Balance (TSh)</th>
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bills) > 0): ?>
                    <?php $counter = 1; foreach ($bills as $bill): 
                        $balance = (float)$bill['balance'];
                        $items = $bill_items[$bill['id']] ?? [];
                        $paid_count = 0;
                        $pending_count = 0;
                        $has_pending = false;
                        $all_paid = true;
                        $total_pending_price = 0;
                        
                        foreach ($items as $item) {
                            if ($item['item_status'] === 'paid') {
                                $paid_count++;
                            } else {
                                $pending_count++;
                                $has_pending = true;
                                $all_paid = false;
                                $total_pending_price += (float)($item['price'] ?? $item['total_price'] ?? 0);
                            }
                        }
                        
                        $row_class = '';
                        if ($balance <= 0) {
                            $row_class = 'bill-paid';
                        }
                    ?>
                        <tr class="bill-row <?= $row_class ?>" data-bill-id="<?= $bill['id'] ?>" data-balance="<?= $balance ?>" data-total="<?= $bill['total_amount'] ?>">
                            <td style="text-align:center;">
                                <?php if ($has_pending && $balance > 0): ?>
                                    <input type="checkbox" class="bill-checkbox bill-select" data-id="<?= $bill['id'] ?>" onchange="updateSelectedTotal()">
                                <?php else: ?>
                                    <span style="color:#059669; font-size:0.8rem;" title="All items paid">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= $counter++ ?></td>
                            <td>
                                <span class="font-mono text-xs font-semibold" style="color:#059669;"><?= htmlspecialchars($bill['bill_number']) ?></span>
                            </td>
                            <td>
                                <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <!-- Items Expandable -->
                                <div class="bill-items">
                                    <div class="items-toggle" onclick="toggleItems(this)">
                                        <i class="fas fa-chevron-right" style="transition:transform 0.3s;"></i>
                                        <span>
                                            <?= count($items) ?> item(s)
                                            <?php if ($paid_count > 0): ?>
                                                <span style="color:#059669; font-weight:600;">(<?= $paid_count ?> paid)</span>
                                            <?php endif; ?>
                                            <?php if ($pending_count > 0): ?>
                                                <span style="color:#d97706; font-weight:600;">(<?= $pending_count ?> pending)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="items-list" style="display:none; padding:6px 0 6px 16px; border-left:3px solid #059669; margin-top:4px;">
                                        <?php foreach ($items as $item): 
                                            $is_paid = $item['item_status'] === 'paid';
                                            $price = (float)($item['price'] ?? $item['total_price'] ?? 0);
                                            $quantity = (int)($item['quantity'] ?? 1);
                                            $row_class = $is_paid ? 'paid-item' : '';
                                        ?>
                                            <div class="item-row <?= $row_class ?>" style="display:flex; justify-content:space-between; align-items:center; padding:4px 0; font-size:0.75rem; border-bottom:1px dashed <?= $is_paid ? '#d1fae5' : '#e5e7eb' ?>;">
                                                <div style="display:flex; align-items:center; gap:8px; flex:1;">
                                                    <?php if (!$is_paid): ?>
                                                        <input type="checkbox" class="item-checkbox item-select" data-item-id="<?= $item['id'] ?>" data-price="<?= $price ?>" onchange="updatePartialTotal()">
                                                    <?php else: ?>
                                                        <span style="width:14px; display:inline-block;"></span>
                                                    <?php endif; ?>
                                                    <span class="item-name" style="font-weight:500; <?= $is_paid ? 'text-decoration:line-through; color:#6b7280;' : '' ?>">
                                                        <?= htmlspecialchars($item['item_name']) ?>
                                                    </span>
                                                    <span class="item-qty" style="color:#6b7280; font-size:0.65rem;">x<?= $quantity ?></span>
                                                    <?php if ($is_paid): ?>
                                                        <span class="item-badge paid">
                                                            <i class="fas fa-check-circle"></i> Paid
                                                        </span>
                                                        <span style="font-size:0.55rem; color:#6b7280;">
                                                            <?= date('d/m/Y H:i', strtotime($item['paid_at'] ?? 'now')) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="item-badge pending">
                                                            <i class="fas fa-clock"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="item-price <?= $is_paid ? 'paid' : 'pending' ?>" style="font-weight:600; font-family:monospace; <?= $is_paid ? 'color:#059669;' : 'color:#dc2626;' ?>">
                                                    <?= number_format($price, 2) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($pending_count > 0): ?>
                                            <div style="padding:6px 0; border-top:2px solid #d97706; margin-top:4px; display:flex; justify-content:space-between; font-weight:700; font-size:0.75rem;">
                                                <span style="color:#d97706;">Pending Total:</span>
                                                <span style="color:#d97706; font-family:monospace;"><?= number_format($total_pending_price, 2) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
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
                                <?php elseif ($has_pending): ?>
                                    <span class="bill-status partial">🔄 Partial</span>
                                <?php else: ?>
                                    <span class="bill-status pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-400">
                            <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                            <p>No pending bills found!</p>
                            <p class="text-sm mt-1">All bills have been paid</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================ -->
    <!-- PAID BILLS HISTORY -->
    <!-- ================================================================ -->
    <?php if (count($paid_bills) > 0): ?>
    <div class="paid-bills-section">
        <div class="section-title">
            <i class="fas fa-history text-green-600"></i>
            Payment History
            <span class="badge"><?= count($paid_bills) ?></span>
        </div>
        
        <?php foreach ($paid_bills as $paid):
            $payments = $payment_history[$paid['id']] ?? [];
            $paid_items_count = $paid['item_count'] ?? 0;
        ?>
        <div class="bills-table-wrap" style="border-color: #d1fae5; margin-bottom:12px;">
            <table class="bills-table" style="min-width: 600px;">
                <thead>
                    <tr style="background: #059669;">
                        <th>Bill Number</th>
                        <th>Patient</th>
                        <th style="text-align:right;">Total (TSh)</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="font-mono text-xs text-green-600"><?= htmlspecialchars($paid['bill_number']) ?></span></td>
                        <td><?= htmlspecialchars($paid['patient_name']) ?></td>
                        <td style="text-align:right; color:#059669; font-weight:600; font-family:monospace;"><?= number_format($paid['total_amount'] ?? 0, 2) ?></td>
                        <td style="text-align:center;">
                            <span class="bill-status paid">✅ Paid</span>
                        </td>
                        <td style="text-align:center; font-size:0.7rem; color:#6b7280;">
                            <?= date('d/m/Y H:i', strtotime($paid['updated_at'])) ?>
                        </td>
                    </tr>
                    <?php if (!empty($payments)): ?>
                    <tr>
                        <td colspan="5" style="padding:0;">
                            <div style="padding:8px 14px; background:#f8fafc;">
                                <div style="font-size:0.65rem; font-weight:600; color:#6b7280; margin-bottom:4px;">
                                    <i class="fas fa-receipt mr-1"></i> Payment Receipts
                                </div>
                                <?php foreach ($payments as $payment): ?>
                                <div class="payment-history-item">
                                    <span><?= htmlspecialchars($payment['receipt_number']) ?></span>
                                    <span class="method"><?= $payment['payment_method'] ?></span>
                                    <span class="amount"><?= number_format($payment['amount'], 2) ?></span>
                                    <span class="date"><?= date('d/m/Y H:i', strtotime($payment['received_at'])) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // PARTIAL PAYMENT MODE
    // ================================================================
    var partialMode = false;
    
    function togglePartialMode() {
        partialMode = !partialMode;
        var section = document.getElementById('partialSection');
        var btn = document.getElementById('partialModeBtn');
        var itemCheckboxes = document.querySelectorAll('.item-select');
        
        if (partialMode) {
            section.classList.add('show');
            btn.innerHTML = '<i class="fas fa-times"></i> Cancel Partial';
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-danger');
            // Show item checkboxes (only for pending items)
            itemCheckboxes.forEach(function(cb) {
                cb.style.display = 'inline-block';
            });
            // Hide bill checkboxes
            document.querySelectorAll('.bill-select').forEach(function(cb) {
                cb.style.display = 'none';
            });
            document.getElementById('selectAllCheckbox').style.display = 'none';
            updatePartialTotal();
        } else {
            section.classList.remove('show');
            btn.innerHTML = '<i class="fas fa-hand-holding-heart"></i> Partial Payment';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-warning');
            // Hide item checkboxes
            itemCheckboxes.forEach(function(cb) {
                cb.style.display = 'none';
                cb.checked = false;
            });
            // Show bill checkboxes
            document.querySelectorAll('.bill-select').forEach(function(cb) {
                cb.style.display = 'inline-block';
            });
            document.getElementById('selectAllCheckbox').style.display = 'inline-block';
            updateSelectedTotal();
        }
    }
    
    function updatePartialTotal() {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var total = 0;
        var count = checkboxes.length;
        
        checkboxes.forEach(function(cb) {
            total += parseFloat(cb.dataset.price || 0);
        });
        
        document.getElementById('partialTotal').textContent = 'TSh ' + total.toFixed(2);
        document.getElementById('selectedCount').innerHTML = 'Selected: <strong>' + count + '</strong> items';
        document.getElementById('selectedTotal').textContent = total.toFixed(2);
        
        var btn = document.getElementById('partialPayBtn');
        if (count === 0) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Select Items First';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Pay Selected Items (TSh ' + total.toFixed(2) + ')';
        }
    }
    
    // ================================================================
    // SELECT ALL ITEMS (Partial Mode) - Only pending items
    // ================================================================
    function selectAllItems() {
        if (partialMode) {
            var checkboxes = document.querySelectorAll('.item-select:not(:checked)');
            checkboxes.forEach(function(cb) {
                cb.checked = true;
            });
            updatePartialTotal();
        } else {
            var checkboxes = document.querySelectorAll('.bill-select:not(:checked)');
            checkboxes.forEach(function(cb) {
                cb.checked = true;
            });
            updateSelectedTotal();
        }
    }
    
    // ================================================================
    // DESELECT ALL
    // ================================================================
    function deselectAll() {
        if (partialMode) {
            document.querySelectorAll('.item-select').forEach(function(cb) {
                cb.checked = false;
            });
            updatePartialTotal();
        } else {
            document.querySelectorAll('.bill-select').forEach(function(cb) {
                cb.checked = false;
            });
            document.getElementById('selectAllCheckbox').checked = false;
            updateSelectedTotal();
        }
    }
    
    // ================================================================
    // PROCESS PARTIAL PAYMENT
    // ================================================================
    function processPartialPayment() {
        var checkboxes = document.querySelectorAll('.item-select:checked');
        var itemIds = [];
        var totalAmount = 0;
        
        checkboxes.forEach(function(cb) {
            itemIds.push(parseInt(cb.dataset.itemId));
            totalAmount += parseFloat(cb.dataset.price || 0);
        });
        
        if (itemIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one unpaid item to pay', 'warning');
            return;
        }
        
        // Confirm payment
        if (!confirm('Pay TSh ' + totalAmount.toFixed(2) + ' for ' + itemIds.length + ' item(s)?')) {
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var btn = document.getElementById('partialPayBtn');
        var originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'partial_payment');
        formData.append('payment_method', paymentMethod);
        formData.append('partial_amount', totalAmount);
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
    }
    
    // ================================================================
    // TOGGLE ITEMS EXPAND
    // ================================================================
    function toggleItems(element) {
        var list = element.parentElement.querySelector('.items-list');
        var icon = element.querySelector('.fa-chevron-right');
        if (list) {
            if (list.style.display === 'none' || list.style.display === '') {
                list.style.display = 'block';
                if (icon) icon.style.transform = 'rotate(90deg)';
            } else {
                list.style.display = 'none';
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        }
    }
    
    // ================================================================
    // SELECT / DESELECT ALL BILLS
    // ================================================================
    function toggleSelectAll(checked) {
        var checkboxes = document.querySelectorAll('.bill-select:not(:disabled)');
        checkboxes.forEach(function(cb) {
            cb.checked = checked;
        });
        updateSelectedTotal();
    }
    
    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var count = checkboxes.length;
        var balance = 0;
        var total = 0;
        
        checkboxes.forEach(function(cb) {
            var row = cb.closest('tr');
            if (row) {
                var bal = parseFloat(row.dataset.balance || 0);
                balance += bal;
                var tot = parseFloat(row.dataset.total || 0);
                total += tot;
            }
        });
        
        document.getElementById('selectedCount').innerHTML = 'Selected: <strong>' + count + '</strong> bills';
        document.getElementById('selectedTotal').textContent = balance.toFixed(2);
        
        var selectAll = document.getElementById('selectAllCheckbox');
        var allCheckboxes = document.querySelectorAll('.bill-select:not(:disabled)');
        var allChecked = true;
        allCheckboxes.forEach(function(cb) {
            if (!cb.checked) allChecked = false;
        });
        if (selectAll) {
            selectAll.checked = allChecked && allCheckboxes.length > 0;
        }
    }
    
    // ================================================================
    // PROCESS FULL PAYMENT
    // ================================================================
    function processPayment() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var billIds = [];
        checkboxes.forEach(function(cb) {
            billIds.push(parseInt(cb.dataset.id));
        });
        
        if (billIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one bill to pay', 'info');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var btn = document.getElementById('completeBtn');
        var originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'complete_payment');
        formData.append('payment_method', paymentMethod);
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
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            if (partialMode) {
                processPartialPayment();
            } else {
                processPayment();
            }
        }
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
    console.log('%c💰 Braick - Process Payments (Full + Partial)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📊 Total Bills: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Balance: <?= number_format($total_pending_balance, 2) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Select bills (full) or items (partial) to pay', 'font-size:12px; color:#34D399;');
    console.log('%c🔄 Click "Partial Payment" to switch to item selection mode', 'font-size:12px; color:#34D399;');
    console.log('%c⌨️ Ctrl+Enter to process payment', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>