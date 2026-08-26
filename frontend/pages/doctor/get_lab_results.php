<?php
// ================================================================
// FILE: frontend/pages/doctor/get_lab_results.php
// DOCTOR - GET LAB RESULTS (AJAX API) - INSTANT UPDATE
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in',
        'redirect' => '../login.php'
    ]);
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Doctor';
$user_role = $_SESSION['role'];
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PARAMETERS
// ================================================================
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$last_hash = isset($_GET['hash']) ? $_GET['hash'] : '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// GET LAB TESTS FOR SPECIFIC VISIT OR ALL
// ================================================================
if ($visit_id > 0) {
    // Get tests for specific visit (for consultation page)
    if ($is_admin) {
        $stmt = $db->prepare("
            SELECT lt.*, 
                   p.full_name as patient_name, p.patient_id, p.phone,
                   u.full_name as doctor_name, u.specialty,
                   v.visit_number,
                   lab.full_name as lab_technician_name,
                   me.equipment_name as equipment_name,
                   me.batch_number as equipment_batch
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            LEFT JOIN users lab ON lt.lab_technician_id = lab.id
            LEFT JOIN medical_equipment me ON lt.equipment_used = me.id
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_id]);
    } else {
        $stmt = $db->prepare("
            SELECT lt.*, 
                   p.full_name as patient_name, p.patient_id, p.phone,
                   u.full_name as doctor_name, u.specialty,
                   v.visit_number,
                   lab.full_name as lab_technician_name,
                   me.equipment_name as equipment_name,
                   me.batch_number as equipment_batch
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            LEFT JOIN users lab ON lt.lab_technician_id = lab.id
            LEFT JOIN medical_equipment me ON lt.equipment_used = me.id
            WHERE lt.visit_id = ? AND lt.doctor_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_id, $user_id]);
    }
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get visit status
    $stmt = $db->prepare("SELECT status, patient_id FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    $visit_status = $visit['status'] ?? 'pending';
    $visit_patient_id = $visit['patient_id'] ?? 0;
    
} else {
    // Get all tests for dashboard
    if ($is_admin) {
        $query = "
            SELECT lt.*, 
                   p.full_name as patient_name, p.patient_id, p.phone,
                   u.full_name as doctor_name, u.specialty,
                   v.visit_number,
                   lab.full_name as lab_technician_name,
                   me.equipment_name as equipment_name,
                   me.batch_number as equipment_batch
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            LEFT JOIN users lab ON lt.lab_technician_id = lab.id
            LEFT JOIN medical_equipment me ON lt.equipment_used = me.id
            WHERE lt.branch_id = ?
        ";
        $params = [$doctor_branch_id];
    } else {
        $query = "
            SELECT lt.*, 
                   p.full_name as patient_name, p.patient_id, p.phone,
                   u.full_name as doctor_name, u.specialty,
                   v.visit_number,
                   lab.full_name as lab_technician_name,
                   me.equipment_name as equipment_name,
                   me.batch_number as equipment_batch
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            LEFT JOIN users lab ON lt.lab_technician_id = lab.id
            LEFT JOIN medical_equipment me ON lt.equipment_used = me.id
            WHERE lt.branch_id = ? AND lt.doctor_id = ?
        ";
        $params = [$doctor_branch_id, $user_id];
    }

    if (!empty($status_filter)) {
        if ($status_filter === 'pending') {
            $query .= " AND (lt.status IS NULL OR lt.status = 'pending')";
        } else {
            $query .= " AND lt.status = ?";
            $params[] = $status_filter;
        }
    } else {
        $query .= " AND (lt.status IS NULL OR lt.status != 'cancelled')";
    }

    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($date_filter)) {
        $query .= " AND DATE(lt.created_at) = ?";
        $params[] = $date_filter;
    }

    if ($patient_filter > 0) {
        $query .= " AND p.id = ?";
        $params[] = $patient_filter;
    }

    $query .= " ORDER BY lt.status DESC, lt.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $visit_status = '';
    $visit_patient_id = 0;
}

// ================================================================
// SEPARATE TESTS BY STATUS
// ================================================================
$pending_tests = [];
$in_progress_tests = [];
$completed_tests = [];
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;

foreach ($lab_tests as $test) {
    $status = $test['status'] ?? 'pending';
    if ($status === 'pending' || $status === null) {
        $pending_tests[] = $test;
        $pending_count++;
    } elseif ($status === 'in_progress') {
        $in_progress_tests[] = $test;
        $in_progress_count++;
    } elseif ($status === 'completed') {
        $completed_tests[] = $test;
        $completed_count++;
    }
}

// ================================================================
// CHECK IF ANY STATUS CHANGED - FOR HASH
// ================================================================
$statuses = [];
foreach ($lab_tests as $test) {
    $statuses[] = $test['id'] . ':' . ($test['status'] ?? 'pending') . ':' . ($test['results'] ?? '');
}
$status_hash = md5(implode('|', $statuses));

// ================================================================
// CHECK IF NO CHANGE (304 Not Modified)
// ================================================================
if ($last_hash && $last_hash === $status_hash) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// ================================================================
// GET STATISTICS
// ================================================================
if ($visit_id > 0) {
    // For specific visit, get counts from the visit
    $stats = [
        'pending' => $pending_count,
        'in_progress' => $in_progress_count,
        'completed' => $completed_count,
        'total' => count($lab_tests)
    ];
} else {
    // Global statistics
    if ($is_admin) {
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN status IS NULL OR status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                COUNT(*) as total
            FROM lab_tests 
            WHERE branch_id = ?
        ");
        $stmt->execute([$doctor_branch_id]);
    } else {
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN status IS NULL OR status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                COUNT(*) as total
            FROM lab_tests 
            WHERE branch_id = ? AND doctor_id = ?
        ");
        $stmt->execute([$doctor_branch_id, $user_id]);
    }
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending'] = $stats['pending'] ?? 0;
    $stats['in_progress'] = $stats['in_progress'] ?? 0;
    $stats['completed'] = $stats['completed'] ?? 0;
    $stats['total'] = $stats['total'] ?? 0;
}

// Count tests with results
$with_results = 0;
foreach ($lab_tests as $test) {
    if (!empty($test['results']) && trim($test['results']) !== '') {
        $with_results++;
    }
}

// Check if all tests are completed
$all_completed = ($pending_count == 0 && $in_progress_count == 0 && $completed_count > 0);
$has_active_tests = ($pending_count > 0 || $in_progress_count > 0);
$has_results = ($completed_count > 0);

// ================================================================
// GET STOCK MOVEMENTS FOR EQUIPMENT
// ================================================================
$equipment_movements = [];
if ($visit_id > 0) {
    $stmt = $db->prepare("
        SELECT sm.*, me.equipment_name, me.batch_number
        FROM stock_movements sm
        LEFT JOIN medical_equipment me ON sm.equipment_id = me.id
        WHERE sm.patient_id = ? AND sm.reference_type = 'lab_test'
        ORDER BY sm.created_at DESC
    ");
    $stmt->execute([$visit_patient_id]);
    $equipment_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $status_hash,
    'visit_id' => $visit_id,
    'visit_status' => $visit_status,
    'visit_patient_id' => $visit_patient_id,
    'tests' => $lab_tests,
    'pending_tests' => $pending_tests,
    'in_progress_tests' => $in_progress_tests,
    'completed_tests' => $completed_tests,
    'pending_count' => $stats['pending'],
    'in_progress_count' => $stats['in_progress'],
    'completed_count' => $stats['completed'],
    'total_count' => $stats['total'],
    'with_results' => $with_results,
    'all_completed' => $all_completed,
    'has_active_tests' => $has_active_tests,
    'has_results' => $has_results,
    'total' => count($lab_tests),
    'timestamp' => date('Y-m-d H:i:s'),
    'user_role' => $user_role,
    'is_admin' => $is_admin,
    'has_changes' => ($last_hash !== $status_hash),
    'equipment_movements' => $equipment_movements
]);
?>