<?php
// ================================================================
// FILE: frontend/pages/admin/edit_document.php
// SUPER ADMIN - EDIT DOCUMENT
// ✅ BLUE THEME ONLY
// ✅ Edit metadata + optionally replace file
// ✅ Handles both external_sick_sheets and patient_documents
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
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
// GET PARAMETERS
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';
$doc_type = $_GET['type'] ?? 'patient_documents';

if ($document_id <= 0) {
    header('Location: documents.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
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
// FETCH DOCUMENT
// ================================================================
$document = null;

try {
    if ($doc_type === 'external') {
        $stmt = $db->prepare("SELECT * FROM external_sick_sheets WHERE id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $document['patient_id_ref'] = null;
            $document['document_title'] = 'Sick Sheet - ' . ($document['full_name'] ?? 'Unknown');
            $document['document_type'] = 'sick_sheet';
            $document['sick_sheet_days'] = $document['sick_days'];
            $document['sick_sheet_from_date'] = $document['sick_from'];
            $document['sick_sheet_to_date'] = $document['sick_to'];
            $document['sick_sheet_diagnosis'] = $document['diagnosis'];
            $document['sick_sheet_recommendations'] = $document['treatment'] ?? '';
            $document['sick_sheet_restrictions'] = $document['sick_restrictions'] ?? '';
            $document['patient_id'] = null;
            $document['visit_id'] = null;
        }
    } else {
        $stmt = $db->prepare("SELECT * FROM patient_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $document['patient_id_ref'] = $document['patient_id'];
            $document['document_title'] = $document['document_title'] ?? $document['document_name'];
        }
    }
    
    if (!$document) {
        header('Location: documents.php?branch=' . urlencode($selected_branch_id) . '&error=not_found');
        exit;
    }
} catch (Exception $e) {
    die("Error fetching document: " . $e->getMessage());
}

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
    
    // Sick sheet fields
    $sick_sheet_days = (int)($_POST['sick_sheet_days'] ?? 0);
    $sick_sheet_from_date = $_POST['sick_sheet_from_date'] ?? '';
    $sick_sheet_to_date = $_POST['sick_sheet_to_date'] ?? '';
    $sick_sheet_diagnosis = trim($_POST['sick_sheet_diagnosis'] ?? '');
    $sick_sheet_recommendations = trim($_POST['sick_sheet_recommendations'] ?? '');
    $sick_sheet_restrictions = trim($_POST['sick_sheet_restrictions'] ?? '');
    
    $errors = [];
    
    if ($doc_type !== 'external' && $patient_id <= 0) {
        $errors[] = "Please select a patient";
    }
    if (empty($document_title)) $errors[] = "Document title is required";
    if (empty($document_name)) $errors[] = "Document name is required";
    
    // Handle file replacement (optional)
    $new_file_uploaded = false;
    $new_file_name = null;
    $new_file_path = null;
    $new_file_size = null;
    $new_file_type = null;
    
    if (!empty($_FILES['document_file']['name'])) {
        $file = $_FILES['document_file'];
        $file_name_original = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        if ($file_size > 10 * 1024 * 1024) {
            $errors[] = "File size too large. Maximum 10MB allowed.";
        }
        
        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error (code: $file_error)";
        }
        
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "File type not allowed. Allowed: " . implode(', ', $allowed_extensions);
        }
        
        if (empty($errors)) {
            $new_file_name = $document['document_number'] . '_v' . time() . '.' . $file_extension;
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $new_file_path_full = $upload_dir . $new_file_name;
            $new_file_path = '/dispensary_system/frontend/assets/uploads/documents/' . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $new_file_path_full)) {
                $new_file_uploaded = true;
                $new_file_size = $file_size;
                $new_file_type = mime_content_type($new_file_path_full);
                
                // Delete old file
                if (!empty($document['file_path'])) {
                    $old_file = $_SERVER['DOCUMENT_ROOT'] . $document['file_path'];
                    if (file_exists($old_file)) @unlink($old_file);
                }
            } else {
                $errors[] = "Failed to upload new file.";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            if ($doc_type === 'external') {
                // Update external_sick_sheets
                $update_data = [
                    'diagnosis' => $sick_sheet_diagnosis,
                    'sick_days' => $sick_sheet_days,
                    'sick_from' => !empty($sick_sheet_from_date) ? $sick_sheet_from_date : null,
                    'sick_to' => !empty($sick_sheet_to_date) ? $sick_sheet_to_date : null,
                    'sick_reason' => $document['sick_reason'] ?? 'Medical condition',
                    'sick_restrictions' => $sick_sheet_restrictions,
                    'treatment' => $sick_sheet_recommendations,
                    'doctor_id' => $doctor_id > 0 ? $doctor_id : $document['doctor_id'],
                    'branch_id' => $branch_id_post,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($new_file_uploaded) {
                    $update_data['file_name'] = $new_file_name;
                    $update_data['file_path'] = $new_file_path;
                    $update_data['file_type'] = $new_file_type;
                }
                
                $update_parts = [];
                $update_values = [];
                foreach ($update_data as $col => $val) {
                    $update_parts[] = "$col = ?";
                    $update_values[] = $val;
                }
                $update_values[] = $document_id;
                
                $sql = "UPDATE external_sick_sheets SET " . implode(', ', $update_parts) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($update_values);
                
            } else {
                // Update patient_documents
                $columns_check = $db->query("SHOW COLUMNS FROM patient_documents");
                $existing_columns = [];
                while ($col = $columns_check->fetch(PDO::FETCH_ASSOC)) {
                    $existing_columns[] = $col['Field'];
                }
                
                $update_data = [];
                
                if (in_array('patient_id', $existing_columns)) $update_data['patient_id'] = $patient_id;
                if (in_array('doctor_id', $existing_columns)) $update_data['doctor_id'] = $doctor_id > 0 ? $doctor_id : null;
                if (in_array('branch_id', $existing_columns)) $update_data['branch_id'] = $branch_id_post;
                if (in_array('visit_id', $existing_columns) && $visit_id > 0) $update_data['visit_id'] = $visit_id;
                if (in_array('document_type', $existing_columns)) $update_data['document_type'] = $document_type;
                if (in_array('document_name', $existing_columns)) $update_data['document_name'] = $document_name;
                if (in_array('document_title', $existing_columns)) $update_data['document_title'] = $document_title;
                if (in_array('description', $existing_columns)) $update_data['description'] = $description;
                
                // Sick sheet fields
                if ($document_type === 'sick_sheet') {
                    if (in_array('sick_sheet_days', $existing_columns)) $update_data['sick_sheet_days'] = $sick_sheet_days;
                    if (in_array('sick_sheet_from_date', $existing_columns)) $update_data['sick_sheet_from_date'] = !empty($sick_sheet_from_date) ? $sick_sheet_from_date : null;
                    if (in_array('sick_sheet_to_date', $existing_columns)) $update_data['sick_sheet_to_date'] = !empty($sick_sheet_to_date) ? $sick_sheet_to_date : null;
                    if (in_array('sick_sheet_diagnosis', $existing_columns)) $update_data['sick_sheet_diagnosis'] = $sick_sheet_diagnosis;
                    if (in_array('sick_sheet_recommendations', $existing_columns)) $update_data['sick_sheet_recommendations'] = $sick_sheet_recommendations;
                    if (in_array('sick_sheet_restrictions', $existing_columns)) $update_data['sick_sheet_restrictions'] = $sick_sheet_restrictions;
                }
                
                if ($new_file_uploaded) {
                    if (in_array('file_name', $existing_columns)) $update_data['file_name'] = $new_file_name;
                    if (in_array('file_path', $existing_columns)) $update_data['file_path'] = $new_file_path;
                    if (in_array('file_size', $existing_columns)) $update_data['file_size'] = $new_file_size;
                    if (in_array('file_type', $existing_columns)) $update_data['file_type'] = $new_file_type;
                }
                
                if (in_array('updated_at', $existing_columns)) {
                    $update_data['updated_at'] = date('Y-m-d H:i:s');
                }
                
                if (!empty($update_data)) {
                    $update_parts = [];
                    $update_values = [];
                    foreach ($update_data as $col => $val) {
                        $update_parts[] = "$col = ?";
                        $update_values[] = $val;
                    }
                    $update_values[] = $document_id;
                    
                    $sql = "UPDATE patient_documents SET " . implode(', ', $update_parts) . " WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($update_values);
                }
            }
            
            // Log activity
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'document_updated', ?, NOW())");
                $log_stmt->execute([$user_id, $branch_id_post, "Updated document #$document_id - $document_title"]);
            } catch (Exception $e) {}
            
            $message = "✅ Document updated successfully!";
            if ($new_file_uploaded) {
                $message .= "<br>📎 New file uploaded: " . htmlspecialchars($new_file_name);
            }
            $message_type = 'success';
            
            // Redirect after 2 seconds
            echo '<script>setTimeout(function(){ window.location.href = "view_document.php?id=' . $document_id . '&branch=' . $selected_branch_id . '&type=' . $doc_type . '"; }, 2000);</script>';
            
            // Refresh data
            $stmt = $db->prepare("SELECT * FROM " . ($doc_type === 'external' ? 'external_sick_sheets' : 'patient_documents') . " WHERE id = ?");
            $stmt->execute([$document_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $message = "❌ Failed to update: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>❌ ', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// PREPARE FORM DATA
// ================================================================
if ($doc_type === 'external') {
    $existing_type = $document['document_type'] ?? 'sick_sheet';
    $existing_title = $document['document_title'] ?? '';
    $existing_name = $document['file_name'] ?? '';
    $existing_description = $document['symptoms'] ?? '';
    $existing_patient_id = 0;
    $existing_doctor_id = $document['doctor_id'] ?? 0;
    $existing_branch_id = $document['branch_id'] ?? $user_branch_id;
    $existing_sick_days = $document['sick_days'] ?? 0;
    $existing_sick_from = $document['sick_from'] ?? '';
    $existing_sick_to = $document['sick_to'] ?? '';
    $existing_diagnosis = $document['diagnosis'] ?? '';
    $existing_recommendations = $document['treatment'] ?? '';
    $existing_restrictions = $document['sick_restrictions'] ?? '';
    $existing_patient_display = $document['full_name'] ?? 'External Patient';
} else {
    $existing_type = $document['document_type'] ?? 'other';
    $existing_title = $document['document_title'] ?? $document['document_name'] ?? '';
    $existing_name = $document['document_name'] ?? '';
    $existing_description = $document['description'] ?? '';
    $existing_patient_id = $document['patient_id'] ?? 0;
    $existing_doctor_id = $document['doctor_id'] ?? 0;
    $existing_branch_id = $document['branch_id'] ?? $user_branch_id;
    $existing_sick_days = $document['sick_sheet_days'] ?? 0;
    $existing_sick_from = $document['sick_sheet_from_date'] ?? '';
    $existing_sick_to = $document['sick_sheet_to_date'] ?? '';
    $existing_diagnosis = $document['sick_sheet_diagnosis'] ?? '';
    $existing_recommendations = $document['sick_sheet_recommendations'] ?? '';
    $existing_restrictions = $document['sick_sheet_restrictions'] ?? '';
    $existing_patient_display = '';
}

$file_exists = !empty($document['file_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $document['file_path']);

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

.form-control:disabled {
    background: #F1F5F9;
    cursor: not-allowed;
    opacity: 0.7;
}

[data-theme="dark"] .form-control:disabled {
    background: #0F172A;
}

/* CURRENT FILE BOX */
.current-file-box {
    padding: 16px 20px;
    background: var(--primary-bg);
    border-radius: 12px;
    border: 2px solid var(--primary-light);
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
}

.current-file-box .file-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.current-file-box .file-details {
    flex: 1;
    min-width: 0;
}

.current-file-box .file-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
    word-break: break-all;
}

.current-file-box .file-meta {
    font-size: 0.72rem;
    color: var(--text-secondary);
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

[data-theme="dark"] .file-upload-info {
    background: #1E293B;
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

/* READ-ONLY INFO */
.readonly-notice {
    padding: 12px 16px;
    background: #FEF3C7;
    border: 2px solid #F59E0B;
    border-radius: 10px;
    color: #92400E;
    font-size: 0.8rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

[data-theme="dark"] .readonly-notice {
    background: #3D2E0A;
    color: #FBBF24;
    border-color: #F59E0B;
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
                <i class="fas fa-edit"></i> Edit Document
                <span class="badge-branch">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                Update document information
            </p>
        </div>
        <a href="view_document.php?id=<?= $document_id ?>&branch=<?= $selected_branch_id ?>&type=<?= $doc_type ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Details
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
            
            <!-- EXTERNAL NOTICE -->
            <?php if ($doc_type === 'external'): ?>
                <div class="readonly-notice">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>External Sick Sheet:</strong> Patient information is read-only. You can update the clinical details and file.
                    </div>
                </div>
            <?php endif; ?>
            
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
                        <select name="document_type" class="form-control" id="documentType" 
                                onchange="toggleSickSheetFields()" required
                                <?= $doc_type === 'external' ? 'disabled' : '' ?>>
                            <?php foreach ($document_types as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $existing_type === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($doc_type === 'external'): ?>
                            <input type="hidden" name="document_type" value="<?= htmlspecialchars($existing_type) ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Document Title <span class="required">*</span>
                        </label>
                        <input type="text" name="document_title" class="form-control" 
                               value="<?= htmlspecialchars($existing_title) ?>"
                               maxlength="200" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Document Name <span class="required">*</span>
                        </label>
                        <input type="text" name="document_name" class="form-control" 
                               value="<?= htmlspecialchars($existing_name) ?>"
                               maxlength="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Visit ID <span class="optional">(optional)</span>
                        </label>
                        <input type="number" name="visit_id" class="form-control" 
                               value="<?= (int)($document['visit_id'] ?? 0) ?>"
                               min="0">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">
                            Description <span class="optional">(optional)</span>
                        </label>
                        <textarea name="description" class="form-control" rows="3"
                                  maxlength="500"><?= htmlspecialchars($existing_description) ?></textarea>
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
                            Patient <?= $doc_type === 'external' ? '' : '<span class="required">*</span>' ?>
                        </label>
                        <?php if ($doc_type === 'external'): ?>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($existing_patient_display) ?>" disabled>
                        <?php else: ?>
                            <select name="patient_id" class="form-control" required>
                                <option value="">-- Select Patient --</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $existing_patient_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['full_name']) ?> 
                                        (<?= htmlspecialchars($p['patient_id']) ?>)
                                        <?php if (!empty($p['phone'])): ?>
                                            - <?= htmlspecialchars($p['phone']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Doctor <span class="optional">(optional)</span>
                        </label>
                        <select name="doctor_id" class="form-control">
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['id'] == $existing_doctor_id ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($d['full_name']) ?> 
                                    <?= !empty($d['specialty']) ? '(' . htmlspecialchars($d['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: SICK SHEET DETAILS -->
            <div class="sick-sheet-fields <?= $existing_type === 'sick_sheet' ? 'show' : '' ?>" id="sickSheetFields">
                <div class="section-title" style="margin-top:0;">
                    <i class="fas fa-file-medical"></i>
                    <span>Sick Sheet Details</span>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Sick Days</label>
                        <input type="number" name="sick_sheet_days" class="form-control" 
                               value="<?= (int)$existing_sick_days ?>" min="0" max="365">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="sick_sheet_from_date" class="form-control"
                               value="<?= htmlspecialchars($existing_sick_from) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="sick_sheet_to_date" class="form-control"
                               value="<?= htmlspecialchars($existing_sick_to) ?>">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Diagnosis</label>
                    <input type="text" name="sick_sheet_diagnosis" class="form-control" 
                           value="<?= htmlspecialchars($existing_diagnosis) ?>"
                           maxlength="300">
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Recommendations</label>
                    <textarea name="sick_sheet_recommendations" class="form-control" rows="2"><?= htmlspecialchars($existing_recommendations) ?></textarea>
                </div>
                
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Restrictions</label>
                    <input type="text" name="sick_sheet_restrictions" class="form-control" 
                           value="<?= htmlspecialchars($existing_restrictions) ?>"
                           maxlength="200">
                </div>
            </div>

            <!-- SECTION 4: FILE -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-file-upload"></i>
                    <span>File</span>
                </div>
                
                <?php if ($file_exists): ?>
                    <div class="current-file-box">
                        <div class="file-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name"><?= htmlspecialchars($document['file_name'] ?? 'Current File') ?></div>
                            <div class="file-meta">
                                <i class="fas fa-check-circle" style="color:#059669;"></i> Current file active • 
                                <?= number_format(($document['file_size'] ?? 0) / 1024, 2) ?> KB
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" 
                           style="padding:6px 14px;background:#0B5ED7;color:white;border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:600;">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                <?php endif; ?>
                
                <label class="form-label">
                    Replace File <span class="optional">(optional - leave empty to keep current)</span>
                </label>
                
                <div class="file-upload-wrapper" id="fileUploadWrapper">
                    <input type="file" name="document_file" id="documentFile" 
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.txt">
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
                                <option value="<?= $b['id'] ?>" <?= $b['id'] == $existing_branch_id ? 'selected' : '' ?>>
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
                <a href="view_document.php?id=<?= $document_id ?>&branch=<?= $selected_branch_id ?>&type=<?= $doc_type ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Edit Document #<?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>
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

// ================================================================
// FILE UPLOAD PREVIEW
// ================================================================
document.getElementById('documentFile')?.addEventListener('change', function(e) {
    var file = e.target.files[0];
    var info = document.getElementById('fileInfo');
    
    if (file) {
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
    var documentTitle = document.querySelector('input[name="document_title"]').value.trim();
    var documentName = document.querySelector('input[name="document_name"]').value.trim();
    var patientSelect = document.querySelector('select[name="patient_id"]');
    
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
    if (patientSelect && !patientSelect.value) {
        e.preventDefault();
        alert('⚠️ Please select a patient');
        return false;
    }
    
    var file = document.getElementById('documentFile').files[0];
    if (file && file.size > 10 * 1024 * 1024) {
        e.preventDefault();
        alert('⚠️ File size too large. Maximum 10MB allowed.');
        return false;
    }
    
    var btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
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

console.log('%c✏️ Admin - Edit Document (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Blue theme applied everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ File replacement supported', 'font-size:12px;color:#34D399;');
console.log('%c✅ Document #<?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>', 'font-size:12px;color:#64748B;');
</script>

</body>
</html>