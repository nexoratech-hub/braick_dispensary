<?php
// ================================================================
// FILE: backend/api/admin_sidebar_ajax.php
// ADMIN SIDEBAR - DIRECT AJAX (NO EXTERNAL API)
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
if ($_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

// Get branch ID
$branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : 'all';
$hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) && $_POST['force_update'] == '1';

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
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;
$total_patients = 0;
$today_patients = 0;
$total_services = 0;
$today_services = 0;
$module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];

try {
    // Total employees (non-admin)
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$branch_id]);
    }
    $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total doctors
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$branch_id]);
    }
    $total_doctors = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Module counts
    $modules = ['pharmacy', 'reception', 'laboratory', 'cashier'];
    foreach ($modules as $module) {
        if ($branch_id === 'all') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
            $stmt->execute([$module]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
            $stmt->execute([$module, (int)$branch_id]);
        }
        $module_counts[$module] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total patients
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([(int)$branch_id]);
    }
    $total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Today's patients
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$branch_id]);
    }
    $today_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total services
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled'");
        $stmt->execute([(int)$branch_id]);
    }
    $total_services = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Today's services
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled' AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$branch_id]);
    }
    $today_services = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Pending prescriptions (pending + confirmed)
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status IN ('pending', 'confirmed')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([(int)$branch_id]);
    }
    $pending_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Pending lab tests
    if ($branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', 'in_progress')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([(int)$branch_id]);
    }
    $pending_lab_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total branches
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    error_log("Admin sidebar stats error: " . $e->getMessage());
}

// ================================================================
// GENERATE HASH AND CHECK CHANGES
// ================================================================
$data = [
    'total_employees' => $total_employees,
    'total_patients' => $total_patients,
    'today_patients' => $today_patients,
    'total_doctors' => $total_doctors,
    'pharmacy_count' => $module_counts['pharmacy'],
    'reception_count' => $module_counts['reception'],
    'laboratory_count' => $module_counts['laboratory'],
    'cashier_count' => $module_counts['cashier'],
    'total_services' => $total_services,
    'today_services' => $today_services,
    'total_branches' => $total_branches,
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests
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
        'employees' => $total_employees,
        'patients' => $total_patients,
        'today_patients' => $today_patients,
        'doctors' => $total_doctors,
        'pharmacy' => $module_counts['pharmacy'],
        'reception' => $module_counts['reception'],
        'laboratory' => $module_counts['laboratory'],
        'cashier' => $module_counts['cashier'],
        'services' => $total_services,
        'today_services' => $today_services,
        'branches' => $total_branches,
        'pending_prescriptions' => $pending_prescriptions,
        'pending_lab_tests' => $pending_lab_tests
    ],
    'timestamp' => date('H:i:s'),
    'branch_id' => $branch_id
]);
exit;
?>