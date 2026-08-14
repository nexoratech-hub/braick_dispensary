<?php
// ================================================================
// FILE: frontend/pages/doctor/view_test.php
// DOCTOR - VIEW SINGLE LAB TEST (ALIAS)
// REDIRECT TO view_lab_test.php
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION (for logging)
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================================
// LOG ACTIVITY
// ================================================================
try {
    // Include database to log activity
    require_once __DIR__ . '/../../../backend/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
        VALUES (?, ?, 'view_test_redirect', ?, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        $_SESSION['branch_id'] ?? 1,
        "Redirected to view lab test ID: $test_id" . ($is_admin ? " (Admin)" : "")
    ]);
} catch (Exception $e) {
    // Silent fail - don't break redirect
}

// ================================================================
// REDIRECT TO view_lab_test.php
// ================================================================
if ($test_id > 0) {
    header('Location: view_lab_test.php?id=' . $test_id);
    exit;
} else {
    header('Location: lab_results.php');
    exit;
}
?>