<?php
// ================================================================
// FILE: frontend/pages/doctor/upload_document.php
// DOCTOR - UPLOAD PATIENT DOCUMENTS (ALL FILES ALLOWED)
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
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

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
// GET PATIENT DETAILS
// ================================================================
$patient = null;
if ($patient_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as assigned_doctor_name
            FROM patients p
            LEFT JOIN users u ON p.assigned_doctor_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $patient = null;
    }
}

// ================================================================
// GET PATIENT DOCUMENTS - FIXED: using upload_date not created_at
// ================================================================
$documents = [];
try {
    $stmt = $db->prepare("
        SELECT pd.*, 
               u.full_name as uploaded_by_name,
               b.name as branch_name
        FROM patient_documents pd
        LEFT JOIN users u ON pd.uploaded_by = u.id
        LEFT JOIN branches b ON pd.branch_id = b.id
        WHERE pd.patient_id = ? 
        ORDER BY pd.upload_date DESC
    ");
    $stmt->execute([$patient_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Documents fetch error: " . $e->getMessage());
    $documents = [];
}

// ================================================================
// GET DOCTOR'S PATIENTS (for dropdown)
// ================================================================
$patients = [];
try {
    if ($is_admin) {
        $stmt = $db->prepare("
            SELECT DISTINCT p.id, p.full_name, p.patient_id 
            FROM patients p
            ORDER BY p.full_name
        ");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT DISTINCT p.id, p.full_name, p.patient_id 
            FROM patients p
            JOIN visits v ON p.id = v.patient_id
            WHERE v.doctor_id = ? OR p.assigned_doctor_id = ?
            ORDER BY p.full_name
        ");
        $stmt->execute([$user_id, $user_id]);
    }
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Patients fetch error: " . $e->getMessage());
    $patients = [];
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// CREATE UPLOAD DIRECTORY
// ================================================================
$upload_dir_physical = __DIR__ . '/../../assets/uploads/documents/';
if (!is_dir($upload_dir_physical)) {
    mkdir($upload_dir_physical, 0777, true);
}

// Also create in alternative locations
$alt_dirs = [
    'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/',
    __DIR__ . '/../../../uploads/documents/',
    __DIR__ . '/../../../assets/uploads/documents/'
];
foreach ($alt_dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

$upload_dir_relative = '/dispensary_system/frontend/assets/uploads/documents/';

// ================================================================
// DOCUMENT TYPES
// ================================================================
$document_types = [
    'lab_result' => '🧪 Lab Result',
    'prescription' => '💊 Prescription',
    'medical_record' => '📋 Medical Record',
    'referral_letter' => '📄 Referral Letter',
    'x_ray' => '🩻 X-Ray',
    'scan' => '📷 Scan',
    'ultrasound' => '🔬 Ultrasound',
    'insurance' => '🛡️ Insurance',
    'id_document' => '🪪 ID Document',
    'sick_sheet' => '📋 Sick Sheet',
    'consent_form' => '📝 Consent Form',
    'other' => '📁 Other'
];

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
    
    $file_uploaded = false;
    $file_name = '';
    $file_path = '';
    $file_size = 0;
    $file_type = '';
    $file_extension = '';
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document_file'];
        $max_size = 50 * 1024 * 1024; // 50MB
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file['size'] > $max_size) {
            $errors[] = "File size must be less than 50MB";
        }
        
        if (empty($errors)) {
            $file_name = 'doc_' . $patient_id_post . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            
            // Try to save in multiple locations
            $save_success = false;
            $paths_to_try = [
                $upload_dir_physical . $file_name,
                'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $file_name,
                __DIR__ . '/../../../assets/uploads/documents/' . $file_name,
                __DIR__ . '/../../../uploads/documents/' . $file_name
            ];
            
            foreach ($paths_to_try as $path) {
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], $path)) {
                    $save_success = true;
                    $file_path = $upload_dir_relative . $file_name;
                    break;
                }
            }
            
            if ($save_success) {
                $file_uploaded = true;
                $file_size = $file['size'];
                $file_type = $file['type'] ?: $file_extension;
            } else {
                $errors[] = "Failed to upload file to any location";
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
                $user_id,
                $visit_id_post,
                $document_number,
                $document_name,
                $document_type,
                $file_name,
                $file_path,
                $file_size,
                $file_type,
                $description,
                $user_id,
                $user_branch_id
            ]);
            
            $message = "✅ Document uploaded successfully!";
            $message_type = 'success';
            
            // Refresh documents
            $stmt = $db->prepare("
                SELECT pd.*, u.full_name as uploaded_by_name, b.name as branch_name
                FROM patient_documents pd
                LEFT JOIN users u ON pd.uploaded_by = u.id
                LEFT JOIN branches b ON pd.branch_id = b.id
                WHERE pd.patient_id = ? 
                ORDER BY pd.upload_date DESC
            ");
            $stmt->execute([$patient_id_post]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
            error_log("Upload error: " . $e->getMessage());
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
            $stmt->execute([$user_id, $document_id]);
            $message = "✅ Document verified successfully!";
            $message_type = 'success';
            
            // Refresh documents
            $stmt = $db->prepare("
                SELECT pd.*, u.full_name as uploaded_by_name, b.name as branch_name
                FROM patient_documents pd
                LEFT JOIN users u ON pd.uploaded_by = u.id
                LEFT JOIN branches b ON pd.branch_id = b.id
                WHERE pd.patient_id = ? 
                ORDER BY pd.upload_date DESC
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
                // Try multiple paths to delete
                $paths_to_try = [
                    'C:/xampp/htdocs' . $doc['file_path'],
                    'C:/xampp/htdocs/dispensary_system' . $doc['file_path'],
                    __DIR__ . '/../../' . $doc['file_path'],
                    __DIR__ . '/../../../' . $doc['file_path'],
                    __DIR__ . '/../../assets/uploads/documents/' . $doc['file_name'],
                    __DIR__ . '/../../../assets/uploads/documents/' . $doc['file_name'],
                    'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $doc['file_name']
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
                
                // Refresh documents
                $stmt = $db->prepare("
                    SELECT pd.*, u.full_name as uploaded_by_name, b.name as branch_name
                    FROM patient_documents pd
                    LEFT JOIN users u ON pd.uploaded_by = u.id
                    LEFT JOIN branches b ON pd.branch_id = b.id
                    WHERE pd.patient_id = ? 
                    ORDER BY pd.upload_date DESC
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
        'ultrasound' => 'fa-microscope',
        'insurance' => 'fa-shield-alt',
        'id_document' => 'fa-id-card',
        'sick_sheet' => 'fa-file-alt',
        'consent_form' => 'fa-file-signature',
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
        'ultrasound' => 'purple',
        'insurance' => 'green',
        'id_document' => 'blue',
        'sick_sheet' => 'orange',
        'consent_form' => 'blue',
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
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Document - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ALL STYLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--gray-50); color: var(--gray-800); font-family: 'Inter', 'Segoe UI', sans-serif; line-height: 1.6; }
        [data-theme="dark"] body { background: var(--gray-900); color: var(--gray-100); }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            transition: var(--transition);
        }
        [data-theme="dark"] .main-content { background: var(--gray-900); }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        [data-theme="dark"] ::-webkit-scrollbar-track { background: var(--gray-700); }
        [data-theme="dark"] ::-webkit-scrollbar-thumb { background: var(--primary-dark); }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            color: white;
            position: relative;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.6), rgba(255,255,255,0.3));
            border-radius: 0 0 4px 4px;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
            color: white;
        }
        .page-title i { color: rgba(255,255,255,0.8); }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9);
        }
        .page-subtitle strong { color: white; font-weight: 700; }
        .branch-badge {
            background: rgba(255,255,255,0.2);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
        }
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            color: white;
        }
        
        .consultation-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        .consultation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #0B5ED7, #1A7FE8);
            border-radius: 0 0 4px 4px;
        }
        .consultation-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        [data-theme="dark"] .consultation-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        [data-theme="dark"] .consultation-card::before {
            background: linear-gradient(135deg, #0A4CA8, #1A6FD6);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        [data-theme="dark"] .card-title {
            color: var(--primary-light);
            border-color: var(--primary-dark);
        }
        .card-title i { color: var(--primary); font-size: 1.2rem; }
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        
        .upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 5px;
            letter-spacing: 0.02em;
        }
        [data-theme="dark"] .form-label { color: var(--gray-400); }
        .required { color: var(--danger); margin-left: 2px; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: white;
            color: var(--gray-800);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
        }
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: var(--gray-100);
        }
        [data-theme="dark"] .form-control {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        [data-theme="dark"] .form-control:disabled {
            background: var(--gray-600);
        }
        textarea.form-control { resize: vertical; min-height: 80px; font-family: inherit; }
        select.form-control { appearance: auto; cursor: pointer; }
        
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
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--gray-50);
            color: var(--gray-700);
        }
        [data-theme="dark"] .file-label {
            background: var(--gray-700);
            border-color: var(--gray-600);
            color: var(--gray-300);
        }
        .file-label:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
        }
        [data-theme="dark"] .file-label:hover {
            background: #1A3A5F;
            border-color: var(--primary-light);
        }
        .file-label i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        .file-label .file-name {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        [data-theme="dark"] .file-label .file-name {
            color: var(--gray-400);
        }
        
        .file-support-hint {
            display: block;
            margin-top: 6px;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        [data-theme="dark"] .file-support-hint {
            color: var(--gray-400);
        }
        
        #filePreview {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 10px;
            margin-top: 10px;
        }
        [data-theme="dark"] #filePreview {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #1A7FE8);
            color: white;
            box-shadow: 0 2px 12px rgba(11,94,215,0.25);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(11,94,215,0.35); }
        .btn-success {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
            box-shadow: 0 2px 12px rgba(5,150,105,0.25);
        }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(5,150,105,0.35); }
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #EF4444);
            color: white;
            box-shadow: 0 2px 12px rgba(220,38,38,0.25);
        }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(220,38,38,0.35); }
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        .btn-sm { padding: 6px 16px; font-size: 0.75rem; border-radius: 8px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; pointer-events: none; }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--gray-200);
        }
        [data-theme="dark"] .form-actions { border-color: var(--gray-700); }
        
        .documents-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .documents-list::-webkit-scrollbar { width: 4px; }
        .documents-list::-webkit-scrollbar-track { background: var(--gray-50); border-radius: 4px; }
        .documents-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .document-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
            border-radius: 8px;
        }
        [data-theme="dark"] .document-item { border-color: var(--gray-700); }
        .document-item:hover { background: var(--gray-50); }
        [data-theme="dark"] .document-item:hover { background: var(--gray-700); }
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
        .document-icon.green { background: var(--success); }
        .document-icon.purple { background: var(--purple); }
        .document-icon.orange { background: var(--warning); }
        .document-icon.red { background: var(--danger); }
        .document-icon.gray { background: var(--gray-400); }
        
        .document-info { flex: 1; min-width: 0; }
        .document-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-800);
        }
        [data-theme="dark"] .document-name { color: var(--gray-100); }
        .document-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 0.7rem;
            color: var(--gray-500);
            margin-top: 2px;
        }
        .document-meta span {
            background: var(--gray-100);
            padding: 1px 8px;
            border-radius: 12px;
        }
        [data-theme="dark"] .document-meta span {
            background: var(--gray-700);
            color: var(--gray-400);
        }
        .document-desc {
            font-size: 0.75rem;
            color: var(--gray-500);
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
            align-items: center;
        }
        
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
            border: 1px solid transparent;
        }
        .badge-success {
            background: var(--success-bg);
            color: var(--success);
            border-color: #A7F3D0;
        }
        .badge-warning {
            background: var(--warning-bg);
            color: var(--warning);
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
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .empty-state {
            text-align: center;
            padding: 30px 10px;
            color: var(--gray-500);
        }
        .empty-state i {
            font-size: 2.5rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 8px;
        }
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        .empty-state h3 {
            font-size: 1rem;
            color: var(--gray-700);
            margin: 0 0 4px 0;
        }
        [data-theme="dark"] .empty-state h3 { color: var(--gray-300); }
        
        .quick-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
        }
        .info-item { display: flex; flex-direction: column; }
        .info-label {
            font-size: 0.65rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 500;
        }
        .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-800);
            padding: 2px 0;
        }
        [data-theme="dark"] .info-value { color: var(--gray-100); }
        
        .supported-types-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .type-tag {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid rgba(11,94,215,0.15);
            transition: var(--transition);
        }
        .type-tag:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }
        [data-theme="dark"] .type-tag {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: #1E3A5F;
        }
        [data-theme="dark"] .type-tag:hover {
            background: #6EA8FE;
            color: #0F172A;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        [data-theme="dark"] .footer { border-color: var(--gray-700); color: var(--gray-400); }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .text-xs { font-size: 0.75rem; }
        .text-sm { font-size: 0.875rem; }
        .text-gray-400 { color: var(--gray-400); }
        .text-gray-500 { color: var(--gray-500); }
        .ml-2 { margin-left: 8px; }
        .mt-2 { margin-top: 8px; }
        .mb-2 { margin-bottom: 8px; }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .upload-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; }
            .page-title { font-size: 1.2rem; }
            .consultation-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .document-item { flex-direction: column; align-items: flex-start; gap: 8px; }
            .document-actions { width: 100%; justify-content: flex-start; }
            .quick-info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .consultation-card { padding: 12px; }
            .page-title { font-size: 1rem; }
            .file-label { flex-direction: column; text-align: center; }
            .document-meta { flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-upload"></i> Upload Document
                <span class="page-badge"><?= count($documents) ?> documents</span>
            </h1>
            <p class="page-subtitle">
                Upload and manage patient documents
                <?php if ($patient): ?>
                    <span style="background:rgba(255,255,255,0.15);padding:2px 12px;border-radius:20px;font-size:0.8rem;">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name']) ?>
                        (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                    </span>
                <?php endif; ?>
                <span class="branch-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="my_patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <?php if ($patient_id > 0): ?>
                <a href="view_patient.php?id=<?= $patient_id ?>" class="btn-outline-light">
                    <i class="fas fa-user"></i> Patient Profile
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
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
                            <?php if (empty($patients)): ?>
                                <option value="" disabled>No patients found</option>
                            <?php endif; ?>
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
                                        ORDER BY created_at DESC LIMIT 10
                                    ");
                                    $stmt->execute([$patient_id, $user_id]);
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
                            <?php foreach ($document_types as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
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
                            <div id="filePreview" style="display:none; margin-top:10px; padding:10px; border-radius:8px; border:1px solid var(--gray-200); background:var(--gray-50);">
                                <div id="previewContent" style="display:flex; align-items:center; gap:10px;">
                                    <i class="fas fa-file" style="font-size:2rem; color:#94A3B8;"></i>
                                    <span id="previewFileName" style="font-weight:500; flex:1;"></span>
                                    <span id="previewFileSize" style="font-size:0.8rem; color:var(--gray-500);"></span>
                                </div>
                            </div>
                        </div>
                        <small class="file-support-hint">
                            <i class="fas fa-check-circle" style="color:#059669;"></i>
                            <strong style="color:#059669;">All file types are supported</strong>
                            <span style="color:var(--gray-500);">(PDF, JPEG, PNG, GIF, DOC, DOCX, SQL, ZIP, and more) | Max: 50MB</span>
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Document
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <?php if ($patient_id > 0): ?>
                            <a href="view_patient.php?id=<?= $patient_id ?>" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </div>

        <!-- RIGHT COLUMN - DOCUMENTS LIST -->
        <div class="upload-right">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-file-alt title-green"></i> Documents
                    <span class="text-sm text-gray-400">(<?= count($documents) ?> documents)</span>
                </h3>
                
                <?php if (count($documents) > 0): ?>
                    <div class="documents-list">
                        <?php foreach ($documents as $doc): ?>
                            <?php 
                            $is_verified = $doc['is_verified'] ?? 0;
                            $file_path_check = $doc['file_path'] ?? '';
                            $file_exists = file_exists(__DIR__ . '/../../' . $file_path_check) || 
                                           file_exists('C:/xampp/htdocs' . $file_path_check) ||
                                           file_exists('C:/xampp/htdocs/dispensary_system' . $file_path_check);
                            ?>
                            <div class="document-item">
                                <div class="document-icon <?= getDocumentTypeColor($doc['document_type']) ?>">
                                    <i class="fas <?= getDocumentTypeIcon($doc['document_type']) ?>"></i>
                                </div>
                                <div class="document-info">
                                    <div class="document-name"><?= htmlspecialchars($doc['document_name']) ?></div>
                                    <div class="document-meta">
                                        <span><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></span>
                                        <span><?= date('M d, Y', strtotime($doc['upload_date'])) ?></span>
                                        <span><?= formatFileSize($doc['file_size'] ?? 0) ?></span>
                                        <?php if ($doc['file_type']): ?>
                                            <span><?= strtoupper($doc['file_type']) ?></span>
                                        <?php endif; ?>
                                        <span>#<?= $doc['document_number'] ?></span>
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
                                        <?php if ($doc['uploaded_by_name']): ?>
                                            <span class="text-xs text-gray-400">By: <?= htmlspecialchars($doc['uploaded_by_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="document-actions">
                                    <!-- View Button -->
                                    <?php if ($file_exists): ?>
                                        <a href="<?= $doc['file_path'] ?>" target="_blank" class="btn btn-primary btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="download_document.php?id=<?= $doc['id'] ?>" target="_blank" class="btn btn-success btn-sm" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-outline btn-sm disabled" title="File not found">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Verify Button -->
                                    <?php if (!$is_verified): ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="verify_document" value="1">
                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" title="Verify" onclick="return confirm('Verify this document?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Delete Button -->
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this document permanently?')">
                                        <input type="hidden" name="delete_document" value="1">
                                        <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
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

            <!-- Supported File Types -->
            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-check-circle" style="color:#059669;"></i> Supported File Types
                </h3>
                <div class="supported-types-grid">
                    <span class="type-tag">📄 PDF</span>
                    <span class="type-tag">🖼️ JPEG</span>
                    <span class="type-tag">🖼️ PNG</span>
                    <span class="type-tag">🎞️ GIF</span>
                    <span class="type-tag">📝 DOC</span>
                    <span class="type-tag">📝 DOCX</span>
                    <span class="type-tag">📊 XLS</span>
                    <span class="type-tag">📊 XLSX</span>
                    <span class="type-tag">📑 CSV</span>
                    <span class="type-tag">📄 TXT</span>
                    <span class="type-tag">🗄️ SQL</span>
                    <span class="type-tag">📦 ZIP</span>
                    <span class="type-tag">📦 RAR</span>
                    <span class="type-tag">🎬 MP4</span>
                    <span class="type-tag">🎵 MP3</span>
                    <span class="type-tag">📁 And more...</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-info-circle"></i> All file types accepted. Max size: <strong>50 MB</strong>
                </p>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">🏥 Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Upload Document
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($user_name) ?>
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FILE INPUT - SHOW PREVIEW
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
            var ext = file.name.split('.').pop().toLowerCase();
            
            // Set icon based on file type
            if (fileType === 'application/pdf' || ext === 'pdf') {
                icon.className = 'fas fa-file-pdf';
                icon.style.color = '#EF4444';
            } else if (fileType.startsWith('image/') || ['jpg','jpeg','png','gif','bmp','webp','svg'].includes(ext)) {
                icon.className = 'fas fa-file-image';
                icon.style.color = '#059669';
            } else if (fileType === 'application/msword' || fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || ['doc','docx'].includes(ext)) {
                icon.className = 'fas fa-file-word';
                icon.style.color = '#0B5ED7';
            } else if (fileType === 'application/vnd.ms-excel' || fileType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || ['xls','xlsx','csv'].includes(ext)) {
                icon.className = 'fas fa-file-excel';
                icon.style.color = '#059669';
            } else if (ext === 'sql') {
                icon.className = 'fas fa-database';
                icon.style.color = '#7C3AED';
            } else if (['zip','rar','7z','tar','gz'].includes(ext)) {
                icon.className = 'fas fa-file-archive';
                icon.style.color = '#D97706';
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
                '<?= addslashes(strip_tags($message)) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>
</script>

</body>
</html>