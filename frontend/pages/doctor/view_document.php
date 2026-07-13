<?php
// ================================================================
// FILE: frontend/pages/doctor/view_document.php
// DOCTOR - VIEW DOCUMENT DETAILS
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
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCUMENT DETAILS
// ================================================================
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
                <i class="fas fa-file-medical mr-2" style="color: #0B5ED7;"></i> Document Details
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
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="documents.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="<?= htmlspecialchars($document['file_path']) ?>" class="btn btn-success btn-sm" target="_blank">
                <i class="fas fa-download"></i> Download
            </a>
            <?php if (!$document['is_verified']): ?>
                <a href="verify_document.php?id=<?= $document['id'] ?>" class="btn btn-verify btn-sm" onclick="return confirm('Verify this document?')">
                    <i class="fas fa-check"></i> Verify
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
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
            </p>
            <div class="preview-actions">
                <a href="<?= htmlspecialchars($document['file_path']) ?>" class="btn btn-success btn-sm" target="_blank">
                    <i class="fas fa-eye"></i> View File
                </a>
                <a href="<?= htmlspecialchars($document['file_path']) ?>" class="btn btn-blue btn-sm" download>
                    <i class="fas fa-download"></i> Download
                </a>
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
            <span class="text-gray-300 mx-2">|</span>
            Document Details
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
    
    .preview-info {
        flex: 1;
    }
    
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
    }
    
    .preview-size {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
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
        color: #fff;
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
    
    .info-row:last-child {
        border-bottom: none;
    }
    
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
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-success:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-blue {
        background: var(--primary);
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .btn-verify {
        background: #7C3AED;
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-verify:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .text-muted { color: var(--text-muted); }
    
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

    console.log('%c📄 Document Details - <?= htmlspecialchars($document['document_name']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📁 Patient: <?= htmlspecialchars($document['patient_name']) ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ View document page loaded', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>