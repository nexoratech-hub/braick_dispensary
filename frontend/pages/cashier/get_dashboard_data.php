<?php
// ================================================================
// FILE: frontend/pages/cashier/get_dashboard_data.php
// AJAX ENDPOINT - Get cashier dashboard data
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

$cashier_id = $_SESSION['user_id'];
$cashier_branch_id = $_SESSION['branch_id'] ?? 1;
$today = date('Y-m-d');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// ================================================================
// GET ALL STATS
// ================================================================
try {
    // 1. Today Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Pending Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$cashier_branch_id]);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 3. Cancelled Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'cancelled'
    ");
    $stmt->execute([$cashier_branch_id]);
    $cancelled_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. Total Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills WHERE branch_id = ?
    ");
    $stmt->execute([$cashier_branch_id]);
    $total_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 5. Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$cashier_branch_id]);
    $paid_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 6. Partial Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance), 0) as total_balance
        FROM bills 
        WHERE branch_id = ? AND status = 'partial'
    ");
    $stmt->execute([$cashier_branch_id]);
    $partial_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 7. Expenses
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$cashier_branch_id]);
    $expenses = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 8. Payment History
    $stmt = $db->prepare("
        SELECT 
            b.id as bill_id,
            b.bill_number,
            b.patient_id,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.status,
            b.payment_method,
            b.updated_at,
            p.full_name as patient_name,
            p.patient_id as patient_code
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        WHERE b.branch_id = ?
        AND b.paid_amount > 0
        ORDER BY b.updated_at DESC
        LIMIT 15
    ");
    $stmt->execute([$cashier_branch_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add time_ago to each payment
    foreach ($payment_history as &$payment) {
        $payment['time_ago'] = time_ago($payment['updated_at'] ?? '');
    }
    
    // 9. Payment Methods Today
    $stmt = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
        GROUP BY payment_method
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 10. Today's Items
    $stmt = $db->prepare("
        SELECT 
            bi.item_type,
            COUNT(DISTINCT bi.bill_id) as bill_count,
            COUNT(bi.id) as item_count,
            COALESCE(SUM(bi.final_price), 0) as total_amount
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.branch_id = ?
        AND DATE(b.created_at) = ?
        GROUP BY bi.item_type
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $today_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // RETURN JSON
    // ================================================================
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'today_payments_count' => (int)($today_payments['count'] ?? 0),
        'today_payments_total' => (float)($today_payments['total'] ?? 0),
        'pending_bills' => (int)($pending_bills['count'] ?? 0),
        'pending_bills_total' => (float)($pending_bills['total'] ?? 0),
        'cancelled_bills' => (int)($cancelled_bills['count'] ?? 0),
        'cancelled_bills_total' => (float)($cancelled_bills['total'] ?? 0),
        'total_bills' => (int)($total_bills['count'] ?? 0),
        'total_bills_amount' => (float)($total_bills['total'] ?? 0),
        'paid_bills' => (int)($paid_bills['count'] ?? 0),
        'paid_bills_total' => (float)($paid_bills['total'] ?? 0),
        'partial_bills' => (int)($partial_bills['count'] ?? 0),
        'partial_bills_paid' => (float)($partial_bills['total_paid'] ?? 0),
        'partial_bills_balance' => (float)($partial_bills['total_balance'] ?? 0),
        'expenses_count' => (int)($expenses['count'] ?? 0),
        'expenses_total' => (float)($expenses['total'] ?? 0),
        'history_count' => count($payment_history),
        'payment_history' => $payment_history,
        'payment_methods' => $payment_methods,
        'today_items' => $today_items,
        'timestamp' => date('h:i:s A')
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ================================================================
// HELPER FUNCTION
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'Just now';
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);
    
    if ($diff->days > 7) return date('M d, Y', strtotime($timestamp));
    if ($diff->days > 0) return $diff->days . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}