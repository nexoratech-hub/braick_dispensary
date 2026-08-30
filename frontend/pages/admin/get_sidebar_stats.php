<?php
// ================================================================
// FILE: frontend/pages/admin/get_sidebar_stats.php
// AJAX ENDPOINT - GET SIDEBAR STATISTICS
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// SESSION START
// ================================================================
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
        'error' => 'Unauthorized',
        'message' => 'Please login first'
    ]);
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Access Denied',
        'message' => 'Only administrators can access this endpoint'
    ]);
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

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
        
        // Check if table has branch_id column
        $has_branch = false;
        try {
            $stmt = $db->query("SHOW COLUMNS FROM $table LIKE 'branch_id'");
            $has_branch = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $has_branch = false;
        }
        
        if ($has_branch && $branch_id !== 'all') {
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
// FUNCTION TO GET SUM SAFELY
// ================================================================
function getSum($db, $table, $column, $branch_id, $extra_conditions = '') {
    try {
        $sql = "SELECT SUM($column) as total FROM $table WHERE 1=1 ";
        $params = [];
        
        // Check if table has branch_id column
        $has_branch = false;
        try {
            $stmt = $db->query("SHOW COLUMNS FROM $table LIKE 'branch_id'");
            $has_branch = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $has_branch = false;
        }
        
        if ($has_branch && $branch_id !== 'all') {
            $sql .= " AND branch_id = ?";
            $params[] = (int)$branch_id;
        }
        
        if (!empty($extra_conditions)) {
            $sql .= " AND $extra_conditions";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// ================================================================
// GET ALL STATISTICS
// ================================================================

// ----- EMPLOYEES -----
// Total employees (role != admin)
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

// Total doctors (active)
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

// Online doctors
$online_doctors = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND is_online = 1";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $online_doctors = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $online_doctors = 0;
}

// Pharmacy count
$pharmacy_count = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'pharmacy' AND status = 'active'";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pharmacy_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $pharmacy_count = 0;
}

// Reception count
$reception_count = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'reception' AND status = 'active'";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reception_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $reception_count = 0;
}

// Laboratory count
$laboratory_count = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'laboratory' AND status = 'active'";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $laboratory_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $laboratory_count = 0;
}

// Cashier count
$cashier_count = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'cashier' AND status = 'active'";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cashier_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $cashier_count = 0;
}

// ----- PATIENTS -----
// Total patients
$total_patients = getCount($db, 'patients', $selected_branch_id);

// Today patients
$today_patients = getCount($db, 'patients', $selected_branch_id, "DATE(created_at) = CURDATE()");

// ----- VISITS -----
// Today visits
$today_visits = getCount($db, 'visits', $selected_branch_id, "DATE(created_at) = CURDATE()");

// ----- APPOINTMENTS -----
// Today appointments
$today_appointments = getCount($db, 'appointments', $selected_branch_id, "DATE(appointment_date) = CURDATE() AND status NOT IN ('cancelled')");

// ----- BILL ITEMS (Services) -----
// Total services (bill items)
$total_services = getCount($db, 'bill_items', $selected_branch_id, "status != 'cancelled'");

// Today services
$today_services = getCount($db, 'bill_items', $selected_branch_id, "status != 'cancelled' AND DATE(created_at) = CURDATE()");

// ----- PRESCRIPTIONS -----
// Pending prescriptions
$pending_prescriptions = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions p 
            WHERE p.status = 'pending' AND p.branch_id = ?
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    }
    $pending_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ----- LAB TESTS -----
// Pending lab tests
$pending_lab_tests = getCount($db, 'lab_tests', $selected_branch_id, "status = 'pending'");

// ----- BILLS -----
// Pending bills (using bills table, not patient_bills)
$pending_bills = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE branch_id = ? AND status = 'pending'
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE status = 'pending'");
    }
    $pending_bills = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $pending_bills = 0;
}

// Today revenue (from bills table)
$today_revenue = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM bills 
            WHERE branch_id = ? 
            AND status = 'paid' 
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM bills 
            WHERE status = 'paid' 
            AND DATE(created_at) = CURDATE()
        ");
    }
    $today_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $today_revenue = 0;
}

// Total revenue all time (from bills table)
$total_revenue = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM bills 
            WHERE branch_id = ? 
            AND status = 'paid'
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM bills 
            WHERE status = 'paid'
        ");
    }
    $total_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $total_revenue = 0;
}

// ----- BRANCHES -----
// Total branches
$total_branches = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    $total_branches = 0;
}

// ----- OTC SALES -----
// Today OTC revenue
$today_otc_revenue = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM otc_sales 
            WHERE branch_id = ? 
            AND payment_status = 'paid' 
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM otc_sales 
            WHERE payment_status = 'paid' 
            AND DATE(created_at) = CURDATE()
        ");
    }
    $today_otc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $today_otc_revenue = 0;
}

// Total OTC revenue all time
$total_otc_revenue = 0;
try {
    if ($selected_branch_id !== 'all') {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM otc_sales 
            WHERE branch_id = ? 
            AND payment_status = 'paid'
        ");
        $stmt->execute([(int)$selected_branch_id]);
    } else {
        $stmt = $db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM otc_sales 
            WHERE payment_status = 'paid'
        ");
    }
    $total_otc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $total_otc_revenue = 0;
}

// ----- COMBINED REVENUE -----
$today_total_revenue = $today_revenue + $today_otc_revenue;
$total_total_revenue = $total_revenue + $total_otc_revenue;

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'branch_id' => $selected_branch_id,
    'user_name' => $user_full_name,
    'user_role' => $user_role,
    
    // Employee Stats
    'total_employees' => $total_employees,
    'total_doctors' => $total_doctors,
    'online_doctors' => $online_doctors,
    'pharmacy_count' => $pharmacy_count,
    'reception_count' => $reception_count,
    'laboratory_count' => $laboratory_count,
    'cashier_count' => $cashier_count,
    
    // Patient Stats
    'total_patients' => $total_patients,
    'today_patients' => $today_patients,
    'today_visits' => $today_visits,
    
    // Appointment Stats
    'today_appointments' => $today_appointments,
    
    // Service Stats (from bill_items)
    'total_services' => $total_services,
    'today_services' => $today_services,
    
    // Pending Items
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests,
    'pending_bills' => $pending_bills,
    
    // Revenue Stats (from bills table)
    'today_revenue' => number_format($today_revenue, 0),
    'total_revenue' => number_format($total_revenue, 0),
    
    // OTC Revenue Stats (from otc_sales table)
    'today_otc_revenue' => number_format($today_otc_revenue, 0),
    'total_otc_revenue' => number_format($total_otc_revenue, 0),
    
    // Combined Revenue
    'today_total_revenue' => number_format($today_total_revenue, 0),
    'total_total_revenue' => number_format($total_total_revenue, 0),
    
    // Branch Stats
    'total_branches' => $total_branches
]);