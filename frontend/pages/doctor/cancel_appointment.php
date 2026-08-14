<?php
// ================================================================
// FILE: frontend/pages/doctor/complete_appointment.php
// DOCTOR - COMPLETE APPOINTMENT
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
// CHECK IF APPOINTMENT EXISTS
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.status = 'confirmed'
    ");
    $stmt->execute([$appointment_id]);
} else {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.doctor_id = ? AND a.status = 'confirmed'
    ");
    $stmt->execute([$appointment_id, $doctor_id]);
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointments.php?error=not_found_or_already_processed');
    exit;
}

// ================================================================
// COMPLETE APPOINTMENT
// ================================================================
try {
    $stmt = $db->prepare("
        UPDATE appointments 
        SET status = 'completed', 
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$appointment_id]);

    // Log activity
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
            VALUES (?, ?, 'appointment_completed', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $_SESSION['branch_id'] ?? 1,
            "Appointment #$appointment_id completed for patient: " . $appointment['patient_name']
        ]);
    } catch (Exception $e) {}

    header('Location: appointments.php?completed=1&appointment=' . $appointment_id);
    exit;

} catch (Exception $e) {
    error_log("Complete appointment error: " . $e->getMessage());
    header('Location: appointments.php?error=complete_failed');
    exit;
}
?>