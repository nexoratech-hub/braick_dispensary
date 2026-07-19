<?php
// ================================================================
// FILE: frontend/api/get_cashier_stats.php
// CASHIER STATS API - RETURNS JSON FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE CASHIER DODOMA (ID: 10) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// FETCH ALL STATISTICS FOR CASHIER
// ================================================================

// 1. Pending Bills (including partial)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status IN ('pending', 'partial')
");
$stmt->execute([$user_branch_id]);
$pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Payments
$stmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
$today_payments = $today_payments_data['count'] ?? 0;
$today_revenue = $today_payments_data['total'] ?? 0;

// 3. Total Bills
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Paid Bills
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status = 'paid'
");
$stmt->execute([$user_branch_id]);
$paid_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Today's Receipts
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM receipts 
    WHERE DATE(printed_at) = ?
");
$stmt->execute([$today]);
$today_receipts = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Total Patients with bills
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM patient_bills 
    WHERE branch_id = ?
");
$stmt->execute([$user_branch_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. Pending Bills List (for table)
$stmt = $db->prepare("
    SELECT pb.id, pb.bill_number, pb.total_amount, pb.balance, pb.status, pb.created_at,
           p.full_name as patient_name, p.patient_id,
           v.visit_type,
           u.full_name as doctor_name
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE pb.branch_id = ? AND pb.status IN ('pending', 'partial')
    ORDER BY pb.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$pending_bills_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Recent Payments
$stmt = $db->prepare("
    SELECT p.*, pb.bill_number, pb.total_amount,
           pat.full_name as patient_name, pat.patient_id,
           u.full_name as cashier_name
    FROM payments p
    JOIN patient_bills pb ON p.bill_id = pb.id
    JOIN patients pat ON pb.patient_id = pat.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.branch_id = ?
    ORDER BY p.received_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Payment Methods (today)
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
    GROUP BY payment_method
");
$stmt->execute([$user_branch_id, $today]);
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. Patients with Bills
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id,
        (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND branch_id = ? AND status IN ('pending', 'partial')) as pending_bills_count,
        (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND branch_id = ? AND status = 'paid') as paid_bills_count
    FROM patients p
    WHERE p.branch_id = ?
    AND EXISTS (SELECT 1 FROM patient_bills WHERE patient_id = p.id AND branch_id = ?)
    ORDER BY p.full_name
    LIMIT 10
");
$stmt->execute([$user_branch_id, $user_branch_id, $user_branch_id, $user_branch_id]);
$patients_with_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'pending_bills' => $pending_bills,
    'today_payments' => $today_payments,
    'today_revenue' => $today_revenue,
    'total_bills' => $total_bills,
    'paid_bills' => $paid_bills,
    'today_receipts' => $today_receipts,
    'total_patients' => $total_patients,
    'pending_bills_count' => count($pending_bills_list),
    'recent_payments_count' => count($recent_payments),
    'payment_methods_count' => count($payment_methods),
    'patients_with_bills_count' => count($patients_with_bills)
];

$data_hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'stats' => [
        'pending_bills' => $pending_bills,
        'today_payments' => $today_payments,
        'today_revenue' => $today_revenue,
        'total_bills' => $total_bills,
        'paid_bills' => $paid_bills,
        'today_receipts' => $today_receipts,
        'total_patients' => $total_patients,
        'pending_bills_count' => count($pending_bills_list)
    ],
    'lists' => [
        'pending_bills' => $pending_bills_list,
        'recent_payments' => $recent_payments,
        'payment_methods' => $payment_methods,
        'patients_with_bills' => $patients_with_bills
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>