<?php
// ================================================================
// FILE: frontend/pages/doctor/download_document.php
// DOWNLOAD DOCUMENT HANDLER - BRANCH SPECIFIC
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
$allowed_roles = ['doctor', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
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
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET DOCUMENT ID
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($document_id <= 0) {
    die("❌ Invalid document ID");
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
// GET DOCUMENT WITH PERMISSION CHECK - FIXED: using upload_date
// ================================================================
try {
    if ($is_admin) {
        // Admin can download any document from any branch
        $stmt = $db->prepare("
            SELECT 
                pd.id,
                pd.document_number,
                pd.file_name, 
                pd.file_path, 
                pd.patient_id,
                pd.uploaded_by,
                pd.document_type,
                pd.document_name,
                pd.document_title,
                pd.description,
                pd.branch_id,
                pd.upload_date,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                u.full_name as uploaded_by_name,
                b.name as branch_name
            FROM patient_documents pd
            LEFT JOIN patients p ON pd.patient_id = p.id
            LEFT JOIN users u ON pd.uploaded_by = u.id
            LEFT JOIN branches b ON pd.branch_id = b.id
            WHERE pd.id = ? AND pd.status = 'active'
        ");
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Doctor can only download documents for their patients (with branch check)
        $stmt = $db->prepare("
            SELECT 
                pd.id,
                pd.document_number,
                pd.file_name, 
                pd.file_path, 
                pd.patient_id,
                pd.uploaded_by,
                pd.document_type,
                pd.document_name,
                pd.document_title,
                pd.description,
                pd.branch_id,
                pd.upload_date,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                u.full_name as uploaded_by_name,
                b.name as branch_name
            FROM patient_documents pd
            LEFT JOIN patients p ON pd.patient_id = p.id
            LEFT JOIN users u ON pd.uploaded_by = u.id
            LEFT JOIN branches b ON pd.branch_id = b.id
            WHERE pd.id = ? AND pd.status = 'active'
            AND pd.branch_id = ?
            AND (
                p.id IN (SELECT DISTINCT patient_id FROM visits WHERE doctor_id = ?)
                OR p.id IN (SELECT DISTINCT patient_id FROM appointments WHERE doctor_id = ?)
                OR p.assigned_doctor_id = ?
            )
        ");
        $stmt->execute([$document_id, $user_branch_id, $user_id, $user_id, $user_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check if document exists
    if (!$doc) {
        die("❌ Document not found or you don't have permission to access it");
    }
    
    // Additional check: if document has branch_id, user must be from same branch
    if (!$is_admin && $doc['branch_id'] && $doc['branch_id'] != $user_branch_id) {
        die("❌ You don't have permission to access documents from this branch");
    }
    
    // ================================================================
    // LOG DOWNLOAD ACTIVITY
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (
                user_id, branch_id, patient_id, action, details, created_at
            ) VALUES (?, ?, ?, 'document_downloaded', ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $user_branch_id,
            $doc['patient_id'],
            "Downloaded document #" . $doc['id'] . 
            " (" . $doc['document_number'] . "): " . $doc['file_name'] . 
            " | Type: " . ($doc['document_type'] ?? 'Unknown') .
            " | Patient: " . ($doc['patient_name'] ?? 'Unknown') . 
            " | Uploaded by: " . ($doc['uploaded_by_name'] ?? 'Unknown')
        ]);
    } catch (Exception $e) {
        // Silent fail - don't block download
    }
    
    // ================================================================
    // FIND PHYSICAL FILE PATH
    // ================================================================
    $physical_path = null;
    $file_path = $doc['file_path'];
    $file_name = $doc['file_name'];
    
    // Define possible base paths
    $base_paths = [
        $_SERVER['DOCUMENT_ROOT'],
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system',
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend',
        'C:/xampp/htdocs',
        'C:/xampp/htdocs/dispensary_system',
        'C:/xampp/htdocs/dispensary_system/frontend',
        __DIR__ . '/../../',
        __DIR__ . '/../../../',
        __DIR__ . '/../../../frontend',
        realpath(__DIR__ . '/../../assets/uploads/documents/'),
        realpath(__DIR__ . '/../../../assets/uploads/documents/'),
        realpath(__DIR__ . '/../../../frontend/assets/uploads/documents/')
    ];
    
    // Try direct path first
    if (file_exists($file_path)) {
        $physical_path = $file_path;
    } else {
        // Try combining with base paths
        foreach ($base_paths as $base) {
            if (empty($base)) continue;
            
            // Try different path combinations
            $test_paths = [
                $base . $file_path,
                $base . '/dispensary_system' . $file_path,
                $base . '/frontend' . $file_path,
                $base . '/assets' . $file_path,
                $base . '/uploads' . $file_path,
                $base . '/assets/uploads/documents/' . basename($file_path),
                $base . '/uploads/documents/' . basename($file_path)
            ];
            
            foreach ($test_paths as $test) {
                if (file_exists($test)) {
                    $physical_path = $test;
                    break 2;
                }
            }
        }
    }
    
    // If still not found, search by filename in upload directories
    if (!$physical_path) {
        $filename = basename($file_path);
        $search_dirs = [
            __DIR__ . '/../../assets/uploads/documents/',
            __DIR__ . '/../../../assets/uploads/documents/',
            __DIR__ . '/../../../frontend/assets/uploads/documents/',
            __DIR__ . '/../../../uploads/documents/',
            'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/',
            'C:/xampp/htdocs/dispensary_system/assets/uploads/documents/',
            'C:/xampp/htdocs/dispensary_system/uploads/documents/'
        ];
        
        foreach ($search_dirs as $dir) {
            $test_path = $dir . $filename;
            if (file_exists($test_path)) {
                $physical_path = $test_path;
                break;
            }
        }
    }
    
    // Final check
    if (!$physical_path || !file_exists($physical_path)) {
        error_log("Document file not found: " . $file_path . " | ID: " . $document_id);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>File Not Found - Braick Dispensary</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #F8FAFC; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
                .error-container { background: white; border-radius: 16px; padding: 40px 50px; max-width: 550px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #DC2626; }
                .error-icon { font-size: 48px; color: #DC2626; margin-bottom: 16px; }
                .error-title { font-size: 24px; font-weight: 700; color: #1E293B; margin-bottom: 8px; }
                .error-message { color: #64748B; font-size: 16px; margin-bottom: 20px; line-height: 1.6; }
                .error-details { background: #F1F5F9; border-radius: 8px; padding: 16px; font-size: 13px; color: #475569; margin-bottom: 20px; text-align: left; }
                .error-details strong { color: #1E293B; }
                .btn { display: inline-block; padding: 10px 24px; background: #0B5ED7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
                .btn:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
                .btn-secondary { background: #E2E8F0; color: #475569; margin-left: 10px; }
                .btn-secondary:hover { background: #CBD5E1; box-shadow: none; }
                .text-sm { font-size: 12px; color: #94A3B8; margin-top: 12px; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">📄</div>
                <div class="error-title">File Not Found</div>
                <div class="error-message">
                    The file you are trying to download could not be found on the server.
                    Please contact the administrator or request the file to be re-uploaded.
                </div>
                <div class="error-details">
                    <strong>Document:</strong> <?= htmlspecialchars($doc['document_name'] ?? 'N/A') ?><br>
                    <strong>File:</strong> <?= htmlspecialchars($file_name) ?><br>
                    <strong>Patient:</strong> <?= htmlspecialchars($doc['patient_name'] ?? 'Unknown') ?><br>
                    <strong>Uploaded:</strong> <?= date('M d, Y h:i A', strtotime($doc['upload_date'] ?? 'now')) ?><br>
                    <strong>Document ID:</strong> #<?= $document_id ?>
                </div>
                <div>
                    <a href="javascript:history.back()" class="btn">← Go Back</a>
                    <a href="my_patients.php" class="btn btn-secondary">My Patients</a>
                </div>
                <div class="text-sm">If you believe this is an error, please contact system administrator.</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // ================================================================
    // GET FILE EXTENSION AND SET CONTENT TYPE
    // ================================================================
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
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
        'html' => 'text/html',
        'htm' => 'text/html',
        'xml' => 'application/xml',
        'json' => 'application/json',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // ================================================================
    // SET HEADERS AND OUTPUT FILE
    // ================================================================
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($physical_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Clear output buffer
    if (ob_get_level()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    // Output file
    readfile($physical_path);
    exit;
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage() . " | ID: " . $document_id);
    
    // Display friendly error
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Download Error - Braick Dispensary</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #F8FAFC; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
            .error-container { background: white; border-radius: 16px; padding: 40px 50px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #DC2626; }
            .error-icon { font-size: 48px; color: #DC2626; margin-bottom: 16px; }
            .error-title { font-size: 24px; font-weight: 700; color: #1E293B; margin-bottom: 8px; }
            .error-message { color: #64748B; font-size: 16px; margin-bottom: 20px; line-height: 1.6; }
            .error-details { background: #F1F5F9; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #475569; margin-bottom: 20px; word-break: break-all; }
            .btn { display: inline-block; padding: 10px 24px; background: #0B5ED7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
            .btn:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
            .btn-secondary { background: #E2E8F0; color: #475569; margin-left: 10px; }
            .btn-secondary:hover { background: #CBD5E1; box-shadow: none; }
            .error-code { font-size: 12px; color: #94A3B8; margin-top: 12px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">📄</div>
            <div class="error-title">Download Error</div>
            <div class="error-message">
                <?= htmlspecialchars($e->getMessage()) ?>
            </div>
            <div class="error-details">
                <strong>Document ID:</strong> #<?= $document_id ?><br>
                <strong>File:</strong> <?= htmlspecialchars($doc['file_name'] ?? 'Unknown') ?>
            </div>
            <div>
                <a href="javascript:history.back()" class="btn">← Go Back</a>
                <a href="my_patients.php" class="btn btn-secondary">My Patients</a>
            </div>
            <div class="error-code">Error Code: DOC-<?= str_pad($document_id, 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>