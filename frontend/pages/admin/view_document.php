<?php
// ================================================================
// FILE: frontend/pages/admin/view_document.php
// SUPER ADMIN - VIEW DOCUMENT DETAILS
// ✅ BLUE THEME ONLY
// ✅ Preview, Download, Edit, Delete buttons
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
$doc_type = $_GET['type'] ?? 'patient_documents'; // 'external' or 'patient_documents'

if ($document_id <= 0) {
    header('Location: documents.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// HANDLE DELETE
// ================================================================
if (isset($_GET['delete']) && $_GET['delete'] == 1) {
    try {
        $db->beginTransaction();
        
        if ($doc_type === 'external') {
            $stmt = $db->prepare("SELECT file_path FROM external_sick_sheets WHERE id = ?");
            $stmt->execute([$document_id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($file && !empty($file['file_path'])) {
                $file_full_path = $_SERVER['DOCUMENT_ROOT'] . $file['file_path'];
                if (file_exists($file_full_path)) @unlink($file_full_path);
            }
            
            $stmt = $db->prepare("DELETE FROM external_sick_sheets WHERE id = ?");
            $stmt->execute([$document_id]);
        } else {
            $stmt = $db->prepare("SELECT file_path FROM patient_documents WHERE id = ?");
            $stmt->execute([$document_id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($file && !empty($file['file_path'])) {
                $file_full_path = $_SERVER['DOCUMENT_ROOT'] . $file['file_path'];
                if (file_exists($file_full_path)) @unlink($file_full_path);
            }
            
            $stmt = $db->prepare("DELETE FROM patient_documents WHERE id = ?");
            $stmt->execute([$document_id]);
        }
        
        try {
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'document_deleted', ?, NOW())");
            $log_stmt->execute([$user_id, $user_branch_id, "Deleted document #$document_id ($doc_type)"]);
        } catch (Exception $e) {}
        
        $db->commit();
        header('Location: documents.php?branch=' . urlencode($selected_branch_id) . '&deleted=1');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error_message = "Failed to delete: " . $e->getMessage();
    }
}

// ================================================================
// FETCH DOCUMENT
// ================================================================
$document = null;
$patient = null;

try {
    if ($doc_type === 'external') {
        // External sick sheet
        $stmt = $db->prepare("
            SELECT 
                ess.*,
                'external_sick_sheet' as source_type,
                ess.full_name as patient_name,
                ess.patient_id as patient_code,
                ess.phone as patient_phone,
                ess.gender as patient_gender,
                ess.date_of_birth as patient_dob,
                ess.address as patient_address,
                ess.blood_group as patient_blood,
                ess.allergies as patient_allergies,
                ess.diagnosis as document_diagnosis,
                ess.treatment as document_treatment,
                ess.sick_days as document_days,
                ess.sick_from as document_from,
                ess.sick_to as document_to,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                b.name as branch_name,
                b.location as branch_location
            FROM external_sick_sheets ess
            LEFT JOIN users u ON ess.doctor_id = u.id
            LEFT JOIN branches b ON ess.branch_id = b.id
            WHERE ess.id = ?
        ");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $document['document_number'] = $document['document_number'] ?? 'N/A';
            $document['document_title'] = 'Sick Sheet - ' . ($document['patient_name'] ?? 'Unknown');
            $document['document_name'] = $document['file_name'] ?? 'Sick Sheet';
            $document['document_type'] = 'sick_sheet';
            $document['uploaded_by_name'] = $document['doctor_name'] ?? 'Doctor';
            $document['upload_date'] = $document['created_at'];
        }
    } else {
        // Patient document
        $stmt = $db->prepare("
            SELECT 
                pd.*,
                'patient_document' as source_type,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone as patient_phone,
                p.gender as patient_gender,
                p.date_of_birth as patient_dob,
                p.address as patient_address,
                p.blood_group as patient_blood,
                p.allergies as patient_allergies,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                ub.full_name as uploaded_by_name,
                b.name as branch_name,
                b.location as branch_location
            FROM patient_documents pd
            LEFT JOIN patients p ON pd.patient_id = p.id
            LEFT JOIN users u ON pd.doctor_id = u.id
            LEFT JOIN users ub ON pd.uploaded_by = ub.id
            LEFT JOIN branches b ON pd.branch_id = b.id
            WHERE pd.id = ?
        ");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $document['document_diagnosis'] = $document['sick_sheet_diagnosis'] ?? null;
            $document['document_days'] = $document['sick_sheet_days'] ?? null;
            $document['document_from'] = $document['sick_sheet_from_date'] ?? null;
            $document['document_to'] = $document['sick_sheet_to_date'] ?? null;
            $document['document_recommendations'] = $document['sick_sheet_recommendations'] ?? null;
            $document['document_restrictions'] = $document['sick_sheet_restrictions'] ?? null;
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
// DOCUMENT TYPE HELPERS
// ================================================================
function getTypeLabel($type) {
    $labels = [
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
    return $labels[$type] ?? '📄 ' . ucfirst($type);
}

function getTypeBadgeClass($type) {
    switch ($type) {
        case 'sick_sheet': return 'badge-blue-1';
        case 'report': return 'badge-blue-2';
        case 'prescription': return 'badge-blue-3';
        case 'lab_result': return 'badge-blue-4';
        default: return 'badge-blue-1';
    }
}

function getFileIcon($file_type, $file_name) {
    $ext = strtolower(pathinfo($file_name ?? '', PATHINFO_EXTENSION));
    
    if ($ext === 'pdf') return 'fa-file-pdf';
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) return 'fa-file-image';
    if (in_array($ext, ['doc', 'docx'])) return 'fa-file-word';
    if (in_array($ext, ['xls', 'xlsx'])) return 'fa-file-excel';
    if ($ext === 'txt') return 'fa-file-alt';
    
    return 'fa-file';
}

function isImage($file_name) {
    $ext = strtolower(pathinfo($file_name ?? '', PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
}

function isPDF($file_name) {
    $ext = strtolower(pathinfo($file_name ?? '', PATHINFO_EXTENSION));
    return $ext === 'pdf';
}

function formatFileSize($bytes) {
    if (!$bytes) return 'N/A';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 2) . ' KB';
    return number_format($bytes / (1024 * 1024), 2) . ' MB';
}

$file_path = $document['file_path'] ?? '';
$file_name = $document['file_name'] ?? '';
$file_exists = !empty($file_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $file_path);

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

.page-header-custom .header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.page-header-custom .btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
    cursor: pointer;
}

.page-header-custom .btn-header:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.page-header-custom .btn-header.primary {
    background: white;
    color: #0B5ED7;
    border: none;
}

.page-header-custom .btn-header.primary:hover {
    background: #F0F7FF;
}

.page-header-custom .btn-header.danger {
    background: rgba(220,38,38,0.85);
    border-color: rgba(220,38,38,0.9);
}

.page-header-custom .btn-header.danger:hover {
    background: #DC2626;
}

/* CARD */
.card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 24px 28px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    transition: all 0.3s;
}

.card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    padding-bottom: 12px;
    margin-bottom: 18px;
    border-bottom: 2px solid var(--primary-bg);
}

.section-title i {
    width: 34px;
    height: 34px;
    background: var(--primary);
    color: white;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* INFO GRID */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.info-item {
    padding: 12px 16px;
    background: var(--primary-bg);
    border-radius: 10px;
    border-left: 4px solid var(--primary);
    transition: all 0.3s;
}

.info-item:hover {
    transform: translateX(3px);
}

.info-item .info-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 4px;
}

.info-item .info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    display: block;
    word-break: break-word;
}

.info-item .info-value.big {
    font-size: 1rem;
    color: var(--primary);
}

.info-item.full-width {
    grid-column: 1 / -1;
}

/* BADGE */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-blue-1 { background: #0B5ED7; color: white; }
.badge-blue-2 { background: #1A73E8; color: white; }
.badge-blue-3 { background: #0A4CA8; color: white; }
.badge-blue-4 { background: #6EA8FE; color: white; }
.badge-blue-5 { background: #1E40AF; color: white; }

/* PATIENT CARD */
.patient-card {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 18px 22px;
    background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
    border-radius: 14px;
    border: 2px solid var(--primary-light);
    margin-bottom: 20px;
}

[data-theme="dark"] .patient-card {
    background: linear-gradient(135deg, #1E3A5F, #1E40AF);
    border-color: #3B82F6;
}

.patient-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.8rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    border: 3px solid white;
}

.patient-info h2 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.patient-info p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 2px 0;
}

/* FILE PREVIEW */
.file-preview {
    background: var(--primary-bg);
    border-radius: 14px;
    border: 2px solid var(--primary-light);
    padding: 20px;
    text-align: center;
}

.file-preview img {
    max-width: 100%;
    max-height: 500px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.2);
    border: 3px solid white;
}

.file-preview iframe {
    width: 100%;
    height: 600px;
    border-radius: 10px;
    border: 2px solid var(--primary-light);
    background: white;
}

.file-icon-big {
    font-size: 5rem;
    color: var(--primary);
    margin-bottom: 16px;
}

.file-name-preview {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
    word-break: break-all;
}

.file-meta {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.file-missing {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-secondary);
}

.file-missing i {
    font-size: 3rem;
    color: #DC2626;
    margin-bottom: 12px;
    display: block;
}

/* ACTION BUTTONS */
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 18px 12px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s;
    border: 2px solid transparent;
    cursor: pointer;
    min-height: 100px;
    text-align: center;
}

.action-btn i {
    font-size: 1.5rem;
    margin-bottom: 8px;
}

.action-btn .btn-label {
    font-size: 0.75rem;
    font-weight: 700;
}

.action-btn .btn-sublabel {
    font-size: 0.62rem;
    font-weight: 400;
    opacity: 0.85;
    margin-top: 2px;
}

.action-btn-blue-1 {
    background: #E8F0FE;
    color: #0B5ED7;
    border-color: #6EA8FE;
}
.action-btn-blue-1:hover {
    background: #0B5ED7;
    color: white;
    border-color: #0B5ED7;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.3);
}

.action-btn-blue-2 {
    background: #DBEAFE;
    color: #0A4CA8;
    border-color: #93C5FD;
}
.action-btn-blue-2:hover {
    background: #0A4CA8;
    color: white;
    border-color: #0A4CA8;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(10, 76, 168, 0.3);
}

.action-btn-blue-3 {
    background: #E0F2FE;
    color: #0B3D8A;
    border-color: #7DD3FC;
}
.action-btn-blue-3:hover {
    background: #0B3D8A;
    color: white;
    border-color: #0B3D8A;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(11, 61, 138, 0.3);
}

.action-btn-danger {
    background: #FEE2E2;
    color: #DC2626;
    border-color: #FCA5A5;
}
.action-btn-danger:hover {
    background: #DC2626;
    color: white;
    border-color: #DC2626;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
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
    .card { padding: 16px; }
    .page-header-custom { padding: 18px 20px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .header-actions { width: 100%; }
    .header-actions .btn-header { flex: 1; justify-content: center; }
    .info-grid { grid-template-columns: 1fr; }
    .patient-card { flex-direction: column; text-align: center; }
    .file-preview iframe { height: 400px; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-alt"></i> Document Details
                <span class="badge-branch">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>
                </span>
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($document['branch_name'] ?? 'N/A') ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                View complete document information
            </p>
        </div>
        <div class="header-actions">
            <a href="documents.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($file_exists): ?>
                <a href="<?= htmlspecialchars($file_path) ?>" download class="btn-header primary">
                    <i class="fas fa-download"></i> Download
                </a>
            <?php endif; ?>
            <a href="edit_document.php?id=<?= $document['id'] ?>&branch=<?= $selected_branch_id ?>&type=<?= $doc_type ?>" class="btn-header">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="?id=<?= $document['id'] ?>&branch=<?= $selected_branch_id ?>&type=<?= $doc_type ?>&delete=1" 
               class="btn-header danger"
               onclick="return confirm('⚠️ Delete this document?\n\nTitle: <?= htmlspecialchars(addslashes($document['document_title'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($document['patient_name'] ?? 'N/A')) ?>\n\nThis action cannot be undone!');">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div style="background:#FEE2E2;color:#991B1B;padding:14px 20px;border-radius:12px;margin-bottom:16px;border:2px solid #FCA5A5;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- PATIENT CARD -->
    <?php if (!empty($document['patient_name'])): ?>
    <div class="patient-card">
        <div class="patient-avatar">
            <?= strtoupper(substr($document['patient_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-info" style="flex:1;">
            <h2><?= htmlspecialchars($document['patient_name']) ?></h2>
            <p>
                <i class="fas fa-id-card"></i> <?= htmlspecialchars($document['patient_code'] ?? 'N/A') ?>
                <?php if (!empty($document['patient_phone'])): ?>
                    <span style="margin-left:12px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($document['patient_phone']) ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php if (!empty($document['patient_gender'])): ?>
                    <i class="fas fa-venus-mars"></i> <?= htmlspecialchars($document['patient_gender']) ?>
                <?php endif; ?>
                <?php if (!empty($document['patient_dob'])): ?>
                    <span style="margin-left:12px;"><i class="fas fa-birthday-cake"></i> <?= date('M d, Y', strtotime($document['patient_dob'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div style="text-align:right;">
            <span class="badge <?= getTypeBadgeClass($document['document_type'] ?? 'other') ?>" style="font-size:0.8rem;padding:10px 22px;">
                <?= getTypeLabel($document['document_type'] ?? 'other') ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- DOCUMENT INFORMATION -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-info-circle"></i>
            <span>Document Information</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Document Number</span>
                <span class="info-value big" style="font-family:monospace;">
                    <?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Document Type</span>
                <span class="info-value"><?= getTypeLabel($document['document_type'] ?? 'other') ?></span>
            </div>
            
            <div class="info-item full-width">
                <span class="info-label">Document Title</span>
                <span class="info-value big"><?= htmlspecialchars($document['document_title'] ?? 'N/A') ?></span>
            </div>
            
            <?php if (!empty($document['document_name'])): ?>
            <div class="info-item">
                <span class="info-label">Document Name</span>
                <span class="info-value"><?= htmlspecialchars($document['document_name']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">Branch</span>
                <span class="info-value">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($document['branch_name'] ?? 'N/A') ?>
                </span>
            </div>
            
            <?php if (!empty($document['uploaded_by_name'])): ?>
            <div class="info-item">
                <span class="info-label">Uploaded By</span>
                <span class="info-value">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($document['uploaded_by_name']) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['doctor_name'])): ?>
            <div class="info-item">
                <span class="info-label">Doctor</span>
                <span class="info-value">
                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($document['doctor_name']) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">Upload Date</span>
                <span class="info-value">
                    <i class="fas fa-calendar"></i>
                    <?= !empty($document['upload_date']) ? date('M d, Y', strtotime($document['upload_date'])) : 'N/A' ?>
                    <?php if (!empty($document['upload_date'])): ?>
                        <span style="font-weight:400;color:var(--text-secondary);font-size:0.75rem;margin-left:4px;">
                            at <?= date('h:i A', strtotime($document['upload_date'])) ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if (!empty($document['file_size'])): ?>
            <div class="info-item">
                <span class="info-label">File Size</span>
                <span class="info-value">
                    <i class="fas fa-hdd"></i> <?= formatFileSize($document['file_size']) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['file_type'])): ?>
            <div class="info-item">
                <span class="info-label">File Type</span>
                <span class="info-value" style="font-family:monospace;font-size:0.8rem;">
                    <?= htmlspecialchars($document['file_type']) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['description'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Description</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($document['description'])) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SICK SHEET DETAILS (if applicable) -->
    <?php if (($document['document_type'] ?? '') === 'sick_sheet' || !empty($document['document_diagnosis'])): ?>
    <div class="card">
        <div class="section-title">
            <i class="fas fa-file-medical"></i>
            <span>Sick Sheet Details</span>
        </div>
        
        <div class="info-grid">
            <?php if (!empty($document['document_days'])): ?>
            <div class="info-item">
                <span class="info-label">Sick Days</span>
                <span class="info-value big">
                    <i class="fas fa-calendar-day"></i> <?= (int)$document['document_days'] ?> days
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['document_from'])): ?>
            <div class="info-item">
                <span class="info-label">From Date</span>
                <span class="info-value">
                    <i class="fas fa-calendar-check"></i> <?= date('M d, Y', strtotime($document['document_from'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['document_to'])): ?>
            <div class="info-item">
                <span class="info-label">To Date</span>
                <span class="info-value">
                    <i class="fas fa-calendar-times"></i> <?= date('M d, Y', strtotime($document['document_to'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['document_diagnosis'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Diagnosis</span>
                <span class="info-value"><?= htmlspecialchars($document['document_diagnosis']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['document_recommendations'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Recommendations</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($document['document_recommendations'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['document_restrictions'])): ?>
            <div class="info-item full-width">
                <span class="info-label">⚠️ Restrictions</span>
                <span class="info-value" style="color:#DC2626;">
                    <?= htmlspecialchars($document['document_restrictions']) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- PATIENT DETAILS -->
    <?php if (!empty($document['patient_name'])): ?>
    <div class="card">
        <div class="section-title">
            <i class="fas fa-user"></i>
            <span>Patient Details</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value big"><?= htmlspecialchars($document['patient_name']) ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Patient ID</span>
                <span class="info-value" style="font-family:monospace;"><?= htmlspecialchars($document['patient_code'] ?? 'N/A') ?></span>
            </div>
            
            <?php if (!empty($document['patient_phone'])): ?>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <span class="info-value"><i class="fas fa-phone"></i> <?= htmlspecialchars($document['patient_phone']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['patient_gender'])): ?>
            <div class="info-item">
                <span class="info-label">Gender</span>
                <span class="info-value"><?= htmlspecialchars($document['patient_gender']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['patient_dob'])): ?>
            <div class="info-item">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?= date('M d, Y', strtotime($document['patient_dob'])) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['patient_blood'])): ?>
            <div class="info-item">
                <span class="info-label">Blood Group</span>
                <span class="info-value" style="color:#DC2626;font-weight:700;"><?= htmlspecialchars($document['patient_blood']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['patient_allergies'])): ?>
            <div class="info-item full-width">
                <span class="info-label">⚠️ Allergies</span>
                <span class="info-value" style="color:#DC2626;"><?= htmlspecialchars($document['patient_allergies']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($document['patient_address'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Address</span>
                <span class="info-value" style="font-weight:400;"><?= htmlspecialchars($document['patient_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- FILE PREVIEW -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-eye"></i>
            <span>File Preview</span>
        </div>
        
        <?php if ($file_exists): ?>
            <div class="file-preview">
                <?php if (isImage($file_name)): ?>
                    <img src="<?= htmlspecialchars($file_path) ?>" alt="<?= htmlspecialchars($document['document_title'] ?? 'Document') ?>">
                <?php elseif (isPDF($file_name)): ?>
                    <iframe src="<?= htmlspecialchars($file_path) ?>#toolbar=0" type="application/pdf"></iframe>
                <?php else: ?>
                    <div class="file-icon-big">
                        <i class="fas <?= getFileIcon($document['file_type'] ?? '', $file_name) ?>"></i>
                    </div>
                    <div class="file-name-preview"><?= htmlspecialchars($file_name) ?></div>
                    <div class="file-meta">
                        <?= formatFileSize($document['file_size'] ?? 0) ?> • 
                        <?= htmlspecialchars($document['file_type'] ?? 'Unknown') ?>
                    </div>
                    <a href="<?= htmlspecialchars($file_path) ?>" download class="btn btn-primary" 
                       style="display:inline-flex;padding:10px 24px;border-radius:10px;background:linear-gradient(135deg,#0B5ED7,#0A4CA8);color:white;text-decoration:none;font-weight:600;gap:6px;">
                        <i class="fas fa-download"></i> Download File
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="file-preview">
                <div class="file-missing">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p style="font-size:1rem;font-weight:600;color:#DC2626;">File Not Found</p>
                    <p style="font-size:0.85rem;">The file may have been deleted or moved</p>
                    <?php if (!empty($file_path)): ?>
                        <p style="font-size:0.75rem;font-family:monospace;margin-top:12px;word-break:break-all;">
                            Path: <?= htmlspecialchars($file_path) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-bolt"></i>
            <span>Quick Actions</span>
        </div>
        
        <div class="action-grid">
            <?php if ($file_exists): ?>
            <a href="<?= htmlspecialchars($file_path) ?>" target="_blank" class="action-btn action-btn-blue-1">
                <i class="fas fa-external-link-alt"></i>
                <span class="btn-label">Open</span>
                <span class="btn-sublabel">New tab</span>
            </a>
            
            <a href="<?= htmlspecialchars($file_path) ?>" download class="action-btn action-btn-blue-2">
                <i class="fas fa-download"></i>
                <span class="btn-label">Download</span>
                <span class="btn-sublabel">Save file</span>
            </a>
            <?php endif; ?>
            
            <a href="edit_document.php?id=<?= $document['id'] ?>&branch=<?= $selected_branch_id ?>&type=<?= $doc_type ?>" class="action-btn action-btn-blue-3">
                <i class="fas fa-edit"></i>
                <span class="btn-label">Edit</span>
                <span class="btn-sublabel">Modify details</span>
            </a>
            
            <?php if (!empty($document['patient_id'])): ?>
            <a href="patient_details.php?id=<?= $document['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="action-btn action-btn-blue-1">
                <i class="fas fa-user-circle"></i>
                <span class="btn-label">View Patient</span>
                <span class="btn-sublabel">Full profile</span>
            </a>
            <?php endif; ?>
            
            <a href="?id=<?= $document['id'] ?>&branch=<?= $selected_branch_id ?>&type=<?= $doc_type ?>&delete=1" 
               class="action-btn action-btn-danger"
               onclick="return confirm('⚠️ Delete this document?\n\nThis action cannot be undone!');">
                <i class="fas fa-trash"></i>
                <span class="btn-label">Delete</span>
                <span class="btn-sublabel">Remove permanently</span>
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Document #<?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// UPDATE FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// ================================================================
// ESC KEY TO GO BACK
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'documents.php?branch=<?= $selected_branch_id ?>';
    }
});

console.log('%c📄 Admin - View Document (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Blue theme applied everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ Image & PDF preview supported', 'font-size:12px;color:#34D399;');
console.log('%c✅ Document: <?= htmlspecialchars($document['document_number'] ?? 'N/A') ?>', 'font-size:12px;color:#64748B;');
console.log('%c⌨️ Press ESC to go back', 'font-size:12px;color:#64748B;');
</script>

</body>
</html>