<?php
// ================================================================
// FILE: frontend/pages/doctor/download_document.php
// DOWNLOAD DOCUMENT HANDLER
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
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_role = $_SESSION['role'];
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET DOCUMENT ID
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($document_id <= 0) {
    die("Invalid document ID");
}

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET DOCUMENT - WITH PERMISSION CHECK
// ================================================================
try {
    if ($is_admin) {
        // Admin can download any document
        $stmt = $db->prepare("
            SELECT pd.file_name, pd.file_path, pd.patient_id, pd.uploaded_by,
                   p.full_name as patient_name,
                   u.full_name as uploaded_by_name
            FROM patient_documents pd
            LEFT JOIN patients p ON pd.patient_id = p.id
            LEFT JOIN users u ON pd.uploaded_by = u.id
            WHERE pd.id = ? AND pd.status = 'active'
        ");
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Doctor can only download documents for their patients
        $stmt = $db->prepare("
            SELECT pd.file_name, pd.file_path, pd.patient_id, pd.uploaded_by,
                   p.full_name as patient_name,
                   u.full_name as uploaded_by_name
            FROM patient_documents pd
            LEFT JOIN patients p ON pd.patient_id = p.id
            LEFT JOIN users u ON pd.uploaded_by = u.id
            WHERE pd.id = ? AND pd.status = 'active'
            AND p.id IN (
                SELECT DISTINCT patient_id FROM visits WHERE doctor_id = ?
            )
        ");
        $stmt->execute([$document_id, $doctor_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$doc) {
        die("Document not found or you don't have permission to access it");
    }
    
    // ================================================================
    // LOG DOWNLOAD ACTIVITY
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
            VALUES (?, ?, 'document_downloaded', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $_SESSION['branch_id'] ?? 1,
            "Downloaded document #$document_id: " . $doc['file_name'] . 
            " | Patient: " . ($doc['patient_name'] ?? 'Unknown') . 
            " | Uploaded by: " . ($doc['uploaded_by_name'] ?? 'Unknown')
        ]);
    } catch (Exception $e) {
        // Silent fail
    }
    
    // ================================================================
    // GET PHYSICAL FILE PATH
    // ================================================================
    // The path stored in database is relative to document root
    $base_path = $_SERVER['DOCUMENT_ROOT'];
    $physical_path = $base_path . $doc['file_path'];
    
    // Also try alternative paths
    if (!file_exists($physical_path)) {
        // Try with dispensary_system prefix
        $alt_path = 'C:/xampp/htdocs/dispensary_system' . $doc['file_path'];
        if (file_exists($alt_path)) {
            $physical_path = $alt_path;
        } else {
            // Try with frontend path
            $alt_path2 = __DIR__ . '/../../' . $doc['file_path'];
            if (file_exists($alt_path2)) {
                $physical_path = $alt_path2;
            } else {
                // Log error but continue
                error_log("File not found: " . $physical_path . " | DB path: " . $doc['file_path']);
                
                // Try to find file by filename in uploads directory
                $filename = basename($doc['file_path']);
                $search_dirs = [
                    __DIR__ . '/../../assets/uploads/documents/',
                    __DIR__ . '/../../../uploads/documents/',
                    __DIR__ . '/../../../frontend/assets/uploads/documents/',
                    'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/',
                    'C:/xampp/htdocs/dispensary_system/uploads/documents/'
                ];
                
                $found = false;
                foreach ($search_dirs as $dir) {
                    $test_path = $dir . $filename;
                    if (file_exists($test_path)) {
                        $physical_path = $test_path;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    die("File not found. Please contact system administrator.");
                }
            }
        }
    }
    
    // Verify file exists
    if (!file_exists($physical_path)) {
        die("File not found: " . basename($doc['file_name']));
    }
    
    // ================================================================
    // GET FILE EXTENSION AND SET CONTENT TYPE
    // ================================================================
    $file_extension = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
    
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'xml' => 'application/xml',
        'json' => 'application/json'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // ================================================================
    // SET HEADERS AND OUTPUT FILE
    // ================================================================
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
    header('Content-Length: ' . filesize($physical_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    readfile($physical_path);
    exit;
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    die("Error downloading document: " . $e->getMessage());
}
?>