<?php
// ================================================================
// FILE: frontend/pages/doctor/cancel_appointment.php
// DOCTOR - CANCEL APPOINTMENT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA (ID: 2) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['username'] = 'dr.sarah';
    $_SESSION['email'] = 'sarah@braick.com';
    $_SESSION['phone'] = '+255 700 000 001';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['profile_pic'] = '';
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';

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
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found");
}
$db = Database::getInstance()->getConnection();

// ================================================================
// CHECK IF APPOINTMENT EXISTS AND BELONGS TO THIS DOCTOR
// ================================================================
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.id = ? AND a.doctor_id = ? AND a.status IN ('scheduled', 'pending', 'confirmed')
");
$stmt->execute([$appointment_id, $doctor_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointments.php?error=not_found_or_already_processed');
    exit;
}

// ================================================================
// CANCEL APPOINTMENT
// ================================================================
$stmt = $db->prepare("
    UPDATE appointments 
    SET status = 'cancelled', updated_at = NOW()
    WHERE id = ? AND doctor_id = ?
");
$stmt->execute([$appointment_id, $doctor_id]);

// Log activity
try {
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, created_at) 
        VALUES (?, 'appointment_cancelled', ?, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        "Appointment #$appointment_id cancelled for patient: " . $appointment['patient_name']
    ]);
} catch (Exception $e) {}

// ================================================================
// REDIRECT WITH SUCCESS MESSAGE
// ================================================================
header('Location: appointments.php?cancelled=1&appointment=' . $appointment_id);
exit;
?>