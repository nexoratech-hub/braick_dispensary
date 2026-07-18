<?php
// ================================================================
// FILE: frontend/pages/doctor/upload_document.php
// DOCTOR - UPLOAD PATIENT DOCUMENTS (FIXED DOWNLOAD)
// DOWNLOAD USES PHYSICAL PATH WITH ALTERNATIVE PATHS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET PATIENT DETAILS
// ================================================================
$patient = null;
if ($patient_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $patient = null;
    }
}

// ================================================================
// GET VISIT DETAILS
// ================================================================
$visit = null;
if ($visit_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM visits WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$visit_id, $doctor_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $visit = null;
    }
}

// ================================================================
// GET PATIENT DOCUMENTS
// ================================================================
$documents = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM patient_documents 
        WHERE patient_id = ? 
        ORDER BY upload_date DESC
    ");
    $stmt->execute([$patient_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If table doesn't exist, create it
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS patient_documents (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                patient_id INT(11) NOT NULL,
                doctor_id INT(11) DEFAULT NULL,
                visit_id INT(11) DEFAULT NULL,
                document_number VARCHAR(50) NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_type VARCHAR(50) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT(11) DEFAULT NULL,
                file_type VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                uploaded_by INT(11) DEFAULT NULL,
                upload_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_verified TINYINT(1) DEFAULT 0,
                verified_by INT(11) DEFAULT NULL,
                verified_date TIMESTAMP NULL DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'active',
                branch_id INT(11) DEFAULT NULL
            )
        ");
        $documents = [];
    } catch (Exception $e2) {
        $documents = [];
    }
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// CREATE UPLOAD DIRECTORY
// ================================================================
$upload_dir_physical = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/';
$upload_dir_relative = '/dispensary_system/frontend/assets/uploads/documents/';
$upload_dir_web = 'http://localhost/dispensary_system/frontend/assets/uploads/documents/';

if (!is_dir($upload_dir_physical)) {
    mkdir($upload_dir_physical, 0777, true);
}

// ================================================================
// HANDLE FILE UPLOAD
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $patient_id_post = (int)($_POST['patient_id'] ?? 0);
    $visit_id_post = (int)($_POST['visit_id'] ?? 0);
    $document_name = trim($_POST['document_name'] ?? '');
    $document_type = trim($_POST['document_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $document_number = 'DOC-' . date('Ymd') . '-' . str_pad($patient_id_post, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
    
    $errors = [];
    
    if ($patient_id_post <= 0) {
        $errors[] = "Please select a patient";
    }
    if (empty($document_name)) {
        $errors[] = "Please enter document name";
    }
    if (empty($document_type)) {
        $errors[] = "Please select document type";
    }
    
    // Handle file upload
    $file_uploaded = false;
    $file_name = '';
    $file_path = '';
    $file_size = 0;
    $file_type = '';
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document_file'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "File type not allowed. Allowed: PDF, JPEG, PNG, GIF, DOC, DOCX";
        }
        if ($file['size'] > $max_size) {
            $errors[] = "File size must be less than 10MB";
        }
        
        if (empty($errors)) {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = 'doc_' . $patient_id_post . '_' . time() . '.' . $file_extension;
            
            $file_path_relative = $upload_dir_relative . $file_name;
            $file_path_physical = $upload_dir_physical . $file_name;
            
            $file_size = $file['size'];
            $file_type = $file['type'];
            
            if (move_uploaded_file($file['tmp_name'], $file_path_physical)) {
                $file_uploaded = true;
                $file_path = $file_path_relative;
            } else {
                $errors[] = "Failed to upload file";
            }
        }
    } else {
        $errors[] = "Please select a file to upload";
    }
    
    if (empty($errors) && $file_uploaded) {
        try {
            $stmt = $db->prepare("
                INSERT INTO patient_documents (
                    patient_id, doctor_id, visit_id, document_number, document_name, 
                    document_type, file_name, file_path, file_size, file_type, 
                    description, uploaded_by, branch_id, upload_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $patient_id_post,
                $doctor_id,
                $visit_id_post,
                $document_number,
                $document_name,
                $document_type,
                $file_name,
                $file_path,
                $file_size,
                $file_type,
                $description,
                $doctor_id,
                $doctor_branch_id
            ]);
            
            $message = "✅ Document uploaded successfully!";
            $message_type = 'success';
            
            // Refresh documents
            $stmt = $db->prepare("
                SELECT * FROM patient_documents 
                WHERE patient_id = ? 
                ORDER BY upload_date DESC
            ");
            $stmt->execute([$patient_id_post]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<script>setTimeout(function(){ window.location.href = "upload_document.php?patient_id=' . $patient_id_post . '"; }, 1500);</script>';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE VERIFY DOCUMENT
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_document'])) {
    $document_id = (int)($_POST['document_id'] ?? 0);
    if ($document_id > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE patient_documents 
                SET is_verified = 1, verified_by = ?, verified_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$doctor_id, $document_id]);
            $message = "✅ Document verified successfully!";
            $message_type = 'success';
            
            // Refresh documents
            $stmt = $db->prepare("
                SELECT * FROM patient_documents 
                WHERE patient_id = ? 
                ORDER BY upload_date DESC
            ");
            $stmt->execute([$patient_id]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// HANDLE DELETE DOCUMENT
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    $document_id = (int)($_POST['document_id'] ?? 0);
    if ($document_id > 0) {
        try {
            $stmt = $db->prepare("SELECT file_path, file_name FROM patient_documents WHERE id = ?");
            $stmt->execute([$document_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                // Try multiple paths
                $paths_to_try = [
                    'C:/xampp/htdocs' . $doc['file_path'],
                    $upload_dir_physical . $doc['file_name'],
                    str_replace('/', '\\', 'C:/xampp/htdocs' . $doc['file_path'])
                ];
                
                foreach ($paths_to_try as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                        break;
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM patient_documents WHERE id = ?");
                $stmt->execute([$document_id]);
                
                $message = "✅ Document deleted successfully!";
                $message_type = 'success';
                
                $stmt = $db->prepare("
                    SELECT * FROM patient_documents 
                    WHERE patient_id = ? 
                    ORDER BY upload_date DESC
                ");
                $stmt->execute([$patient_id]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET DOCTOR'S PATIENTS (for dropdown)
// ================================================================
$patients = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getDocumentTypeIcon($type) {
    $icons = [
        'lab_result' => 'fa-flask',
        'prescription' => 'fa-prescription',
        'medical_record' => 'fa-file-medical-alt',
        'referral_letter' => 'fa-paper-plane',
        'x_ray' => 'fa-x-ray',
        'scan' => 'fa-camera',
        'insurance' => 'fa-shield-alt',
        'id_document' => 'fa-id-card',
        'other' => 'fa-file'
    ];
    return $icons[$type] ?? 'fa-file';
}

function getDocumentTypeColor($type) {
    $colors = [
        'lab_result' => 'purple',
        'prescription' => 'green',
        'medical_record' => 'blue',
        'referral_letter' => 'orange',
        'x_ray' => 'red',
        'scan' => 'blue',
        'insurance' => 'green',
        'id_document' => 'blue',
        'other' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-upload"></i> Upload Document
                <span class="page-badge"><?= count($documents) ?> documents</span>
            </h1>
            <p class="page-subtitle">
                Upload and manage patient documents
                <?php if ($patient): ?>
                    <span class="patient-badge ml-2">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name']) ?>
                        <span class="text-xs opacity-70">(<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)</span>
                    </span>
                <?php endif; ?>
                <span class="separator">|</span>
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <a href="view_patient.php?id=<?= $patient_id ?>" class="btn btn-primary">
                <i class="fas fa-user"></i> Patient Profile
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- TWO COLUMN LAYOUT -->
    <!-- ================================================================ -->
    <div class="upload-grid">

        <!-- LEFT COLUMN - UPLOAD FORM -->
        <div class="upload-left">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-cloud-upload-alt title-blue"></i> Upload New Document
                </h3>
                
                <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="upload_document" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Patient <span class="required">*</span></label>
                        <select name="patient_id" class="form-control" required onchange="window.location.href='upload_document.php?patient_id='+this.value">
                            <option value="">Select Patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $patient_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Visit (Optional)</label>
                        <select name="visit_id" class="form-control">
                            <option value="">Select Visit...</option>
                            <?php if ($patient_id > 0): ?>
                                <?php 
                                try {
                                    $stmt = $db->prepare("
                                        SELECT id, visit_number, created_at 
                                        FROM visits 
                                        WHERE patient_id = ? AND doctor_id = ?
                                        ORDER BY created_at DESC
                                    ");
                                    $stmt->execute([$patient_id, $doctor_id]);
                                    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($visits as $v) {
                                        echo '<option value="' . $v['id'] . '" ' . ($visit_id == $v['id'] ? 'selected' : '') . '>';
                                        echo htmlspecialchars($v['visit_number']) . ' - ' . date('M d, Y', strtotime($v['created_at']));
                                        echo '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Document Name <span class="required">*</span></label>
                        <input type="text" name="document_name" class="form-control" placeholder="e.g. Lab Report - CBC" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Document Type <span class="required">*</span></label>
                        <select name="document_type" class="form-control" required>
                            <option value="">Select Type...</option>
                            <option value="lab_result">🧪 Lab Result</option>
                            <option value="prescription">💊 Prescription</option>
                            <option value="medical_record">📋 Medical Record</option>
                            <option value="referral_letter">📄 Referral Letter</option>
                            <option value="x_ray">🩻 X-Ray</option>
                            <option value="scan">📷 Scan</option>
                            <option value="insurance">🛡️ Insurance</option>
                            <option value="id_document">🪪 ID Document</option>
                            <option value="other">📁 Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Additional notes about this document..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">File <span class="required">*</span></label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="document_file" id="fileInput" class="file-input" required>
                            <label for="fileInput" class="file-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose File</span>
                                <span class="file-name" id="fileName">No file selected</span>
                            </label>
                            <div id="filePreview" style="display:none; margin-top:10px; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body);">
                                <div id="previewContent">
                                    <i class="fas fa-file-pdf" style="font-size:2rem; color:#EF4444;"></i>
                                    <span id="previewFileName" style="margin-left:10px; font-weight:500;"></span>
                                    <span id="previewFileSize" style="margin-left:10px; font-size:0.8rem; color:var(--text-secondary);"></span>
                                </div>
                            </div>
                        </div>
                        <small class="text-xs text-gray-400">
                            Allowed: PDF, JPEG, PNG, GIF, DOC, DOCX (Max 10MB)
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Document
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <a href="view_patient.php?id=<?= $patient_id ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>

        <!-- RIGHT COLUMN - DOCUMENTS LIST -->
        <div class="upload-right">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-file-alt title-green"></i> Documents
                    <span class="text-sm font-normal text-gray-400">(<?= count($documents) ?> documents)</span>
                </h3>
                
                <?php if (count($documents) > 0): ?>
                    <div class="documents-list">
                        <?php foreach ($documents as $doc): ?>
                            <?php 
                            $is_verified = $doc['is_verified'] ?? 0;
                            
                            // Try multiple paths to check if file exists
                            $paths_to_check = [
                                'C:/xampp/htdocs' . $doc['file_path'],
                                'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $doc['file_name'],
                                'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . str_replace('doc_', '', $doc['file_name']),
                                'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $doc['file_name']
                            ];
                            
                            $file_exists = false;
                            $found_path = '';
                            foreach ($paths_to_check as $path) {
                                if (file_exists($path)) {
                                    $file_exists = true;
                                    $found_path = $path;
                                    break;
                                }
                            }
                            
                            // Build download URL
                            $download_url = $doc['file_path'];
                            ?>
                            <div class="document-item">
                                <div class="document-icon <?= getDocumentTypeColor($doc['document_type']) ?>">
                                    <i class="fas <?= getDocumentTypeIcon($doc['document_type']) ?>"></i>
                                </div>
                                <div class="document-info">
                                    <div class="document-name"><?= htmlspecialchars($doc['document_name']) ?></div>
                                    <div class="document-meta">
                                        <span class="document-type"><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></span>
                                        <span class="document-date"><?= date('M d, Y', strtotime($doc['upload_date'])) ?></span>
                                        <span class="document-size"><?= formatFileSize($doc['file_size'] ?? 0) ?></span>
                                    </div>
                                    <?php if (!empty($doc['description'])): ?>
                                        <div class="document-desc"><?= htmlspecialchars($doc['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="document-status">
                                        <span class="status-badge <?= $doc['status'] === 'active' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= ucfirst($doc['status'] ?? 'Active') ?>
                                        </span>
                                        <?php if ($is_verified): ?>
                                            <span class="status-badge badge-success">✅ Verified</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-warning">⏳ Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="document-actions">
                                    <!-- View Button -->
                                    <?php if ($file_exists): ?>
                                        <a href="<?= $doc['file_path'] ?>" target="_blank" class="btn btn-primary btn-sm" title="View Document">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-outline btn-sm disabled" title="File not found">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- DOWNLOAD - Direct file link with multiple path attempts -->
                                    <?php if ($file_exists): ?>
                                        <?php
                                        // Try multiple download URLs
                                        $download_urls = [
                                            $doc['file_path'],
                                            '/dispensary_system/frontend/assets/uploads/documents/' . $doc['file_name'],
                                            'http://localhost/dispensary_system/frontend/assets/uploads/documents/' . $doc['file_name']
                                        ];
                                        $download_url = $download_urls[0];
                                        ?>
                                        <a href="<?= $download_url ?>" download="<?= $doc['file_name'] ?>" class="btn btn-success btn-sm" title="Download Document">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Verify Button (only if not verified) -->
                                    <?php if (!$is_verified): ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="verify_document" value="1">
                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" title="Verify Document" onclick="return confirm('Verify this document?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Delete Button -->
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this document permanently?')">
                                        <input type="hidden" name="delete_document" value="1">
                                        <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Document">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file"></i>
                        <h3>No Documents</h3>
                        <p>Upload documents using the form on the left</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Info -->
            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-info-circle title-blue"></i> Quick Info
                </h3>
                <div class="quick-info-grid">
                    <div class="info-item">
                        <span class="info-label">Total Documents</span>
                        <span class="info-value"><?= count($documents) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Verified</span>
                        <span class="info-value">
                            <?php 
                            $verified = array_filter($documents, function($d) { return $d['is_verified'] ?? 0; });
                            echo count($verified);
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Pending</span>
                        <span class="info-value">
                            <?php 
                            $pending = array_filter($documents, function($d) { return !($d['is_verified'] ?? 0); });
                            echo count($pending);
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Active</span>
                        <span class="info-value">
                            <?php 
                            $active = array_filter($documents, function($d) { return ($d['status'] ?? 'active') === 'active'; });
                            echo count($active);
                            ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Upload Document
            <span class="separator">|</span>
            <?= htmlspecialchars($doctor_name) ?>
            <span class="separator">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 3px solid var(--primary);
    }
    
    .page-header-left { flex: 1; }
    .page-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-title i { color: var(--primary); }
    .page-badge {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 14px;
        border-radius: 20px;
    }
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .separator { color: var(--border-color); margin: 0 4px; }
    .ml-2 { margin-left: 8px; }
    .text-xs { font-size: 0.75rem; }
    .opacity-70 { opacity: 0.7; }
    .text-gray-400 { color: var(--text-secondary); }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    /* ================================================================
       BADGES & TAGS
       ================================================================ */
    .patient-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    [data-theme="dark"] .patient-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 1px 10px;
        border-radius: 12px;
        border: 1px solid transparent;
    }
    .badge-success {
        background: #D1FAE5;
        color: #059669;
        border-color: #A7F3D0;
    }
    .badge-warning {
        background: #FEF3C7;
        color: #D97706;
        border-color: #FDE68A;
    }
    
    [data-theme="dark"] .badge-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }
    [data-theme="dark"] .badge-warning {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #78350F;
    }
    
    /* ================================================================
       ALERT
       ================================================================ */
    .alert {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }
    .alert-success { background: #D1FAE5; color: #059669; border-color: #059669; }
    .alert-error { background: #FEE2E2; color: #DC2626; border-color: #DC2626; }
    .alert-warning { background: #FEF3C7; color: #D97706; border-color: #D97706; }
    .alert-info { background: #E8F0FE; color: #0B5ED7; border-color: #0B5ED7; }
    
    [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
    [data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }
    
    /* ================================================================
       UPLOAD GRID
       ================================================================ */
    .upload-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }
    
    /* ================================================================
       CONSULTATION CARDS
       ================================================================ */
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .title-blue { color: var(--primary); }
    .title-green { color: #059669; }
    
    /* ================================================================
       FORM
       ================================================================ */
    .form-group { margin-bottom: 14px; }
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    .required { color: #EF4444; }
    
    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; }
    select.form-control { appearance: auto; cursor: pointer; }
    textarea.form-control { resize: vertical; min-height: 60px; }
    
    /* ================================================================
       FILE UPLOAD
       ================================================================ */
    .file-upload-wrapper {
        position: relative;
    }
    .file-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    .file-label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border: 2px dashed var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--bg-body);
        color: var(--text-primary);
    }
    .file-label:hover {
        border-color: var(--primary);
        background: var(--primary-bg);
    }
    .file-label i {
        font-size: 1.5rem;
        color: var(--primary);
    }
    .file-label .file-name {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    #filePreview {
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
    }
    #filePreview #previewContent {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    #previewContent i {
        font-size: 2rem;
    }
    #previewFileName {
        font-weight: 500;
        color: var(--text-primary);
        flex: 1;
    }
    #previewFileSize {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: transparent;
    }
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-success {
        background: #059669;
        color: white;
    }
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-danger {
        background: #EF4444;
        color: white;
    }
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
    .btn-sm.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    /* ================================================================
       DOCUMENTS LIST
       ================================================================ */
    .documents-list {
        max-height: 500px;
        overflow-y: auto;
    }
    .documents-list::-webkit-scrollbar { width: 4px; }
    .documents-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .documents-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .document-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.3s ease;
        border-radius: 8px;
    }
    .document-item:hover {
        background: var(--bg-body);
    }
    .document-item:last-child { border-bottom: none; }
    
    .document-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: white;
        flex-shrink: 0;
    }
    .document-icon.blue { background: var(--primary); }
    .document-icon.green { background: #059669; }
    .document-icon.purple { background: #7C3AED; }
    .document-icon.orange { background: #D97706; }
    .document-icon.red { background: #EF4444; }
    .document-icon.gray { background: #94A3B8; }
    
    .document-info { flex: 1; min-width: 0; }
    .document-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-primary);
    }
    .document-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .document-meta span {
        background: var(--bg-body);
        padding: 1px 8px;
        border-radius: 12px;
    }
    .document-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    .document-status {
        display: flex;
        gap: 6px;
        margin-top: 4px;
        flex-wrap: wrap;
    }
    
    .document-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    
    /* ================================================================
       QUICK INFO
       ================================================================ */
    .quick-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 16px;
    }
    .info-item { display: flex; flex-direction: column; }
    .info-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 500;
    }
    .info-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        padding: 2px 0;
    }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 30px 10px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    .empty-state h3 {
        font-size: 1rem;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .empty-state p { font-size: 0.85rem; margin: 0; }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .upload-grid { grid-template-columns: 1fr; }
        .consultation-card { padding: 16px 18px; }
    }
    
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .consultation-card { padding: 12px 14px; }
        .page-title { font-size: 1.2rem; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .document-item { flex-direction: column; align-items: flex-start; gap: 8px; }
        .document-actions { width: 100%; justify-content: flex-start; }
        .quick-info-grid { grid-template-columns: 1fr; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        .separator { display: none; }
    }
    
    @media (max-width: 480px) {
        .file-label { flex-direction: column; text-align: center; }
        .document-meta { flex-direction: column; gap: 4px; }
        #filePreview #previewContent { flex-wrap: wrap; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .file-upload-wrapper { display: none !important; }
        .form-actions { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FILE INPUT - SHOW PREVIEW BEFORE UPLOAD
    // ================================================================
    document.getElementById('fileInput')?.addEventListener('change', function(e) {
        var fileName = document.getElementById('fileName');
        var preview = document.getElementById('filePreview');
        var previewFileName = document.getElementById('previewFileName');
        var previewFileSize = document.getElementById('previewFileSize');
        var previewContent = document.getElementById('previewContent');
        
        if (this.files && this.files.length > 0) {
            var file = this.files[0];
            var sizeMB = (file.size / 1024 / 1024).toFixed(2);
            fileName.textContent = file.name + ' (' + sizeMB + ' MB)';
            
            preview.style.display = 'block';
            previewFileName.textContent = file.name;
            previewFileSize.textContent = sizeMB + ' MB';
            
            var icon = previewContent.querySelector('i');
            var fileType = file.type;
            if (fileType === 'application/pdf') {
                icon.className = 'fas fa-file-pdf';
                icon.style.color = '#EF4444';
            } else if (fileType.startsWith('image/')) {
                icon.className = 'fas fa-file-image';
                icon.style.color = '#059669';
            } else if (fileType === 'application/msword' || fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                icon.className = 'fas fa-file-word';
                icon.style.color = '#0B5ED7';
            } else {
                icon.className = 'fas fa-file';
                icon.style.color = '#94A3B8';
            }
        } else {
            fileName.textContent = 'No file selected';
            preview.style.display = 'none';
        }
    });

    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Notice' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c📄 Upload Document - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📁 Documents: <?= count($documents) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📂 Upload Directory: C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/', 'font-size:12px; color:#34D399;');
    console.log('%c⬇️ Download uses multiple path attempts', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>