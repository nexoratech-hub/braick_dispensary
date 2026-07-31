<?php
// ================================================================
// FILE: frontend/pages/admin/get_sidebar_stats.php
// AJAX ENDPOINT - GET SIDEBAR STATISTICS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH FILTER
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

// ================================================================
// FUNCTION TO GET COUNT SAFELY
// ================================================================
function getCount($db, $table, $branch_id, $extra_conditions = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM $table WHERE 1=1 ";
        $params = [];
        
        if ($branch_id !== 'all') {
            $sql .= " AND branch_id = ?";
            $params[] = (int)$branch_id;
        }
        
        if (!empty($extra_conditions)) {
            $sql .= " AND $extra_conditions";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// ================================================================
// GET ALL STATISTICS
// ================================================================

// Employees (role != admin)
$total_employees = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $total_employees = 0;
}

// Patients
$total_patients = getCount($db, 'patients', $selected_branch_id);
$today_patients = getCount($db, 'patients', $selected_branch_id, "DATE(created_at) = CURDATE()");

// Doctors
$total_doctors = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $total_doctors = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $total_doctors = 0;
}

// Module counts
$pharmacy_count = getCount($db, 'users', $selected_branch_id, "role = 'pharmacy' AND status = 'active'");
$reception_count = getCount($db, 'users', $selected_branch_id, "role = 'reception' AND status = 'active'");
$laboratory_count = getCount($db, 'users', $selected_branch_id, "role = 'laboratory' AND status = 'active'");
$cashier_count = getCount($db, 'users', $selected_branch_id, "role = 'cashier' AND status = 'active'");

// Services
$total_services = getCount($db, 'bill_items', $selected_branch_id, "status != 'cancelled'");
$today_services = getCount($db, 'bill_items', $selected_branch_id, "status != 'cancelled' AND DATE(created_at) = CURDATE()");

// Branches
$total_branches = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $total_branches = 0;
}

// Pending prescriptions
$pending_prescriptions = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions p 
            INNER JOIN patient_bills pb ON p.id = pb.prescription_id 
            WHERE p.status = 'pending' AND pb.branch_id = ?
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    }
    $pending_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// Pending lab tests
$pending_lab_tests = getCount($db, 'lab_tests', $selected_branch_id, "status = 'pending'");

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'total_employees' => $total_employees,
    'total_patients' => $total_patients,
    'today_patients' => $today_patients,
    'total_doctors' => $total_doctors,
    'pharmacy_count' => $pharmacy_count,
    'reception_count' => $reception_count,
    'laboratory_count' => $laboratory_count,
    'cashier_count' => $cashier_count,
    'total_services' => $total_services,
    'today_services' => $today_services,
    'total_branches' => $total_branches,
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests
]);