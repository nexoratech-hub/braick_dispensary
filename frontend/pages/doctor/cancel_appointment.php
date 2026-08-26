<?php
// ================================================================
// FILE: frontend/pages/doctor/cancel_appointment.php
// DOCTOR - CANCEL APPOINTMENT
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
// LOGIN PROTECTION
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
// GET DOCTOR INFO
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

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
// CHECK IF APPOINTMENT EXISTS
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id as patient_code, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.status IN ('scheduled', 'pending', 'confirmed')
    ");
    $stmt->execute([$appointment_id]);
} else {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id as patient_code, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.doctor_id = ? AND a.status IN ('scheduled', 'pending', 'confirmed')
    ");
    $stmt->execute([$appointment_id, $doctor_id]);
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointment.php?error=not_found_or_already_processed');
    exit;
}

// ================================================================
// GET REASON FOR CANCELLATION
// ================================================================
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
if (empty($reason)) {
    $reason = 'Cancelled by ' . ($is_admin ? 'admin' : 'doctor');
}

// ================================================================
// CANCEL APPOINTMENT
// ================================================================
try {
    $db->beginTransaction();

    if ($is_admin) {
        $stmt = $db->prepare("
            UPDATE appointments 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ' [CANCELLED: ', ?, ']'),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $appointment_id]);
    } else {
        $stmt = $db->prepare("
            UPDATE appointments 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ' [CANCELLED: ', ?, ']'),
                updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$reason, $appointment_id, $doctor_id]);
    }

    $db->commit();

    // ================================================================
    // LOG ACTIVITY - Using new database structure
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
            VALUES (?, ?, ?, 'appointment_cancelled', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $doctor_branch_id,
            $appointment['patient_id'],
            "Appointment #$appointment_id cancelled for patient: " . $appointment['patient_name'] . 
            " (ID: " . ($appointment['patient_code'] ?? 'N/A') . ")" .
            " | Doctor: " . ($appointment['doctor_name'] ?? $doctor_name) . 
            " | Reason: " . $reason
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }

    // ================================================================
    // REDIRECT TO APPOINTMENTS PAGE
    // ================================================================
    $redirect_url = 'appointment.php?cancelled=1&appointment=' . $appointment_id;
    if ($is_admin) {
        $redirect_url .= '&admin=1';
    }
    header('Location: ' . $redirect_url);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Cancel appointment error: " . $e->getMessage());
    header('Location: appointment.php?error=cancel_failed');
    exit;
}
?>