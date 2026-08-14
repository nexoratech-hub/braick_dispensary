<?php
// ================================================================
// FILE: frontend/pages/doctor/cancel_appointment.php
// DOCTOR - CANCEL APPOINTMENT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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
    header('Location: appointments.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// CHECK IF APPOINTMENT EXISTS AND CAN BE CANCELLED
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ? AND a.status IN ('scheduled', 'pending', 'confirmed')
    ");
    $stmt->execute([$appointment_id]);
} else {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ? AND a.doctor_id = ? AND a.status IN ('scheduled', 'pending', 'confirmed')
    ");
    $stmt->execute([$appointment_id, $doctor_id]);
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointments.php?error=not_found_or_already_processed');
    exit;
}

// ================================================================
// CANCEL APPOINTMENT
// ================================================================
try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        UPDATE appointments 
        SET status = 'cancelled', 
            notes = CONCAT(IFNULL(notes, ''), ' [CANCELLED: ', NOW(), ']'),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$appointment_id]);

    $db->commit();

    // ================================================================
    // LOG ACTIVITY
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
            VALUES (?, ?, 'appointment_cancelled', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $doctor_branch_id,
            "Appointment #$appointment_id cancelled for patient: " . $appointment['patient_name']
        ]);
    } catch (Exception $e) {
        // Silent fail
    }

    // ================================================================
    // REDIRECT WITH SUCCESS
    // ================================================================
    $redirect_url = 'appointments.php?cancelled=1&appointment=' . $appointment_id;
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
    header('Location: appointments.php?error=cancel_failed');
    exit;
}
?>