<?php
// ================================================================
// FILE: frontend/api/get_cashier_sidebar_stats.php
// CASHIER SIDEBAR STATS - AUTO-UPDATE
// FIXED: Added total_paid (all time) and paid_today
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

$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// FETCH ALL SIDEBAR STATS
// ================================================================

// 1. Pending Bills
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Partial Payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'partial'");
$stmt->execute([$user_branch_id]);
$partial_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Paid Today (Today's paid bills)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$paid_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Total Paid Bills (All time - including previous days)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status = 'paid'
");
$stmt->execute([$user_branch_id]);
$total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Patients Waiting (pending + partial)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND status IN ('pending', 'partial')
");
$stmt->execute([$user_branch_id]);
$patients_waiting = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Total Bills Today
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patient_bills 
    WHERE branch_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$total_bills_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. Total Revenue Today
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM patient_bills 
    WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$revenue_today = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 8. Total Revenue All Time
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM patient_bills 
    WHERE branch_id = ? AND status = 'paid'
");
$stmt->execute([$user_branch_id]);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'pending_bills' => $pending_bills,
    'partial_payments' => $partial_payments,
    'paid_today' => $paid_today,
    'total_paid' => $total_paid,
    'patients_waiting' => $patients_waiting,
    'total_bills_today' => $total_bills_today,
    'revenue_today' => $revenue_today,
    'total_revenue' => $total_revenue
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
    'pending_bills' => (int)$pending_bills,
    'partial_payments' => (int)$partial_payments,
    'paid_today' => (int)$paid_today,
    'total_paid' => (int)$total_paid,
    'patients_waiting' => (int)$patients_waiting,
    'total_bills_today' => (int)$total_bills_today,
    'revenue_today' => (float)$revenue_today,
    'total_revenue' => (float)$total_revenue,
    'timestamp' => date('Y-m-d H:i:s'),
    'branch_id' => $user_branch_id,
    'branch_name' => $_SESSION['branch_name'] ?? 'Dodoma'
]);
?>