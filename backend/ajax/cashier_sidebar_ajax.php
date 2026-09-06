<?php
// ================================================================
// FILE: backend/api/cashier_sidebar_ajax.php
// CASHIER SIDEBAR - DIRECT AJAX (NO EXTERNAL API)
// Gets data directly from database with AJAX
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role
if (!in_array($_SESSION['role'], ['cashier', 'reception', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

// Get branch ID
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $_SESSION['branch_id'] ?? 0;
$hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) && $_POST['force_update'] == '1';

if ($branch_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
    exit;
}

// Include database
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ================================================================
// GET ALL STATISTICS - DIRECT FROM DATABASE
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$total_paid = 0;
$total_expenses = 0;
$patients_waiting = 0;

try {
    // 1. Pending Bills
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$branch_id]);
    $pending_bills = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 2. Partial Payments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
    $stmt->execute([$branch_id]);
    $partial_payments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 3. Paid Bills
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid'");
    $stmt->execute([$branch_id]);
    $total_paid = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 4. Total Expenses
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE branch_id = ? AND status = 'paid'");
    $stmt->execute([$branch_id]);
    $total_expenses = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // 5. Patients Waiting (distinct patients with pending or partial bills)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
    $stmt->execute([$branch_id]);
    $patients_waiting = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    error_log("Cashier sidebar stats error: " . $e->getMessage());
}

// ================================================================
// GENERATE HASH AND CHECK CHANGES
// ================================================================
$data = [
    'pending_bills' => $pending_bills,
    'partial_payments' => $partial_payments,
    'paid_bills' => $total_paid,
    'total_expenses' => round($total_expenses, 2),
    'patients_waiting' => $patients_waiting
];

$new_hash = md5(json_encode($data));
$has_changed = ($hash !== $new_hash || $force_update);

// ================================================================
// RESPONSE
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'has_changed' => $has_changed,
    'hash' => $new_hash,
    'data' => $data,
    'summary' => [
        'pending_bills' => $pending_bills,
        'partial_payments' => $partial_payments,
        'paid_bills' => $total_paid,
        'total_expenses' => number_format($total_expenses, 2),
        'patients_waiting' => $patients_waiting
    ],
    'timestamp' => date('H:i:s'),
    'branch_id' => $branch_id
]);
exit;
?>