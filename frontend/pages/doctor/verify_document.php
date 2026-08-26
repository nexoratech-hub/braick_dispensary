<?php
// ================================================================
// FILE: frontend/pages/doctor/verify_document.php
// DOCTOR - VERIFY DOCUMENT
// Session-based login (NO BYPASS)
// BRAICK DISPENSARY - USING dispensary_db
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
// INCLUDE DATABASE - USING dispensary_db
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("verify_document verification error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH NAME FOR LOGGING
// ================================================================
$branch_name = 'Unknown';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'];
    }
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// CHECK IF DOCUMENT EXISTS AND BELONGS TO THIS DOCTOR
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT pd.*, 
               p.id as patient_id,
               p.full_name as patient_name, 
               p.patient_id as patient_code,
               p.phone as patient_phone,
               u.full_name as uploaded_by_name
        FROM patient_documents pd
        JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.uploaded_by = u.id
        WHERE pd.id = ? AND pd.doctor_id = ? AND pd.is_verified = 0
    ");
    $stmt->execute([$document_id, $doctor_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        // Check if document exists but is already verified or belongs to another doctor
        $stmt = $db->prepare("SELECT id, doctor_id, is_verified, status FROM patient_documents WHERE id = ?");
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
        } elseif ($doc_check['status'] === 'deleted') {
            header('Location: documents.php?error=deleted');
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
// VERIFY DOCUMENT - USING dispensary_db columns
// ================================================================
try {
    $stmt = $db->prepare("
        UPDATE patient_documents 
        SET is_verified = 1, 
            verified_by = ?, 
            verified_date = NOW(),
            status = 'active',
            updated_at = NOW()
        WHERE id = ? AND doctor_id = ?
    ");
    $result = $stmt->execute([$doctor_id, $document_id, $doctor_id]);
    
    if (!$result) {
        header('Location: documents.php?error=update_failed');
        exit;
    }
    
    $affected_rows = $stmt->rowCount();
    if ($affected_rows == 0) {
        header('Location: documents.php?error=document_not_found_or_already_verified');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Document verification update error: " . $e->getMessage());
    header('Location: documents.php?error=database_error');
    exit;
}

// ================================================================
// LOG ACTIVITY - USING dispensary_db activity_logs
// ================================================================
try {
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
        VALUES (?, ?, ?, 'document_verified', ?, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        $doctor_branch_id,
        $document['patient_id'],
        "Document #$document_id ('" . $document['document_name'] . "') verified for patient: " . $document['patient_name'] . " (" . $document['patient_code'] . ")"
    ]);
} catch (Exception $e) {
    error_log("Activity log failed: " . $e->getMessage());
}

// ================================================================
// CREATE NOTIFICATION FOR DOCTOR - USING dispensary_db notifications
// ================================================================
try {
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, branch_id, patient_id, title, message, type, link, is_read, created_at) 
        VALUES (?, ?, ?, '✅ Document Verified', ?, 'success', ?, 0, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        $doctor_branch_id,
        $document['patient_id'],
        "Document '" . $document['document_name'] . "' has been successfully verified for patient " . $document['patient_name'],
        "documents.php"
    ]);
} catch (Exception $e) {
    // Silently fail - notifications are optional
    error_log("Notification creation failed: " . $e->getMessage());
}

// ================================================================
// UPDATE PATIENT DOCUMENT STATUS (ensure consistency)
// ================================================================
try {
    $stmt = $db->prepare("
        UPDATE patient_documents 
        SET updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$document_id]);
} catch (Exception $e) {
    // Silently fail - this is just a timestamp update
}

// ================================================================
// LOG TO ERROR LOG FOR DEBUGGING
// ================================================================
error_log("✅ Document #$document_id verified by Doctor #$doctor_id ($doctor_name) for patient: " . $document['patient_name']);

// ================================================================
// REDIRECT WITH SUCCESS MESSAGE
// ================================================================
header('Location: documents.php?verified=1&document=' . $document_id);
exit;
?>