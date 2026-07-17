<?php
// ================================================================
// FILE: frontend/api/get_cashier_stats.php
// RETURNS CASHIER STATISTICS FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'cashier';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// FETCH CASHIER STATISTICS
// ================================================================

// 1. Pending Bills
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status IN ('pending', 'partial')
");
$stmt->execute([$user_branch_id]);
$pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Revenue
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM patient_bills 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'paid'
");
$stmt->execute([$user_branch_id, $today]);
$today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 3. Today's Payments
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Total Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Total Bills
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Paid Bills
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status = 'paid'
");
$stmt->execute([$user_branch_id]);
$paid_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. Pending Bills List (for display)
$stmt = $db->prepare("
    SELECT pb.*, p.full_name as patient_name, p.patient_id, p.phone,
           v.visit_number, v.visit_type, v.created_at as visit_date,
           u.full_name as doctor_name
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE pb.branch_id = ? AND pb.status IN ('pending', 'partial')
    ORDER BY pb.created_at DESC
    LIMIT 50
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
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.branch_id = ?
    ORDER BY p.received_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Today's Revenue Breakdown by Payment Method
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
    GROUP BY payment_method
");
$stmt->execute([$user_branch_id, $today]);
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'pending_bills' => $pending_bills,
    'today_revenue' => $today_revenue,
    'today_payments' => $today_payments,
    'total_patients' => $total_patients,
    'total_bills' => $total_bills,
    'paid_bills' => $paid_bills,
    'pending_bills_count' => count($pending_bills_list),
    'recent_payments_count' => count($recent_payments)
];
$data_hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'timestamp' => date('Y-m-d H:i:s'),
    'branch_id' => $user_branch_id,
    'branch_name' => $_SESSION['branch_name'] ?? 'Dodoma',
    'user_role' => $user_role,
    'stats' => [
        'pending_bills' => (int)$pending_bills,
        'today_revenue' => (float)$today_revenue,
        'today_payments' => (int)$today_payments,
        'total_patients' => (int)$total_patients,
        'total_bills' => (int)$total_bills,
        'paid_bills' => (int)$paid_bills,
        'pending_bills_count' => count($pending_bills_list)
    ],
    'lists' => [
        'pending_bills' => $pending_bills_list,
        'recent_payments' => $recent_payments,
        'payment_methods' => $payment_methods
    ]
]);
?>