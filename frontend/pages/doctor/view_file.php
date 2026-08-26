<?php
// ================================================================
// FILE: frontend/pages/doctor/view_file.php
// DOCTOR - VIEW FILE IN BROWSER (FULLY FIXED)
// Session-based login - USING dispensary_db
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

// ================================================================
// GET DOCUMENT ID
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_download = isset($_GET['download']) && $_GET['download'] == 1;

if ($document_id <= 0) {
    die('Invalid document ID');
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
    $stmt = $db->prepare("SELECT id, full_name, branch_id, status FROM users WHERE id = ? AND role = 'doctor'");
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
    error_log("view_file verification error: " . $e->getMessage());
}

// ================================================================
// GET DOCUMENT DETAILS - Verify doctor has access
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT id, file_path, file_name, file_type, doctor_id, document_name, patient_id, document_type 
        FROM patient_documents 
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->execute([$document_id, $doctor_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        // Check if document exists but belongs to another doctor
        $stmt = $db->prepare("SELECT id, doctor_id FROM patient_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $doc_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc_check) {
            die('Access denied: This document belongs to another doctor.');
        }
        die('Document not found');
    }
} catch (Exception $e) {
    error_log("Document fetch error: " . $e->getMessage());
    die('Error retrieving document');
}

$file_name = $document['file_name'];
$file_path_db = $document['file_path'];
$document_name = $document['document_name'] ?? $file_name;
$document_type = $document['document_type'] ?? 'other';

// ================================================================
// BUILD CORRECT FILE PATH - FIXED FOR ALL CASES
// ================================================================
$base_upload_path = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/';
$base_url_path = '/dispensary_system/frontend/assets/uploads/documents/';

// Clean the file path from DB - remove any leading slashes or full paths
$clean_file_name = basename($file_path_db);
if (empty($clean_file_name) || strpos($clean_file_name, '.') === false) {
    $clean_file_name = $file_name;
}

// If file_name is empty, try to extract from file_path
if (empty($clean_file_name) && !empty($file_path_db)) {
    $clean_file_name = basename($file_path_db);
}

// If still empty, use a default
if (empty($clean_file_name)) {
    $clean_file_name = 'document_' . $document_id . '.pdf';
}

// ================================================================
// TRY MULTIPLE PATH LOCATIONS
// ================================================================
$paths_to_try = [];

// 1. Standard documents folder
$paths_to_try[] = $base_upload_path . $clean_file_name;

// 2. Try with just the filename in documents folder
$paths_to_try[] = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $clean_file_name;

// 3. Try with the file_path as stored (if it contains a full path)
if (!empty($file_path_db) && strpos($file_path_db, '.') !== false) {
    // Remove /dispensary_system/ prefix if present
    $clean_path = str_replace('/dispensary_system/', '', $file_path_db);
    $paths_to_try[] = 'C:/xampp/htdocs/dispensary_system/' . $clean_path;
    $paths_to_try[] = 'C:/xampp/htdocs' . $file_path_db;
}

// 4. Try with the file_path as is
if (!empty($file_path_db)) {
    $paths_to_try[] = $file_path_db;
}

// 5. Try alternative base path
$paths_to_try[] = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $clean_file_name;

// 6. Try with the filename from file_path_db
if (!empty($file_path_db)) {
    $alt_filename = basename($file_path_db);
    if ($alt_filename != $clean_file_name) {
        $paths_to_try[] = $base_upload_path . $alt_filename;
    }
}

// 7. Try with different filename patterns (for files uploaded with timestamp prefix)
$name_parts = explode('.', $clean_file_name);
if (count($name_parts) > 1) {
    $extension = end($name_parts);
    // Try without timestamp prefix
    $simple_name = $name_parts[0];
    if (strlen($simple_name) > 10 && is_numeric(substr($simple_name, 0, 10))) {
        $simple_name = substr($simple_name, strpos($simple_name, '_') + 1);
        if (!empty($simple_name)) {
            $paths_to_try[] = $base_upload_path . $simple_name . '.' . $extension;
        }
    }
}

// 8. Try looking in subdirectories
$subdirs = ['uploads/documents/', 'assets/uploads/documents/', 'frontend/assets/uploads/documents/'];
foreach ($subdirs as $subdir) {
    $paths_to_try[] = 'C:/xampp/htdocs/dispensary_system/' . $subdir . $clean_file_name;
}

// ================================================================
// FIND EXISTING FILE
// ================================================================
$found_path = '';
foreach ($paths_to_try as $path) {
    if (!empty($path) && file_exists($path)) {
        $found_path = $path;
        break;
    }
}

// ================================================================
// IF FILE NOT FOUND - SHOW DEBUG INFO
// ================================================================
if (empty($found_path)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>File Not Found</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #F1F5F9;
                padding: 20px;
            }
            .debug-container {
                background: white;
                border-radius: 16px;
                padding: 35px 40px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                max-width: 750px;
                width: 100%;
            }
            .debug-container h2 {
                color: #EF4444;
                margin: 0 0 8px 0;
            }
            .debug-container .file-info {
                background: #F8FAFC;
                border-radius: 8px;
                padding: 16px;
                margin: 16px 0;
                border: 1px solid #E2E8F0;
            }
            .debug-container .file-info p {
                margin: 4px 0;
                font-size: 0.9rem;
            }
            .debug-container .file-info .label {
                font-weight: 600;
                color: #475569;
            }
            .debug-container .file-info .value {
                color: #1E293B;
                font-family: monospace;
                word-break: break-all;
                font-size: 0.8rem;
                background: #F1F5F9;
                padding: 2px 8px;
                border-radius: 4px;
            }
            .debug-container .paths-tried {
                background: #F1F5F9;
                border-radius: 8px;
                padding: 12px 16px;
                margin: 12px 0;
                border-left: 4px solid #EF4444;
                max-height: 300px;
                overflow-y: auto;
            }
            .debug-container .paths-tried .path-item {
                padding: 4px 0;
                font-family: monospace;
                font-size: 0.75rem;
                color: #64748B;
                border-bottom: 1px solid #E2E8F0;
            }
            .debug-container .paths-tried .path-item:last-child {
                border-bottom: none;
            }
            .debug-container .paths-tried .path-item.exists {
                color: #059669;
                font-weight: 600;
            }
            .debug-container .paths-tried .path-item.not-exists {
                color: #EF4444;
            }
            .debug-container .btn {
                display: inline-block;
                padding: 10px 24px;
                background: #0B5ED7;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: background 0.3s;
            }
            .debug-container .btn:hover {
                background: #0A4CA8;
            }
            .debug-container .text-muted {
                color: #94A3B8;
                font-size: 0.8rem;
                margin-top: 8px;
            }
            .debug-container .fix-suggestion {
                background: #FEF3C7;
                border: 1px solid #FDE68A;
                border-radius: 8px;
                padding: 12px 16px;
                margin: 12px 0;
            }
            .debug-container .fix-suggestion code {
                background: #1E293B;
                color: #F1F5F9;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 0.8rem;
                display: block;
                margin-top: 6px;
                word-break: break-all;
            }
            .debug-container .doctor-info {
                background: #E8F0FE;
                border-radius: 8px;
                padding: 12px 16px;
                margin: 12px 0;
                border: 1px solid #BFDBFE;
            }
            .debug-container .doctor-info .label {
                font-weight: 600;
                color: #0B5ED7;
            }
            .debug-container .btn-success {
                background: #059669;
                color: white;
            }
            .debug-container .btn-success:hover {
                background: #047857;
            }
            .debug-container .btn-group {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 16px;
            }
            .debug-container .file-preview {
                background: #F8FAFC;
                border-radius: 8px;
                padding: 16px;
                margin: 12px 0;
                border: 2px dashed #E2E8F0;
                text-align: center;
            }
            .debug-container .file-preview .file-icon {
                font-size: 3rem;
                display: block;
                margin-bottom: 8px;
            }
            .debug-container .file-preview .file-type-badge {
                display: inline-block;
                padding: 2px 12px;
                border-radius: 12px;
                font-size: 0.65rem;
                font-weight: 600;
                background: #E8F0FE;
                color: #0B5ED7;
            }
        </style>
    </head>
    <body>
        <div class="debug-container">
            <h2>🔍 File Not Found</h2>
            <p>The file could not be located on the server.</p>
            
            <div class="doctor-info">
                <p><span class="label">👨‍⚕️ Doctor:</span> <?= htmlspecialchars($doctor_name) ?></p>
                <p><span class="label">🆔 Doctor ID:</span> <?= $doctor_id ?></p>
                <p><span class="label">🏢 Branch ID:</span> <?= $doctor_branch_id ?></p>
            </div>
            
            <div class="file-info">
                <p><span class="label">📄 Document ID:</span> <?= $document_id ?></p>
                <p><span class="label">📝 Document Name:</span> <?= htmlspecialchars($document_name ?? 'N/A') ?></p>
                <p><span class="label">📂 Document Type:</span> <span class="file-type-badge"><?= htmlspecialchars($document_type) ?></span></p>
                <p><span class="label">📁 File Name (DB):</span> <span class="value"><?= htmlspecialchars($file_name) ?></span></p>
                <p><span class="label">📂 File Path (DB):</span> <span class="value"><?= htmlspecialchars($file_path_db) ?></span></p>
                <p><span class="label">🧹 Clean File Name:</span> <span class="value"><?= htmlspecialchars($clean_file_name) ?></span></p>
            </div>
            
            <div class="fix-suggestion">
                <strong>💡 Fix Suggestion:</strong>
                <br>
                Run this SQL to fix the file path in database:
                <code>
                UPDATE patient_documents <br>
                SET file_path = '/dispensary_system/frontend/assets/uploads/documents/<?= htmlspecialchars($clean_file_name) ?>' <br>
                WHERE id = <?= $document_id ?>;
                </code>
                <br><br>
                <strong>📌 Expected file location:</strong><br>
                <code>C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/<?= htmlspecialchars($clean_file_name) ?></code>
            </div>
            
            <div class="file-preview">
                <span class="file-icon">
                    <?php
                    $ext = strtolower(pathinfo($clean_file_name, PATHINFO_EXTENSION));
                    $icons = [
                        'pdf' => '📄',
                        'doc' => '📄',
                        'docx' => '📄',
                        'xls' => '📊',
                        'xlsx' => '📊',
                        'jpg' => '🖼️',
                        'jpeg' => '🖼️',
                        'png' => '🖼️',
                        'gif' => '🖼️',
                        'svg' => '🖼️',
                        'txt' => '📝',
                        'sql' => '🗄️',
                        'zip' => '📦',
                        'rar' => '📦',
                        'html' => '🌐',
                        'htm' => '🌐'
                    ];
                    echo $icons[$ext] ?? '📎';
                    ?>
                </span>
                <br>
                <span class="file-type-badge"><?= strtoupper($ext) ?></span>
                <p style="margin-top:8px;font-size:0.8rem;color:#64748B;">
                    <?= htmlspecialchars($clean_file_name) ?>
                </p>
            </div>
            
            <div class="paths-tried">
                <p style="font-weight:600;margin:0 0 8px 0;">📂 Paths Tried (<?= count($paths_to_try) ?>):</p>
                <?php foreach ($paths_to_try as $index => $path): ?>
                    <?php if (empty($path)) continue; ?>
                    <?php $exists = file_exists($path); ?>
                    <div class="path-item <?= $exists ? 'exists' : 'not-exists' ?>">
                        <?= $index + 1 ?>. <?= htmlspecialchars($path) ?>
                        <?php if ($exists): ?>
                            ✅ <strong>EXISTS!</strong>
                        <?php else: ?>
                            ❌ Not found
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="btn-group">
                <a href="documents.php" class="btn">⬅️ Back to Documents</a>
                <a href="javascript:history.back()" class="btn btn-outline" style="background:transparent;border:2px solid #E2E8F0;color:#1E293B;">↩️ Go Back</a>
                <button onclick="window.location.reload()" class="btn btn-success">🔄 Refresh</button>
            </div>
            
            <p class="text-muted" style="margin-top:16px;">
                <strong>💡 Tip:</strong> Make sure the file exists in the documents folder. 
                If it's missing, you may need to re-upload it or check your file paths.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ================================================================
// FILE FOUND - SERVE IT
// ================================================================
$file_path = $found_path;

// Log file access
try {
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
        VALUES (?, ?, ?, 'view_file', ?, NOW())
    ");
    $stmt->execute([
        $doctor_id,
        $doctor_branch_id,
        $document['patient_id'] ?? null,
        "Viewed file: " . $clean_file_name . " (Document ID: " . $document_id . ")"
    ]);
} catch (Exception $e) {
    // Ignore logging errors
}

// ================================================================
// IF DOWNLOAD REQUESTED
// ================================================================
if ($is_download) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $clean_file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($file_path);
    exit;
}

// ================================================================
// VIEW IN BROWSER - WITH PROPER MIME TYPES
// ================================================================
$file_extension = strtolower(pathinfo($clean_file_name, PATHINFO_EXTENSION));

// Set proper content type
switch ($file_extension) {
    case 'pdf':
        header('Content-Type: application/pdf');
        break;
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    case 'gif':
        header('Content-Type: image/gif');
        break;
    case 'svg':
        header('Content-Type: image/svg+xml');
        break;
    case 'txt':
    case 'sql':
    case 'log':
        header('Content-Type: text/plain');
        break;
    case 'csv':
        header('Content-Type: text/csv');
        break;
    case 'doc':
        header('Content-Type: application/msword');
        break;
    case 'docx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
    case 'xls':
        header('Content-Type: application/vnd.ms-excel');
        break;
    case 'xlsx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        break;
    case 'ppt':
        header('Content-Type: application/vnd.ms-powerpoint');
        break;
    case 'pptx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        break;
    case 'zip':
        header('Content-Type: application/zip');
        break;
    case 'rar':
        header('Content-Type: application/x-rar-compressed');
        break;
    case 'html':
    case 'htm':
        header('Content-Type: text/html');
        break;
    case 'json':
        header('Content-Type: application/json');
        break;
    case 'xml':
        header('Content-Type: application/xml');
        break;
    default:
        // Try to detect mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        if ($mime_type && $mime_type !== 'application/octet-stream') {
            header('Content-Type: ' . $mime_type);
        } else {
            header('Content-Type: application/octet-stream');
        }
        break;
}

header('Content-Disposition: inline; filename="' . $clean_file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600');
header('Pragma: public');

// Output the file
readfile($file_path);
exit;
?>