<?php
// ================================================================
// FILE: frontend/pages/admin/add_document.php
// SUPER ADMIN - UPLOAD NEW DOCUMENT
// ✅ BLUE THEME ONLY
// ✅ File upload with validation
// ✅ Saves to patient_documents table
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $current_branch_name_display = $branch_data['name'];
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PATIENTS
// ================================================================
$patients = [];
$patient_sql = "
    SELECT p.id, p.full_name, p.patient_id, p.phone, p.gender, p.date_of_birth, p.branch_id
    FROM patients p
    WHERE 1=1
";
$patient_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $patient_sql .= " AND p.branch_id = ?";
    $patient_params[] = (int)$selected_branch_id;
}
$patient_sql .= " ORDER BY p.full_name ASC LIMIT 500";

$stmt = $db->prepare($patient_sql);
$stmt->execute($patient_params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DOCTORS
// ================================================================
$doctors = [];
$doctor_sql = "
    SELECT u.id, u.full_name, u.specialty, u.branch_id
    FROM users u
    WHERE u.role = 'doctor' AND u.status = 'active'
";
$doctor_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $doctor_sql .= " AND u.branch_id = ?";
    $doctor_params[] = (int)$selected_branch_id;
}
$doctor_sql .= " ORDER BY u.full_name ASC";

$stmt = $db->prepare($doctor_sql);
$stmt->execute($doctor_params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DOCUMENT TYPES
// ================================================================
$document_types = [
    'sick_sheet' => '🩺 Sick Sheet',
    'report' => '📊 Medical Report',
    'prescription' => '💊 Prescription',
    'lab_result' => '🧪 Lab Result',
    'xray' => '📷 X-Ray / Scan',
    'referral' => '📤 Referral Letter',
    'consent' => '✍️ Consent Form',
    'insurance' => '💳 Insurance Document',
    'invoice' => '💰 Invoice / Receipt',
    'other' => '📄 Other Document'
];

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_type = $_POST['document_type'] ?? 'other';
    $document_title = trim($_POST['document_title'] ?? '');
    $document_name = trim($_POST['document_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $branch_id_post = (int)($_POST['branch_id'] ?? $user_branch_id);
    $visit_id = (int)($_POST['visit_id'] ?? 0);
    
    // Sick sheet specific fields
    $sick_sheet_days = (int)($_POST['sick_sheet_days'] ?? 0);
    $sick_sheet_from_date = $_POST['sick_sheet_from_date'] ?? '';
    $sick_sheet_to_date = $_POST['sick_sheet_to_date'] ?? '';
    $sick_sheet_diagnosis = trim($_POST['sick_sheet_diagnosis'] ?? '');
    $sick_sheet_recommendations = trim($_POST['sick_sheet_recommendations'] ?? '');
    $sick_sheet_restrictions = trim($_POST['sick_sheet_restrictions'] ?? '');
    
    $errors = [];
    
    if ($patient_id <= 0) $errors[] = "Please select a patient";
    if (empty($document_title)) $errors[] = "Document title is required";
    if (empty($document_name)) $errors[] = "Document name is required";
    
    if (empty($_FILES['document_file']['name'])) {
        $errors[] = "Please select a file to upload";
    }
    
    if (empty($errors)) {
        try {
            // ================================================================
            // FILE UPLOAD
            // ================================================================
            $file = $_FILES['document_file'];
            $file_name_original = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Validate file size (max 10MB)
            if ($file_size > 10 * 1024 * 1024) {
                $errors[] = "File size too large. Maximum 10MB allowed.";
            }
            
            if ($file_error !== UPLOAD_ERR_OK) {
                $errors[] = "File upload error (code: $file_error)";
            }
            
            // Validate file type
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $errors[] = "File type not allowed. Allowed: " . implode(', ', $allowed_extensions);
            }
            
            if (empty($errors)) {
                // Generate unique file name
                $document_number = 'DOC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $file_name_new = $document_number . '.' . $file_extension;
                
                // Create upload directory
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_path_full = $upload_dir . $file_name_new;
                $file_path_db = '/dispensary_system/frontend/assets/uploads/documents/' . $file_name_new;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_path_full)) {
                    
                    // Detect MIME type
                    $file_type = mime_content_type($file_path_full);
                    
                    // ================================================================
                    // INSERT INTO DATABASE
                    // ================================================================
                    $insert_data = [
                        'document_number' => $document_number,
                        'patient_id' => $patient_id,
                        'visit_id' => $visit_id > 0 ? $visit_id : null,
                        'doctor_id' => $doctor_id > 0 ? $doctor_id : null,
                        'branch_id' => $branch_id_post,
                        'uploaded_by' => $user_id,
                        'document_type' => $document_type,
                        'document_name' => $document_name,
                        'document_title' => $document_title,
                        'description' => $description,
                        'file_name' => $file_name_new,
                        'file_path' => $file_path_db,
                        'file_size' => $file_size,
                        'file_type' => $file_type,
                        'status' => 'active'
                    ];
                    
                    // Add sick sheet fields if type is sick_sheet
                    if ($document_type === 'sick_sheet') {
                        $insert_data['sick_sheet_days'] = $sick_sheet_days;
                        $insert_data['sick_sheet_from_date'] = !empty($sick_sheet_from_date) ? $sick_sheet_from_date : null;
                        $insert_data['sick_sheet_to_date'] = !empty($sick_sheet_to_date) ? $sick_sheet_to_date : null;
                        $insert_data['sick_sheet_diagnosis'] = $sick_sheet_diagnosis;
                        $insert_data['sick_sheet_recommendations'] = $sick_sheet_recommendations;
                        $insert_data['sick_sheet_restrictions'] = $sick_sheet_restrictions;
                    }
                    
                    // Check upload_date column
                    $columns_check = $db->query("SHOW COLUMNS FROM patient_documents LIKE 'upload_date'");
                    if ($columns_check->rowCount() > 0) {
                        $insert_data['upload_date'] = date('Y-m-d H:i:s');
                    }
                    
                    // Build dynamic INSERT
                    $columns = array_keys($insert_data);
                    $placeholders = array_fill(0, count($columns), '?');
                    
                    $sql = "INSERT INTO patient_documents (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array_values($insert_data));
                    
                    $document_id = $db->lastInsertId();
                    
                    // Log activity
                    try {
                        $log_stmt = $db->prepare("
                            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                            VALUES (?, ?, 'document_uploaded', ?, NOW())
                        ");
                        $log_stmt->execute([
                            $user_id, 
                            $branch_id_post, 
                            "Uploaded document: $document_title (#$document_number)"
                        ]);
                    } catch (Exception $e) {}
                    
                    $message = "✅ Document uploaded successfully!<br>";
                    $message .= "📄 <strong>" . htmlspecialchars($document_title) . "</strong><br>";
                    $message .= "🔢 Document #: " . htmlspecialchars($document_number) . "<br>";
                    $message .= "📎 File: " . htmlspecialchars($file_name_new) . "<br>";
                    $message .= "💾 Size: " . number_format($file_size / 1024, 2) . " KB";
                    $message_type = 'success';
                    
                    // Redirect after 3 seconds
                    echo '<script>setTimeout(function(){ window.location.href = "documents.php?branch=' . $selected_branch_id . '"; }, 3000);</script>';
                    
                } else {
                    $errors[] = "Failed to move uploaded file. Please check directory permissions.";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message = "❌ " . implode('<br>❌ ', $errors);
        $message_type = 'error';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================
   BLUE THEME ONLY
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
}

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
}

.page-header-custom .page-title i {
    color: rgba(255,255,255,0.85);
}

.page-header-custom .badge-branch {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.page-header-custom .btn-back {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

.page-header-custom .btn-back:hover {
    background: rgba(255,255,255,0.25);
    transform: translateX(-3px);
}

/* FORM CARD */
.form-card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 28px 32px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.form-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 24px rgba(11, 94, 215, 0.08);
}

.form-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 2px dashed var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 16px;
}

.section-title i {
    width: 32px;
    height: 32px;
    background: var(--primary-bg);
    color: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* FORM ROWS */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.form-label .required {
    color: #DC2626;
    margin-left: 2px;
}

.form-label .optional {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    font-family: inherit;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
}

.form-control:hover {
    border-color: var(--primary-light);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

select.form-control {
    cursor: pointer;
    appearance: auto;
}

/* FILE UPLOAD */
.file-upload-wrapper {
    position: relative;
    padding: 24px;
    border: 3px dashed var(--primary-light);
    border-radius: 12px;
    background: var(--primary-bg);
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}

.file-upload-wrapper:hover {
    border-color: var(--primary);
    background: #DBEAFE;
}

.file-upload-wrapper.dragover {
    border-color: var(--primary);
    background: #BFDBFE;
    transform: scale(1.02);
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-upload-icon {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 8px;
}

.file-upload-text {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.file-upload-hint {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.file-upload-info {
    margin-top: 12px;
    padding: 10px 16px;
    background: white;
    border-radius: 8px;
    border: 2px solid var(--primary);
    display: none;
    align-items: center;
    gap: 10px;
}

.file-upload-info.show {
    display: flex;
}

.file-upload-info i {
    color: var(--primary);
    font-size: 1.2rem;
}

.file-upload-info .file-details {
    flex: 1;
    text-align: left;
}

.file-upload-info .file-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    word-break: break-all;
}

.file-upload-info .file-size {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

/* SICK SHEET FIELDS */
.sick-sheet-fields {
    display: none;
    padding: 20px;
    background: var(--primary-bg);
    border-radius: 12px;
    border: 2px solid var(--primary);
    margin-top: 16px;
}

.sick-sheet-fields.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* FORM ACTIONS */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    min-height: 42px;
}

.btn-primary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* MESSAGE BOX */
.message-box {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-weight: 500;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.message-box.success {
    background: #D1FAE5;
    color: #065F46;
    border: 2px solid #6EE7B7;
}

.message-box.error {
    background: #FEE2E2;
    color: #991B1B;
    border: 2px solid #FCA5A5;
}

[data-theme="dark"] .message-box.success {
    background: #1A3A2A;
    color: #34D399;
    border-color: #34D399;
}

[data-theme="dark"] .message-box.error {
    background: #3A1A1A;
    color: #F87171;
    border-color: #F87171;
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 768px) {
    .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
    .form-card { padding: 18px 20px; }
    .form-actions { flex-direction: column-reverse; }
    .form-actions .btn { width: 100%; justify-content: center; }
    .page-header-custom .page-title { font-size: 1.2rem; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-upload"></i> Upload New Document
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                Upload patient documents, reports, and files
            </p>
        </div>
        <a href="documents.php?branch=<?= $selected_branch_id ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Documents
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="form-card">
        <form method="POST" action="" id="documentForm" enctype="multipart/form-data">
            
            <!-- SECTION 1: DOCUMENT INFO -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i>
                    <span>Document Information</span>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">
                            Document Type <span class="required">*</span>
                        </label>
                        <select name="document_type" class="form-control" id="documentType" required onchange="toggleSickSheetFields()">
                            <?php foreach ($document_types as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Document Title <span class="required">*</span>
                        </label>
                        <input type="text" name="document_title" class="form-control" 
                               placeholder="e.g., Blood Test Report - Jan 2026" 
                               maxlength="200" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Document Name <span class="required">*</span>
                        </label>
                        <input type="text" name="document_name" class="form-control" 
                               placeholder="Short name (e.g., blood_test_jan)" 
                               maxlength="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Visit ID <span class="optional">(optional)</span>
                        </label>
                        <input type="number" name="visit_id" class="form-control" 
                               placeholder="Related visit ID" min="0">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">
                            Description <span class="optional">(optional)</span>
                        </label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Brief description of the document..." 
                                  maxlength="500"></textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: PATIENT & DOCTOR -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-user-md"></i>
                    <span>Patient & Doctor</span>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">
                            Patient <span class="required">*</span>
                        </label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">-- Select Patient --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['full_name']) ?> 
                                    (<?= htmlspecialchars($p['patient_id']) ?>)
                                    <?php if (!empty($p['phone'])): ?>
                                        - <?= htmlspecialchars($p['phone']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Doctor <span class="optional">(optional)</span>
                        </label>
                        <select name="doctor_id" class="form-control">
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    Dr. <?= htmlspecialchars($d['full_name']) ?> 
                                    <?= !empty($d['specialty']) ? '(' . htmlspecialchars($d['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: SICK SHEET DETAILS (SHOWS ONLY WHEN TYPE = SICK_SHEET) -->
            <div class="sick-sheet-fields" id="sickSheetFields">
                <div class="section-title" style="margin-top:0;">
                    <i class="fas fa-file-medical"></i>
                    <span>Sick Sheet Details</span>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Sick Days</label>
                        <input type="number" name="sick_sheet_days" class="form-control" 
                               placeholder="0" min="0" max="365">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="sick_sheet_from_date" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="sick_sheet_to_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Diagnosis</label>
                    <input type="text" name="sick_sheet_diagnosis" class="form-control" 
                           placeholder="e.g., Malaria, Typhoid" maxlength="300">
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Recommendations</label>
                    <textarea name="sick_sheet_recommendations" class="form-control" rows="2"
                              placeholder="e.g., Complete rest, plenty of fluids..."></textarea>
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Restrictions</label>
                    <input type="text" name="sick_sheet_restrictions" class="form-control" 
                           placeholder="e.g., No heavy lifting, no driving" maxlength="200">
                </div>
            </div>

            <!-- SECTION 4: FILE UPLOAD -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-file-upload"></i>
                    <span>Upload File</span>
                </div>
                
                <div class="file-upload-wrapper" id="fileUploadWrapper">
                    <input type="file" name="document_file" id="documentFile" 
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.txt" required>
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        Click to browse or drag & drop
                    </div>
                    <div class="file-upload-hint">
                        Max 10MB • PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, TXT
                    </div>
                </div>
                
                <div class="file-upload-info" id="fileInfo">
                    <i class="fas fa-file"></i>
                    <div class="file-details">
                        <div class="file-name" id="fileName">-</div>
                        <div class="file-size" id="fileSize">-</div>
                    </div>
                    <button type="button" onclick="clearFile()" style="background:none;border:none;cursor:pointer;color:#DC2626;font-size:1.2rem;" title="Remove">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
            </div>

            <!-- BRANCH -->
            <?php if ($selected_branch_id === 'all'): ?>
                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Branch <span class="required">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $b['id'] == $user_branch_id ? 'selected' : '' ?>>
                                    🏥 <?= htmlspecialchars($b['name']) ?>
                                    <?= !empty($b['location']) ? ' - ' . htmlspecialchars($b['location']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="branch_id" value="<?= (int)$selected_branch_id ?>">
            <?php endif; ?>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <a href="documents.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Upload Document
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// TOGGLE SICK SHEET FIELDS
// ================================================================
function toggleSickSheetFields() {
    var type = document.getElementById('documentType').value;
    var fields = document.getElementById('sickSheetFields');
    
    if (type === 'sick_sheet') {
        fields.classList.add('show');
    } else {
        fields.classList.remove('show');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleSickSheetFields();
});

// ================================================================
// FILE UPLOAD PREVIEW
// ================================================================
document.getElementById('documentFile')?.addEventListener('change', function(e) {
    var file = e.target.files[0];
    var info = document.getElementById('fileInfo');
    
    if (file) {
        // Validate size
        if (file.size > 10 * 1024 * 1024) {
            alert('⚠️ File size too large. Maximum 10MB allowed.');
            this.value = '';
            info.classList.remove('show');
            return;
        }
        
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);
        info.classList.add('show');
    } else {
        info.classList.remove('show');
    }
});

// ================================================================
// CLEAR FILE
// ================================================================
function clearFile() {
    document.getElementById('documentFile').value = '';
    document.getElementById('fileInfo').classList.remove('show');
}

// ================================================================
// FORMAT FILE SIZE
// ================================================================
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

// ================================================================
// DRAG & DROP
// ================================================================
var wrapper = document.getElementById('fileUploadWrapper');
var fileInput = document.getElementById('documentFile');

['dragenter', 'dragover'].forEach(function(eventName) {
    wrapper?.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        wrapper.classList.add('dragover');
    });
});

['dragleave', 'drop'].forEach(function(eventName) {
    wrapper?.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        wrapper.classList.remove('dragover');
    });
});

wrapper?.addEventListener('drop', function(e) {
    var files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        fileInput.dispatchEvent(new Event('change'));
    }
});

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('documentForm')?.addEventListener('submit', function(e) {
    var documentType = document.getElementById('documentType').value;
    var documentTitle = document.querySelector('input[name="document_title"]').value.trim();
    var documentName = document.querySelector('input[name="document_name"]').value.trim();
    var patientId = document.querySelector('select[name="patient_id"]').value;
    var file = document.getElementById('documentFile').files[0];
    
    if (!documentTitle) {
        e.preventDefault();
        alert('⚠️ Please enter a document title');
        return false;
    }
    if (!documentName) {
        e.preventDefault();
        alert('⚠️ Please enter a document name');
        return false;
    }
    if (!patientId) {
        e.preventDefault();
        alert('⚠️ Please select a patient');
        return false;
    }
    if (!file) {
        e.preventDefault();
        alert('⚠️ Please select a file to upload');
        return false;
    }
    if (file.size > 10 * 1024 * 1024) {
        e.preventDefault();
        alert('⚠️ File size too large. Maximum 10MB allowed.');
        return false;
    }
    
    var btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    btn.disabled = true;
    return true;
});

// ================================================================
// FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c📤 Admin - Upload Document (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Blue theme applied everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ Drag & drop file upload supported', 'font-size:12px;color:#34D399;');
console.log('%c✅ Sick sheet fields toggle automatically', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>