<?php
// ================================================================
// FILE: frontend/pages/doctor/upload_document.php
// DOCTOR - UPLOAD PATIENT DOCUMENT
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
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET PATIENTS FOR DROPDOWN (Only doctor's patients)
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET VISITS FOR SELECTED PATIENT
// ================================================================
$selected_patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : (isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0);
$visits = [];
if ($selected_patient_id > 0) {
    $stmt = $db->prepare("
        SELECT id, visit_number, created_at 
        FROM visits 
        WHERE patient_id = ? AND doctor_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$selected_patient_id, $doctor_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$upload_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
    $document_name = trim($_POST['document_name'] ?? '');
    $document_type = $_POST['document_type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    
    // Validate
    if ($patient_id <= 0) {
        $message = 'Please select a patient';
        $message_type = 'error';
    } elseif (empty($document_name)) {
        $message = 'Please enter a document name';
        $message_type = 'error';
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $message = 'Please select a file to upload';
        $message_type = 'error';
    } else {
        // ================================================================
        // UPLOAD FILE - FIXED PATH
        // ================================================================
        $upload_dir = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/';
        
        // Create directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['document_file'];
        $file_name = basename($file['name']);
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_type = $file['type'];
        
        // Generate unique filename
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_filename = 'doc_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        
        // Allowed file types
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
        
        if (!in_array($file_extension, $allowed_types)) {
            $message = 'File type not allowed. Allowed: ' . implode(', ', $allowed_types);
            $message_type = 'error';
        } elseif ($file_size > 10485760) { // 10MB max
            $message = 'File too large. Maximum size is 10MB';
            $message_type = 'error';
        } elseif (move_uploaded_file($file_tmp, $target_file)) {
            // ================================================================
            // SAVE TO DATABASE
            // ================================================================
            $document_number = 'DOC-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $file_path = '/dispensary_system/frontend/assets/uploads/documents/' . $new_filename;
            
            $stmt = $db->prepare("
                INSERT INTO patient_documents (
                    patient_id,
                    doctor_id,
                    visit_id,
                    document_number,
                    document_name,
                    document_type,
                    file_name,
                    file_path,
                    file_size,
                    file_type,
                    description,
                    uploaded_by,
                    upload_date,
                    is_verified,
                    branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)
            ");
            
            if ($stmt->execute([
                $patient_id,
                $doctor_id,
                $visit_id > 0 ? $visit_id : null,
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
            ])) {
                $message = 'Document uploaded successfully!';
                $message_type = 'success';
                $upload_success = true;
                
                // Clear form
                $document_name = '';
                $description = '';
                
                // Redirect after 2 seconds
                echo '<script>setTimeout(function(){ window.location.href = "documents.php?uploaded=1"; }, 2000);</script>';
            } else {
                // Delete uploaded file if database insert fails
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
                $message = 'Failed to save document to database';
                $message_type = 'error';
            }
        } else {
            $message = 'Failed to upload file';
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET DOCTOR'S BRANCH NAME
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
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-upload mr-2" style="color: #0B5ED7;"></i> Upload Document
            </h1>
            <p class="page-subtitle">
                Upload a document for a patient
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="documents.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Documents
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="upload-card">
        <form method="POST" action="" enctype="multipart/form-data">
            
            <div class="form-row-grid">
                <!-- Patient -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user text-blue-600"></i> Patient <span class="text-danger">*</span>
                    </label>
                    <select name="patient_id" id="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $selected_patient_id == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Visit -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-clinic-medical text-green-600"></i> Visit (Optional)
                    </label>
                    <select name="visit_id" id="visit_id" class="form-control">
                        <option value="">-- Select Visit --</option>
                        <?php foreach ($visits as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= htmlspecialchars($v['visit_number']) ?> (<?= date('M d, Y', strtotime($v['created_at'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row-grid">
                <!-- Document Name -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-file text-blue-600"></i> Document Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="document_name" class="form-control" placeholder="e.g. Lab Report - Blood Test" value="<?= htmlspecialchars($document_name ?? '') ?>" required>
                </div>

                <!-- Document Type -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag text-blue-600"></i> Document Type <span class="text-danger">*</span>
                    </label>
                    <select name="document_type" class="form-control" required>
                        <option value="medical_record">Medical Record</option>
                        <option value="referral_letter">Referral Letter</option>
                        <option value="lab_result" selected>Lab Result</option>
                        <option value="prescription">Prescription</option>
                        <option value="x_ray">X-Ray</option>
                        <option value="scan">Scan</option>
                        <option value="insurance">Insurance</option>
                        <option value="id_document">ID Document</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <!-- File Upload -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-upload text-blue-600"></i> Choose File <span class="text-danger">*</span>
                </label>
                <div class="file-upload-area" id="fileUploadArea">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p class="upload-text">Click or drag file here to upload</p>
                    <p class="upload-hint">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF (Max 10MB)</p>
                    <input type="file" name="document_file" id="fileInput" class="file-input" required>
                </div>
                <div id="fileInfo" class="file-info" style="display:none;">
                    <i class="fas fa-file"></i>
                    <span id="fileName">No file selected</span>
                    <span id="fileSize"></span>
                    <button type="button" onclick="clearFile()" class="btn-remove"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-align-left text-blue-600"></i> Description (Optional)
                </label>
                <textarea name="description" class="form-control" rows="3" placeholder="Additional notes about this document..."><?= htmlspecialchars($description ?? '') ?></textarea>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="documents.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Upload Document
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
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
    .upload-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        max-width: 48rem;
        margin: 0 auto;
    }
    
    .upload-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-row-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .form-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
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
    
    .text-danger { color: #EF4444; }
    
    .file-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .file-upload-area:hover {
        border-color: var(--primary);
        background: var(--primary-bg);
    }
    
    .file-upload-area .file-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    
    .file-upload-area i {
        font-size: 3rem;
        color: var(--primary);
        display: block;
        margin-bottom: 8px;
    }
    
    .file-upload-area .upload-text {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .file-upload-area .upload-hint {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    
    .file-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        background: var(--primary-bg);
        border-radius: 8px;
        margin-top: 8px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    
    .file-info i {
        color: var(--primary);
        font-size: 1.2rem;
    }
    
    .file-info #fileName {
        flex: 1;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .file-info #fileSize {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .file-info .btn-remove {
        background: none;
        border: none;
        color: #EF4444;
        cursor: pointer;
        font-size: 1rem;
        padding: 0 4px;
    }
    
    .file-info .btn-remove:hover {
        transform: scale(1.1);
    }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        flex: 1;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
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
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 5px 14px;
        font-size: 0.75rem;
        min-height: 34px;
    }
    
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    
    [data-theme="dark"] .upload-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .form-control {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    [data-theme="dark"] .file-upload-area:hover {
        background: #1E3A5F;
    }
    [data-theme="dark"] .file-info {
        background: #1E3A5F;
    }
    
    @media (max-width: 768px) {
        .upload-card {
            padding: 18px 16px;
        }
        .form-row-grid {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .btn-primary {
            flex: none;
        }
        .file-upload-area {
            padding: 20px 16px;
        }
        .file-upload-area i {
            font-size: 2rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // PATIENT CHANGE - Load visits
    // ================================================================
    document.getElementById('patient_id')?.addEventListener('change', function() {
        var patientId = this.value;
        var visitSelect = document.getElementById('visit_id');
        
        if (!patientId) {
            visitSelect.innerHTML = '<option value="">-- Select Visit --</option>';
            return;
        }
        
        // Load visits via AJAX
        fetch('get_visits.php?patient_id=' + patientId)
            .then(response => response.json())
            .then(data => {
                visitSelect.innerHTML = '<option value="">-- Select Visit --</option>';
                if (data.visits && data.visits.length > 0) {
                    data.visits.forEach(function(visit) {
                        var option = document.createElement('option');
                        option.value = visit.id;
                        option.textContent = visit.visit_number + ' (' + new Date(visit.created_at).toLocaleDateString() + ')';
                        visitSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error:', error));
    });

    // ================================================================
    // FILE UPLOAD - Show file info
    // ================================================================
    document.getElementById('fileInput')?.addEventListener('change', function(e) {
        var file = this.files[0];
        var fileInfo = document.getElementById('fileInfo');
        var fileName = document.getElementById('fileName');
        var fileSize = document.getElementById('fileSize');
        var uploadArea = document.getElementById('fileUploadArea');
        
        if (file) {
            uploadArea.style.display = 'none';
            fileInfo.style.display = 'flex';
            fileName.textContent = file.name;
            var size = file.size;
            if (size >= 1048576) {
                fileSize.textContent = (size / 1048576).toFixed(2) + ' MB';
            } else if (size >= 1024) {
                fileSize.textContent = (size / 1024).toFixed(2) + ' KB';
            } else {
                fileSize.textContent = size + ' B';
            }
        }
    });

    function clearFile() {
        var fileInput = document.getElementById('fileInput');
        var fileInfo = document.getElementById('fileInfo');
        var uploadArea = document.getElementById('fileUploadArea');
        fileInput.value = '';
        fileInfo.style.display = 'none';
        uploadArea.style.display = 'block';
    }

    // ================================================================
    // SHOW TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c📄 Upload Document - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📁 Upload path: C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/', 'font-size:12px; color:#059669;');
</script>

</body>
</html>