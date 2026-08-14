<?php
// ================================================================
// FILE: frontend/pages/doctor/verify_document.php
// DOCTOR - VERIFY DOCUMENT
// Session-based login (NO BYPASS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

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

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, status FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $_SESSION['full_name'] = $doctor_name;
    
} catch (Exception $e) {
    error_log("verify_document verification error: " . $e->getMessage());
}

// ================================================================
// CHECK IF DOCUMENT EXISTS AND BELONGS TO THIS DOCTOR
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT pd.*, p.full_name as patient_name, p.patient_id as patient_code
        FROM patient_documents pd
        JOIN patients p ON pd.patient_id = p.id
        WHERE pd.id = ? AND pd.doctor_id = ? AND pd.is_verified = 0
    ");
    $stmt->execute([$document_id, $doctor_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        // Check if document exists but is already verified or belongs to another doctor
        $stmt = $db->prepare("SELECT id, doctor_id, is_verified FROM patient_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $doc_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc_check) {
            header('Location: documents.php?error=not_found');
            exit;
        } elseif ($doc_check['doctor_id'] != $doctor_id) {
            header('Location: documents.php?error=access_denied');
            exit;
        } elseif ($doc_check['is_verified'] == 1) {
            header('Location: documents.php?error=already_verified');
            exit;
        }
        header('Location: documents.php?error=verification_failed');
        exit;
    }
} catch (Exception $e) {
    error_log("Document verification check error: " . $e->getMessage());
    header('Location: documents.php?error=database_error');
    exit;
}

// ================================================================
// VERIFY DOCUMENT
// ================================================================
try {
    $stmt = $db->prepare("
        UPDATE patient_documents 
        SET is_verified = 1, 
            verified_by = ?, 
            verified_date = NOW(),
            status = 'active'
        WHERE id = ? AND doctor_id = ?
    ");
    $result = $stmt->execute([$doctor_id, $document_id, $doctor_id]);
    
    if (!$result) {
        header('Location: documents.php?error=update_failed');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Document verification update error: " . $e->getMessage());
    header('Location: documents.php?error=database_error');
    exit;
}

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
        "Document #$document_id ('" . $document['document_name'] . "') verified for patient: " . $document['patient_name'] . " (" . $document['patient_code'] . ")"
    ]);
    error_log("Document verification activity logged");
} catch (Exception $e) {
    error_log("Activity log failed: " . $e->getMessage());
}

// ================================================================
// CREATE NOTIFICATION FOR PATIENT (Optional)
// ================================================================
try {
    // Check if patients table has user_id or notifications system
    // This is optional - can be removed if not needed
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at) 
        VALUES (?, ?, ?, 'success', ?, 0, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        "✅ Document Verified",
        "Document '" . $document['document_name'] . "' has been successfully verified.",
        "documents.php"
    ]);
    error_log("Notification created for document verification");
} catch (Exception $e) {
    // Silently fail - notifications are optional
    error_log("Notification creation failed: " . $e->getMessage());
}

// ================================================================
// REDIRECT WITH SUCCESS MESSAGE
// ================================================================
header('Location: documents.php?verified=1&document=' . $document_id);
exit;
?>