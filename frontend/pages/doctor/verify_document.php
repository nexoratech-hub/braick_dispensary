<?php
// ================================================================
// FILE: frontend/pages/doctor/verify_document.php
// DOCTOR - VERIFY DOCUMENT
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
// GET DOCUMENT ID
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($document_id <= 0) {
    header('Location: documents.php?error=invalid_id');
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
// CHECK IF DOCUMENT EXISTS AND BELONGS TO THIS DOCTOR
// ================================================================
$stmt = $db->prepare("
    SELECT pd.*, p.full_name as patient_name 
    FROM patient_documents pd
    JOIN patients p ON pd.patient_id = p.id
    WHERE pd.id = ? AND pd.doctor_id = ? AND pd.is_verified = 0
");
$stmt->execute([$document_id, $doctor_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: documents.php?error=not_found_or_already_verified');
    exit;
}

// ================================================================
// VERIFY DOCUMENT
// ================================================================
$stmt = $db->prepare("
    UPDATE patient_documents 
    SET is_verified = 1, 
        verified_by = ?, 
        verified_date = NOW(),
        status = 'active'
    WHERE id = ? AND doctor_id = ?
");
$stmt->execute([$doctor_id, $document_id, $doctor_id]);

// ================================================================
// LOG ACTIVITY
// ================================================================
try {
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, created_at) 
        VALUES (?, 'document_verified', ?, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        "Document #$document_id ('" . $document['document_name'] . "') verified for patient: " . $document['patient_name']
    ]);
} catch (Exception $e) {}

// ================================================================
// REDIRECT WITH SUCCESS MESSAGE
// ================================================================
header('Location: documents.php?verified=1&document=' . $document_id);
exit;
?>