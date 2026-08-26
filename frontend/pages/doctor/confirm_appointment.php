<?php
// ================================================================
// FILE: frontend/pages/doctor/confirm_appointment.php
// DOCTOR - CONFIRM APPOINTMENT
// ✅ FIXED: Using NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_role = $_SESSION['role'];
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET APPOINTMENT ID
// ================================================================
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header('Location: appointment.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE DATABASE - Using NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// IF ADMIN, THEY CAN CONFIRM ANY APPOINTMENT
// ================================================================
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// CHECK IF APPOINTMENT EXISTS
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id as patient_code, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.status IN ('scheduled', 'pending')
    ");
    $stmt->execute([$appointment_id]);
} else {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id as patient_code, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.doctor_id = ? AND a.status IN ('scheduled', 'pending')
    ");
    $stmt->execute([$appointment_id, $doctor_id]);
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointment.php?error=not_found_or_already_processed');
    exit;
}

// ================================================================
// CHECK IF APPOINTMENT CAN BE CONFIRMED
// ================================================================
$allowed_statuses = ['scheduled', 'pending'];
if (!in_array($appointment['status'], $allowed_statuses)) {
    header('Location: appointment.php?error=appointment_already_processed');
    exit;
}

// ================================================================
// GET ADDITIONAL NOTES (optional)
// ================================================================
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// ================================================================
// CONFIRM APPOINTMENT
// ================================================================
try {
    // Start transaction
    $db->beginTransaction();

    if ($is_admin) {
        $stmt = $db->prepare("
            UPDATE appointments 
            SET status = 'confirmed', 
                confirmed_at = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ' [CONFIRMED BY ADMIN: ', IFNULL(?, ''), ']'),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$notes, $appointment_id]);
    } else {
        $stmt = $db->prepare("
            UPDATE appointments 
            SET status = 'confirmed', 
                confirmed_at = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ' [CONFIRMED: ', IFNULL(?, ''), ']'),
                updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$notes, $appointment_id, $doctor_id]);
    }

    // Commit transaction
    $db->commit();

    // ================================================================
    // LOG ACTIVITY - Using new database structure with patient_id
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
            VALUES (?, ?, ?, 'appointment_confirmed', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $doctor_branch_id,
            $appointment['patient_id'],
            "Appointment #$appointment_id confirmed for patient: " . $appointment['patient_name'] . 
            " (ID: " . ($appointment['patient_code'] ?? 'N/A') . ")" .
            " | Doctor: " . ($appointment['doctor_name'] ?? $doctor_name) . 
            " | Notes: " . ($notes ?: 'None')
        ]);
    } catch (Exception $e) {
        // Silent fail
        error_log("Activity log error: " . $e->getMessage());
    }

    // ================================================================
    // REDIRECT WITH SUCCESS MESSAGE
    // ================================================================
    $redirect_url = 'appointment.php?confirmed=1&appointment=' . $appointment_id;
    if ($is_admin) {
        $redirect_url .= '&admin=1';
    }
    header('Location: ' . $redirect_url);
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Confirm appointment error: " . $e->getMessage());
    header('Location: appointment.php?error=confirm_failed');
    exit;
}
?>