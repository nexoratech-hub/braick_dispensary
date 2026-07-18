<?php
// ================================================================
// FILE: frontend/pages/doctor/view_document.php
// DOCTOR - VIEW DOCUMENT DETAILS (WITH FIXED DOWNLOAD)
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

// ================================================================
// GET DOCUMENT ID
// ================================================================
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($document_id <= 0) {
    header('Location: documents.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCUMENT DETAILS
// ================================================================
$document = null;
$file_exists = false;
$physical_path = '';
$web_path = '';
$download_url = '';

try {
    $stmt = $db->prepare("
        SELECT 
            pd.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            vu.full_name as verified_by_name
        FROM patient_documents pd
        JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        LEFT JOIN visits v ON pd.visit_id = v.id
        LEFT JOIN users vu ON pd.verified_by = vu.id
        WHERE pd.id = ? AND pd.doctor_id = ?
    ");
    $stmt->execute([$document_id, $doctor_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        header('Location: documents.php?error=not_found');
        exit;
    }

    // ================================================================
    // BUILD CORRECT PATHS
    // ================================================================
    // Physical paths to check
    $paths_to_check = [
        'C:/xampp/htdocs' . $document['file_path'],
        'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . $document['file_name'],
        'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/documents/' . str_replace('doc_', '', $document['file_name']),
        str_replace('\\', '/', 'C:/xampp/htdocs' . $document['file_path']),
    ];

    foreach ($paths_to_check as $path) {
        $path = str_replace('\\', '/', $path);
        if (file_exists($path)) {
            $file_exists = true;
            $physical_path = $path;
            break;
        }
    }

    // Web path for viewing
    $web_path = '/dispensary_system/frontend/assets/uploads/documents/' . $document['file_name'];
    
    // Download URL - use download_document.php handler
    $download_url = 'download_document.php?id=' . $document['id'];

} catch (Exception $e) {
    header('Location: documents.php?error=database');
    exit;
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
// FUNCTIONS
// ================================================================
function getDocumentTypeLabel($type) {
    $labels = [
        'medical_record' => 'Medical Record',
        'referral_letter' => 'Referral Letter',
        'lab_result' => 'Lab Result',
        'prescription' => 'Prescription',
        'x_ray' => 'X-Ray',
        'scan' => 'Scan',
        'insurance' => 'Insurance',
        'id_document' => 'ID Document',
        'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function getDocumentTypeBadge($type) {
    $types = [
        'medical_record' => 'badge-blue',
        'referral_letter' => 'badge-purple',
        'lab_result' => 'badge-green',
        'prescription' => 'badge-orange',
        'x_ray' => 'badge-teal',
        'scan' => 'badge-indigo',
        'insurance' => 'badge-pink',
        'id_document' => 'badge-gray',
        'other' => 'badge-gray'
    ];
    return $types[$type] ?? 'badge-gray';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function getFileIcon($file_type) {
    if (strpos($file_type, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($file_type, 'image') !== false || strpos($file_type, 'jpg') !== false || strpos($file_type, 'png') !== false) return 'fa-file-image';
    if (strpos($file_type, 'word') !== false || strpos($file_type, 'doc') !== false) return 'fa-file-word';
    if (strpos($file_type, 'excel') !== false || strpos($file_type, 'xls') !== false) return 'fa-file-excel';
    return 'fa-file';
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
                <i class="fas fa-file-medical"></i> Document Details
            </h1>
            <p class="page-subtitle">
                View complete document information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-file mr-1"></i> <?= htmlspecialchars($document['document_number']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($document['patient_name']) ?>
                </span>
                <?php if ($document['is_verified']): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-check-circle mr-1"></i> Verified
                    </span>
                <?php else: ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> Pending
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-right">
            <a href="documents.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($file_exists): ?>
                <!-- DOWNLOAD BUTTON - Using download_document.php handler -->
                <a href="<?= $download_url ?>" class="btn btn-success" id="downloadBtn">
                    <i class="fas fa-download"></i> Download
                </a>
                <!-- VIEW BUTTON -->
                <a href="<?= $web_path ?>" target="_blank" class="btn btn-primary" id="viewBtn">
                    <i class="fas fa-eye"></i> View File
                </a>
            <?php endif; ?>
            <?php if (!$document['is_verified']): ?>
                <a href="verify_document.php?id=<?= $document['id'] ?>" class="btn btn-verify" onclick="return confirm('Verify this document?')">
                    <i class="fas fa-check"></i> Verify
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCUMENT PREVIEW -->
    <!-- ================================================================ -->
    <div class="preview-card">
        <div class="preview-icon">
            <i class="fas <?= getFileIcon($document['file_type'] ?? '') ?>"></i>
        </div>
        <div class="preview-info">
            <h2 class="preview-title"><?= htmlspecialchars($document['document_name']) ?></h2>
            <p class="preview-type">
                <span class="badge <?= getDocumentTypeBadge($document['document_type']) ?>">
                    <?= getDocumentTypeLabel($document['document_type']) ?>
                </span>
                <span class="preview-size">• <?= formatFileSize($document['file_size'] ?? 0) ?></span>
                <?php if ($file_exists): ?>
                    <span class="preview-size text-green-600">✅ File available</span>
                <?php else: ?>
                    <span class="preview-size text-red-600">❌ File not found</span>
                <?php endif; ?>
            </p>
            <div class="preview-actions">
                <?php if ($file_exists): ?>
                    <a href="<?= $web_path ?>" target="_blank" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View File
                    </a>
                    <a href="<?= $download_url ?>" class="btn btn-success btn-sm" id="downloadBtn2">
                        <i class="fas fa-download"></i> Download
                    </a>
                <?php else: ?>
                    <span class="btn btn-danger btn-sm disabled">
                        <i class="fas fa-exclamation-triangle"></i> File Not Available
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCUMENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-info-circle text-blue-600"></i> Document Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Document Number</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($document['document_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Document Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($document['document_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Document Type</span>
                    <span class="info-value">
                        <span class="badge <?= getDocumentTypeBadge($document['document_type']) ?>">
                            <?= getDocumentTypeLabel($document['document_type']) ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">File Name</span>
                    <span class="info-value text-sm"><?= htmlspecialchars($document['file_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">File Size</span>
                    <span class="info-value"><?= formatFileSize($document['file_size'] ?? 0) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">File Type</span>
                    <span class="info-value"><?= htmlspecialchars($document['file_type'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($document['description'])): ?>
                    <div class="info-row">
                        <span class="info-label">Description</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($document['description'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user text-green-600"></i> Related Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Patient</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($document['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($document['patient_code'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($document['patient_phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($document['patient_phone']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Doctor</span>
                    <span class="info-value"><?= htmlspecialchars($document['doctor_name'] ?? $doctor_name) ?></span>
                </div>
                <?php if (!empty($document['visit_number'])): ?>
                    <div class="info-row">
                        <span class="info-label">Visit</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($document['visit_number']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Uploaded On</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($document['upload_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">File Path</span>
                    <span class="info-value text-sm" style="word-break: break-all;"><?= htmlspecialchars($document['file_path']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Physical Path</span>
                    <span class="info-value text-sm" style="word-break: break-all; color: #059669;">
                        <?= htmlspecialchars($physical_path) ?>
                        <?php if ($file_exists): ?>
                            <span class="text-green-600"> ✅</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <?php if ($document['is_verified']): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i> Verified
                                <?php if ($document['verified_by_name']): ?>
                                    by <?= htmlspecialchars($document['verified_by_name']) ?>
                                <?php endif; ?>
                                <?php if ($document['verified_date']): ?>
                                    on <?= date('M d, Y', strtotime($document['verified_date'])) ?>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-clock"></i> Pending Verification
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Document Details
            <span class="separator">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
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
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .ml-2 { margin-left: 8px; }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
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
    
    .preview-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 24px;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    .preview-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .preview-icon {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        background: var(--primary-bg);
        color: var(--primary);
        flex-shrink: 0;
    }
    
    .preview-info { flex: 1; }
    .preview-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .preview-type {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 12px 0;
        flex-wrap: wrap;
    }
    .preview-size { font-size: 0.8rem; color: var(--text-secondary); }
    .text-green-600 { color: #059669; }
    .text-red-600 { color: #DC2626; }
    .preview-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    .badge-success { background: #059669; }
    .badge-warning { background: #D97706; }
    .badge-blue { background: var(--primary); }
    .badge-green { background: #059669; }
    .badge-purple { background: #7C3AED; }
    .badge-orange { background: #D97706; }
    .badge-teal { background: #0D9488; }
    .badge-indigo { background: #4F46E5; }
    .badge-pink { background: #DB2777; }
    .badge-gray { background: #64748B; }
    .badge-danger { background: #EF4444; }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .info-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .info-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .info-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .info-card-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    .info-row:last-child { border-bottom: none; }
    .info-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .info-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    .info-value.font-semibold { font-weight: 600; }
    .info-value.font-mono { font-family: monospace; }
    .info-value.text-sm { font-size: 0.8rem; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
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
    .btn-success {
        background: #059669;
        color: white;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-success:hover {
        background: #047857;
        transform: scale(1.05);
    }
    .btn-primary {
        background: var(--primary);
        color: white;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    .btn-verify {
        background: #7C3AED;
        color: white;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-verify:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    .btn-danger {
        background: #EF4444;
        color: white;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-danger:hover {
        background: #DC2626;
        transform: scale(1.05);
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-sm.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    .separator { color: var(--border-color); margin: 0 4px; }
    
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
    
    [data-theme="dark"] .preview-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .preview-icon {
        background: #1E3A5F;
    }
    [data-theme="dark"] .preview-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .info-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .info-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .info-row {
        border-color: #334155;
    }
    [data-theme="dark"] .info-value {
        color: #F1F5F9;
    }
    [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; }
    
    @media (max-width: 768px) {
        .preview-card {
            flex-direction: column;
            text-align: center;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
            max-width: 100%;
        }
        .preview-actions {
            justify-content: center;
        }
        .preview-card {
            padding: 16px 18px;
        }
        .preview-icon {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }
        .page-title {
            font-size: 1.2rem;
        }
        .page-header {
            flex-direction: column;
        }
        .page-header-right {
            width: 100%;
        }
        .page-header-right .btn {
            flex: 1;
            justify-content: center;
        }
        .page-subtitle {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        .separator { display: none; }
    }
    
    @media (max-width: 480px) {
        .preview-title {
            font-size: 1rem;
        }
        .preview-card {
            padding: 12px 14px;
        }
        .info-card {
            padding: 10px 12px;
        }
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .preview-card, .info-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
        }, 3500);
    }

    // ================================================================
    // DOWNLOAD BUTTON - Debug
    // ================================================================
    document.getElementById('downloadBtn')?.addEventListener('click', function(e) {
        console.log('⬇️ Download button clicked - ID: <?= $document['id'] ?>');
        console.log('📂 File: <?= htmlspecialchars($document['file_name']) ?>');
        console.log('📁 Physical Path: <?= htmlspecialchars($physical_path) ?>');
        console.log('🔗 Download URL: <?= $download_url ?>');
    });

    console.log('%c📄 Document Details - <?= htmlspecialchars($document['document_name']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📁 Patient: <?= htmlspecialchars($document['patient_name']) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📂 File: <?= htmlspecialchars($document['file_name']) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📊 Size: <?= formatFileSize($document['file_size'] ?? 0) ?>', 'font-size:12px; color:#64748B;');
    <?php if ($file_exists): ?>
        console.log('%c✅ File exists at: <?= htmlspecialchars($physical_path) ?>', 'font-size:12px; color:#34D399;');
        console.log('%c🔗 Download URL: <?= $download_url ?>', 'font-size:12px; color:#0B5ED7;');
    <?php else: ?>
        console.log('%c❌ File not found!', 'font-size:12px; color:#EF4444;');
    <?php endif; ?>
</script>

</body>
</html>