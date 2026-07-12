<?php
// ================================================================
// FILE: frontend/pages/doctor/documents.php
// DOCTOR - PATIENT DOCUMENTS (DOCTOR'S PATIENTS ONLY)
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
// GET SEARCH PARAMETERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$patient_filter = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// ================================================================
// GET DOCUMENTS - Only for patients of this doctor
// ================================================================
$sql = "
    SELECT 
        pd.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as doctor_name,
        v.visit_number
    FROM patient_documents pd
    JOIN patients p ON pd.patient_id = p.id
    LEFT JOIN users u ON pd.doctor_id = u.id
    LEFT JOIN visits v ON pd.visit_id = v.id
    WHERE pd.doctor_id = ?
    AND p.id IN (
        SELECT DISTINCT patient_id 
        FROM visits 
        WHERE doctor_id = ?
    )  -- ← Only patients assigned to this doctor
";

$params = [$doctor_id, $doctor_id];

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pd.document_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $sql .= " AND pd.document_type = ?";
    $params[] = $type_filter;
}

if ($patient_filter > 0) {
    $sql .= " AND pd.patient_id = ?";
    $params[] = $patient_filter;
}

$sql .= " ORDER BY pd.upload_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - Only for patients of this doctor
// ================================================================
$total_documents = count($documents);
$verified_count = 0;
$unverified_count = 0;
$type_counts = [];

foreach ($documents as $doc) {
    if ($doc['is_verified']) {
        $verified_count++;
    } else {
        $unverified_count++;
    }
    
    $type = $doc['document_type'] ?? 'other';
    if (!isset($type_counts[$type])) {
        $type_counts[$type] = 0;
    }
    $type_counts[$type]++;
}

// ================================================================
// GET PATIENTS FOR FILTER - Only patients assigned to this doctor
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$doctor_id]);
$patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function getFileIcon($file_type) {
    if (strpos($file_type, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($file_type, 'image') !== false || strpos($file_type, 'jpg') !== false || strpos($file_type, 'png') !== false) return 'fa-file-image';
    if (strpos($file_type, 'word') !== false || strpos($file_type, 'doc') !== false) return 'fa-file-word';
    if (strpos($file_type, 'excel') !== false || strpos($file_type, 'xls') !== false) return 'fa-file-excel';
    return 'fa-file';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
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
                <i class="fas fa-file-medical mr-2" style="color: #0B5ED7;"></i> Patient Documents
            </h1>
            <p class="page-subtitle">
                View and manage documents for your patients
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-file mr-1"></i> <?= $total_documents ?> documents
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="upload_document.php" class="btn btn-blue btn-sm">
                <i class="fas fa-upload"></i> Upload Document
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Total Documents</p>
                <p class="stat-number"><?= $total_documents ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-file"></i></div>
        </div>
        <div class="stat-card green">
            <div>
                <p class="stat-label">Verified</p>
                <p class="stat-number"><?= $verified_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card yellow">
            <div>
                <p class="stat-label">Pending Verification</p>
                <p class="stat-number"><?= $unverified_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Document Types</p>
                <p class="stat-number"><?= count($type_counts) ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
        </div>
    </div>

    <!-- Search & Filter - Only Doctor's Patients -->
    <div class="card mb-6">
        <div class="filter-info">
            <i class="fas fa-info-circle text-blue-600"></i>
            <span class="text-sm text-gray-500">Showing documents for <strong>your patients</strong> only (Dr. <?= htmlspecialchars($doctor_name) ?>)</span>
        </div>
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient or document name..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="type" class="filter-select">
                    <option value="">All Types</option>
                    <option value="medical_record" <?= $type_filter === 'medical_record' ? 'selected' : '' ?>>Medical Record</option>
                    <option value="referral_letter" <?= $type_filter === 'referral_letter' ? 'selected' : '' ?>>Referral Letter</option>
                    <option value="lab_result" <?= $type_filter === 'lab_result' ? 'selected' : '' ?>>Lab Result</option>
                    <option value="prescription" <?= $type_filter === 'prescription' ? 'selected' : '' ?>>Prescription</option>
                    <option value="x_ray" <?= $type_filter === 'x_ray' ? 'selected' : '' ?>>X-Ray</option>
                    <option value="scan" <?= $type_filter === 'scan' ? 'selected' : '' ?>>Scan</option>
                    <option value="insurance" <?= $type_filter === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                    <option value="id_document" <?= $type_filter === 'id_document' ? 'selected' : '' ?>>ID Document</option>
                    <option value="other" <?= $type_filter === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
                <select name="patient_id" class="filter-select">
                    <option value="">All Patients</option>
                    <?php foreach ($patients_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-blue btn-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $type_filter || $patient_filter): ?>
                    <a href="documents.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Documents Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Document</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $index => $doc): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="document-icon <?= getDocumentTypeBadge($doc['document_type']) ?>">
                                            <i class="fas <?= getFileIcon($doc['file_type'] ?? '') ?>"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($doc['document_name']) ?></div>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($doc['document_number'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($doc['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($doc['patient_code'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= getDocumentTypeBadge($doc['document_type']) ?>">
                                        <?= getDocumentTypeLabel($doc['document_type']) ?>
                                    </span>
                                </td>
                                <td><?= formatFileSize($doc['file_size'] ?? 0) ?></td>
                                <td>
                                    <?php if ($doc['is_verified']): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm"><?= time_ago($doc['upload_date'] ?? '') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_document.php?id=<?= $doc['id'] ?>" class="btn btn-view btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-success btn-sm" title="Download" target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (!$doc['is_verified']): ?>
                                            <a href="verify_document.php?id=<?= $doc['id'] ?>" class="btn btn-verify btn-sm" title="Verify" onclick="return confirm('Verify this document?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-muted">
                                <i class="fas fa-file-medical text-3xl block mb-2"></i>
                                <?php if ($search || $type_filter || $patient_filter): ?>
                                    No documents found matching your filters
                                <?php else: ?>
                                    No documents found for your patients. Click "Upload Document" to add one.
                                <?php endif; ?>
                                <div class="text-xs text-muted mt-2">
                                    <i class="fas fa-info-circle"></i> Showing documents for <strong>your patients</strong> only
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Documents
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
    /* ================================================================
       STATS GRID
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card.blue .stat-icon { background: var(--primary); }
    .stat-card.green .stat-icon { background: #059669; }
    .stat-card.yellow .stat-icon { background: #D97706; }
    .stat-card.purple .stat-icon { background: #7C3AED; }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* ================================================================
       FILTER INFO
       ================================================================ */
    .filter-info {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: var(--primary-bg);
        border-radius: 8px;
        margin-bottom: 12px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    
    [data-theme="dark"] .filter-info {
        background: #1E3A5F;
        border-color: #1E3A5F;
    }
    
    .filter-info .text-blue-600 { color: var(--primary); }
    .filter-info .text-gray-500 { color: var(--text-secondary); }
    
    /* ================================================================
       CARD
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .mb-6 { margin-bottom: 1.5rem; }
    
    /* ================================================================
       FILTER FORM
       ================================================================ */
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        width: 100%;
    }
    
    .filter-search {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 200px;
        background: var(--bg-card);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        transition: all 0.3s;
        padding: 0 12px;
    }
    
    .filter-search:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .filter-search .fa-search {
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    
    .filter-input {
        border: none;
        background: transparent;
        padding: 8px 12px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    
    .filter-input::placeholder {
        color: var(--text-muted);
    }
    
    .filter-select {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        font-size: 0.85rem;
        outline: none;
        transition: all 0.3s;
        cursor: pointer;
        min-width: 140px;
    }
    
    .filter-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .data-table td .font-medium { font-weight: 500; }
    .data-table td .text-xs { font-size: 0.75rem; }
    .data-table td .text-sm { font-size: 0.8rem; }
    .data-table td .text-muted { color: var(--text-muted); }
    
    /* ================================================================
       DOCUMENT ICON
       ================================================================ */
    .document-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        color: white;
        flex-shrink: 0;
    }
    
    .document-icon.badge-blue { background: var(--primary); }
    .document-icon.badge-green { background: #059669; }
    .document-icon.badge-purple { background: #7C3AED; }
    .document-icon.badge-orange { background: #D97706; }
    .document-icon.badge-teal { background: #0D9488; }
    .document-icon.badge-indigo { background: #4F46E5; }
    .document-icon.badge-pink { background: #DB2777; }
    .document-icon.badge-gray { background: #64748B; }
    
    /* ================================================================
       BADGES
       ================================================================ */
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
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    .badge-blue { background: var(--primary); }
    .badge-green { background: #059669; }
    .badge-purple { background: #7C3AED; }
    .badge-orange { background: #D97706; }
    .badge-teal { background: #0D9488; }
    .badge-indigo { background: #4F46E5; }
    .badge-pink { background: #DB2777; }
    .badge-gray { background: #64748B; }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
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
    
    .btn-blue {
        background: var(--primary);
        color: #fff;
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
    
    .btn-view {
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
    .btn-view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
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
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        justify-content: center;
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
    }
    
    .page-header .page-title {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: var(--primary-light);
    }
    
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
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
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* ================================================================
       UTILITIES
       ================================================================ */
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-muted { color: var(--text-muted); }
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .text-center { text-align: center; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-3xl { font-size: 1.875rem; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .w-full { width: 100%; }
    .min-w-\[140px\] { min-width: 140px; }
    .min-w-\[200px\] { min-width: 200px; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .stat-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card .stat-number {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card .stat-label {
        color: #94A3B8;
    }
    [data-theme="dark"] .card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #1E293B;
    }
    [data-theme="dark"] .filter-search {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .filter-input {
        color: #F1F5F9;
    }
    [data-theme="dark"] .filter-select {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    [data-theme="dark"] .filter-info {
        background: #1E3A5F;
        border-color: #1E3A5F;
    }
    [data-theme="dark"] .filter-info .text-gray-500 {
        color: #94A3B8;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .card {
            padding: 14px 16px;
        }
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-search {
            min-width: 100%;
        }
        .filter-select {
            width: 100%;
            min-width: 100%;
        }
        .stat-card {
            padding: 14px 16px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .action-buttons {
            flex-wrap: wrap;
            justify-content: center;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .filter-form .btn {
            width: 100%;
            justify-content: center;
        }
        .document-icon {
            width: 30px;
            height: 30px;
            font-size: 0.7rem;
        }
        .filter-info {
            font-size: 0.8rem;
            padding: 6px 12px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
            font-size: 0.7rem;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.55rem;
        }
        .action-buttons {
            gap: 3px;
        }
        .document-icon {
            width: 24px;
            height: 24px;
            font-size: 0.6rem;
        }
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

    console.log('%c📄 Patient Documents - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_documents ?> | Verified: <?= $verified_count ?> | Pending: <?= $unverified_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔒 Doctor ID: <?= $doctor_id ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c👥 Patients: <?= count($patients_list) ?> patients assigned', 'font-size:12px; color:#64748B;');
    console.log('%c✅ Only documents for doctor\'s patients are shown', 'font-size:12px; color:#059669;');
</script>

</body>
</html>