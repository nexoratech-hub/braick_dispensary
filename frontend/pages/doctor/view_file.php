<?php
// ================================================================
// FILE: frontend/pages/doctor/view_file.php
// DOCTOR - VIEW FILE IN BROWSER (FIXED FOR ALL PATHS)
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

// ================================================================
// GET DOCUMENT ID
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_download = isset($_GET['download']) && $_GET['download'] == 1;

if ($document_id <= 0) {
    die('Invalid document ID');
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
// GET DOCUMENT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT id, file_path, file_name, file_type, doctor_id, document_name 
    FROM patient_documents 
    WHERE id = ? AND doctor_id = ?
");
$stmt->execute([$document_id, $doctor_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die('Document not found or access denied');
}

$file_name = $document['file_name'];
$file_path_db = $document['file_path'];

// ================================================================
// BUILD CORRECT FILE PATH - FIXED
// ================================================================
$base_upload_path = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/';
$base_url_path = '/dispensary_system/frontend/assets/uploads/documents/';

// Try multiple path formats
$paths_to_try = [];

// 1. If file_path contains the full path with documen... (truncated)
// Extract just the filename from file_path if it contains a filename
$filename_from_path = basename($file_path_db);
if (!empty($filename_from_path) && strpos($filename_from_path, '.') !== false) {
    // Use the filename from the path
    $paths_to_try[] = $base_upload_path . $filename_from_path;
    $paths_to_try[] = $base_upload_path . $file_name;
} else {
    // Use the stored file_name
    $paths_to_try[] = $base_upload_path . $file_name;
}

// 2. Try with the full path as stored (if it has a valid extension)
if (strpos($file_path_db, '.') !== false) {
    $clean_path = str_replace('/dispensary_system/', '', $file_path_db);
    $paths_to_try[] = 'C:/xampp/htdocs/dispensary_system/' . $clean_path;
    $paths_to_try[] = 'C:/xampp/htdocs' . $file_path_db;
}

// 3. Try with the file_name only
$paths_to_try[] = $base_upload_path . $file_name;

// 4. Try with the file_path as is
$paths_to_try[] = $file_path_db;

// Find which path exists
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
                max-width: 700px;
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
            }
        </style>
    </head>
    <body>
        <div class="debug-container">
            <h2>🔍 File Not Found</h2>
            <p>The file could not be located on the server.</p>
            
            <div class="file-info">
                <p><span class="label">Document ID:</span> <?= $document_id ?></p>
                <p><span class="label">Document Name:</span> <?= htmlspecialchars($document['document_name'] ?? 'N/A') ?></p>
                <p><span class="label">File Name (DB):</span> <span class="value"><?= htmlspecialchars($file_name) ?></span></p>
                <p><span class="label">File Path (DB):</span> <span class="value"><?= htmlspecialchars($file_path_db) ?></span></p>
            </div>
            
            <div class="fix-suggestion">
                <strong>💡 Fix Suggestion:</strong><br>
                Run this SQL to fix the file path:
                <br><br>
                <code>
                UPDATE patient_documents <br>
                SET file_path = '/dispensary_system/frontend/assets/uploads/documents/<?= htmlspecialchars($file_name) ?>' <br>
                WHERE id = <?= $document_id ?>;
                </code>
            </div>
            
            <div class="paths-tried">
                <p style="font-weight:600;margin:0 0 8px 0;">Paths Tried:</p>
                <?php foreach ($paths_to_try as $index => $path): ?>
                    <?php if (empty($path)) continue; ?>
                    <?php $exists = file_exists($path); ?>
                    <div class="path-item <?= $exists ? 'exists' : 'not-exists' ?>">
                        <?= $index + 1 ?>. <?= htmlspecialchars($path) ?>
                        <?php if ($exists): ?>
                            ✅ <strong>EXISTS</strong>
                        <?php else: ?>
                            ❌ Not found
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p class="text-muted">
                <strong>Expected location:</strong><br>
                <code>C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/<?= htmlspecialchars($file_name) ?></code>
            </p>
            
            <br>
            <a href="documents.php" class="btn">Back to Documents</a>
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

// If download requested
if ($is_download) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($file_path);
    exit;
}

// ================================================================
// VIEW IN BROWSER
// ================================================================
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

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
        header('Content-Type: text/plain');
        break;
    default:
        header('Content-Type: application/octet-stream');
        break;
}

header('Content-Disposition: inline; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600');
header('Pragma: public');

readfile($file_path);
exit;
?>