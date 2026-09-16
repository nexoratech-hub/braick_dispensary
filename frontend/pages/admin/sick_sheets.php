<?php
// ================================================================
// FILE: frontend/pages/admin/sick_sheets.php
// SUPER ADMIN - SICK SHEETS MANAGEMENT
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only (no duplicates)
// ✅ Scroll buttons < > in table header
// ✅ Live search with highlight
// ✅ Download button opens PDF in new page
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

$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DELETE HANDLER
// ================================================================
$message = '';
$message_type = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $ss_id = (int)$_GET['delete'];
    $ss_type = $_GET['type'] ?? 'external';
    
    try {
        $db->beginTransaction();
        
        if ($ss_type === 'external') {
            $stmt = $db->prepare("SELECT file_path FROM external_sick_sheets WHERE id = ?");
            $stmt->execute([$ss_id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($file && !empty($file['file_path'])) {
                $file_full_path = $_SERVER['DOCUMENT_ROOT'] . $file['file_path'];
                if (file_exists($file_full_path)) @unlink($file_full_path);
            }
            
            $stmt = $db->prepare("DELETE FROM external_sick_sheets WHERE id = ?");
            $stmt->execute([$ss_id]);
        } else {
            $stmt = $db->prepare("SELECT file_path FROM patient_documents WHERE id = ? AND document_type = 'sick_sheet'");
            $stmt->execute([$ss_id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($file && !empty($file['file_path'])) {
                $file_full_path = $_SERVER['DOCUMENT_ROOT'] . $file['file_path'];
                if (file_exists($file_full_path)) @unlink($file_full_path);
            }
            
            $stmt = $db->prepare("DELETE FROM patient_documents WHERE id = ? AND document_type = 'sick_sheet'");
            $stmt->execute([$ss_id]);
        }
        
        try {
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'sick_sheet_deleted', ?, NOW())");
            $log_stmt->execute([$user_id, $user_branch_id, "Deleted sick sheet #$ss_id ($ss_type)"]);
        } catch (Exception $e) {}
        
        $db->commit();
        $message = "Sick sheet deleted successfully!";
        $message_type = 'success';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "Failed to delete: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// FETCH DATA
// ================================================================
$search = $_GET['search'] ?? '';

// EXTERNAL
$ext_sql = "SELECT 
    'external' as source,
    ess.id, ess.document_number, ess.full_name, ess.patient_id, ess.phone, ess.gender,
    ess.date_of_birth, ess.diagnosis, ess.sick_days, ess.sick_from, ess.sick_to,
    ess.file_path, ess.created_at, ess.branch_id,
    u.full_name as doctor_name,
    b.name as branch_name
FROM external_sick_sheets ess
LEFT JOIN users u ON ess.doctor_id = u.id
LEFT JOIN branches b ON ess.branch_id = b.id
WHERE 1=1";
$ext_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $ext_sql .= " AND ess.branch_id = ?";
    $ext_params[] = (int)$selected_branch_id;
}
if (!empty($search)) {
    $ext_sql .= " AND (ess.full_name LIKE ? OR ess.document_number LIKE ? OR ess.patient_id LIKE ?)";
    $ext_params[] = "%$search%";
    $ext_params[] = "%$search%";
    $ext_params[] = "%$search%";
}

$stmt = $db->prepare($ext_sql);
$stmt->execute($ext_params);
$external_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// REGISTERED
$reg_sql = "SELECT 
    'registered' as source,
    pd.id, 
    pd.document_number, 
    p.full_name, 
    p.patient_id, 
    p.phone, 
    p.gender,
    p.date_of_birth, 
    pd.sick_sheet_diagnosis as diagnosis, 
    pd.sick_sheet_days as sick_days, 
    pd.sick_sheet_from_date as sick_from, 
    pd.sick_sheet_to_date as sick_to,
    pd.file_path, 
    pd.upload_date AS created_at,
    pd.branch_id,
    u.full_name as doctor_name,
    b.name as branch_name
FROM patient_documents pd
LEFT JOIN patients p ON pd.patient_id = p.id
LEFT JOIN users u ON pd.doctor_id = u.id
LEFT JOIN branches b ON pd.branch_id = b.id
WHERE pd.document_type = 'sick_sheet'";
$reg_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $reg_sql .= " AND pd.branch_id = ?";
    $reg_params[] = (int)$selected_branch_id;
}
if (!empty($search)) {
    $reg_sql .= " AND (p.full_name LIKE ? OR pd.document_number LIKE ? OR p.patient_id LIKE ?)";
    $reg_params[] = "%$search%";
    $reg_params[] = "%$search%";
    $reg_params[] = "%$search%";
}

$stmt = $db->prepare($reg_sql);
$stmt->execute($reg_params);
$registered_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_sheets = array_merge($external_sheets, $registered_sheets);
usort($all_sheets, function($a, $b) {
    $a_time = !empty($a['created_at']) ? strtotime($a['created_at']) : 0;
    $b_time = !empty($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $b_time - $a_time;
});

$total = count($all_sheets);
$external_count = count($external_sheets);
$registered_count = count($registered_sheets);

$total_days = 0;
foreach ($all_sheets as $s) $total_days += (int)($s['sick_days'] ?? 0);

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --ss-primary: #0B5ED7;
        --ss-primary-dark: #0A4CA8;
        --ss-primary-bg: #E8F0FE;
        --ss-bg-card: #FFFFFF;
        --ss-text-primary: #1E293B;
        --ss-text-secondary: #64748B;
        --ss-border-color: #E2E8F0;
        --ss-radius: 12px;
    }

    [data-theme="dark"] {
        --ss-bg-card: #1E293B;
        --ss-text-primary: #F1F5F9;
        --ss-text-secondary: #94A3B8;
        --ss-border-color: #334155;
        --ss-primary-bg: #1E3A5F;
    }

    /* ================================================================
       PAGE HEADER CARD
       ================================================================ */
    .ss-page-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 14px;
        padding: 18px 24px;
        margin-bottom: 18px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
        color: white;
    }

    .ss-page-header .title {
        color: white;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin: 0;
    }

    .ss-page-header .title i {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        border: 1px solid rgba(255,255,255,0.15);
    }

    .ss-page-header .branch-tag {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 12px;
        border-radius: 16px;
        font-size: 0.68rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .ss-page-header .subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.8rem;
        margin: 4px 0 0 0;
    }

    .ss-page-header .btn-new {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        background: white;
        color: #0B5ED7;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        text-decoration: none;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    .ss-page-header .btn-new:hover {
        background: #F8FAFC;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        color: #0A4CA8;
    }

    /* ================================================================
       STATS GRID
       ================================================================ */
    .ss-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 18px;
    }

    .ss-stat-card {
        border-radius: 12px;
        padding: 14px 18px;
        color: white;
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
    }

    .ss-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .ss-stat-card.blue-1 { background: #0B5ED7; }
    .ss-stat-card.blue-2 { background: #0A4CA8; }
    .ss-stat-card.blue-3 { background: #1A73E8; }
    .ss-stat-card.blue-4 { background: #0B3D8A; }

    .ss-stat-card .num {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .ss-stat-card .lbl {
        font-size: 0.7rem;
        opacity: 0.9;
        font-weight: 500;
    }

    .ss-stat-card .icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    /* ================================================================
       MESSAGE
       ================================================================ */
    .ss-message {
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .ss-message.success {
        background: #D1FAE5;
        color: #065F46;
        border: 1.5px solid #6EE7B7;
    }

    .ss-message.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 1.5px solid #FCA5A5;
    }

    [data-theme="dark"] .ss-message.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .ss-message.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       TABLE CARD
       ================================================================ */
    .ss-table-card {
        background: var(--ss-bg-card);
        border-radius: 14px;
        border: 1.5px solid var(--ss-border-color);
        overflow: hidden;
        margin-bottom: 20px;
    }

    /* ================================================================
       TABLE HEADER - SEARCH + SCROLL BUTTONS
       ================================================================ */
    .ss-table-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 18px;
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    }

    .ss-table-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
    }

    .ss-table-title {
        color: white;
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        white-space: nowrap;
    }

    .ss-table-title .count-badge {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 2px 10px;
        border-radius: 16px;
        font-size: 0.65rem;
        font-weight: 700;
        margin-left: 4px;
    }

    /* LIVE SEARCH */
    .ss-search-wrapper {
        position: relative;
        flex: 1;
        max-width: 340px;
        min-width: 200px;
    }

    .ss-search-wrapper .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.7);
        font-size: 0.8rem;
        pointer-events: none;
        z-index: 1;
    }

    .ss-search-wrapper input {
        width: 100%;
        padding: 8px 38px 8px 36px;
        border: 1.5px solid rgba(255,255,255,0.25);
        border-radius: 9px;
        font-size: 0.78rem;
        background: rgba(255,255,255,0.15);
        color: white;
        outline: none;
        transition: all 0.3s ease;
        font-weight: 500;
        height: 36px;
        font-family: inherit;
    }

    .ss-search-wrapper input::placeholder {
        color: rgba(255,255,255,0.6);
        font-size: 0.72rem;
    }

    .ss-search-wrapper input:focus {
        background: rgba(255,255,255,0.28);
        border-color: rgba(255,255,255,0.5);
        box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
    }

    .ss-search-wrapper .clear-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: rgba(255,255,255,0.7);
        cursor: pointer;
        padding: 4px 8px;
        font-size: 0.78rem;
        transition: all 0.2s;
        display: none;
        border-radius: 6px;
    }

    .ss-search-wrapper .clear-btn:hover {
        color: white;
        background: rgba(255,255,255,0.15);
    }

    .ss-search-wrapper .clear-btn.visible {
        display: block;
    }

    /* SCROLL BUTTONS */
    .ss-scroll-btns {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .ss-scroll-btn {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.25);
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .ss-scroll-btn:hover:not(:disabled) {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-2px);
    }

    .ss-scroll-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }

    .ss-scroll-btn i {
        pointer-events: none;
    }

    /* ================================================================
       TABLE SCROLL CONTAINER
       ================================================================ */
    .ss-table-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: #0B5ED7 var(--ss-border-color);
    }

    .ss-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .ss-table-scroll::-webkit-scrollbar-track {
        background: var(--ss-border-color);
        border-radius: 10px;
    }

    .ss-table-scroll::-webkit-scrollbar-thumb {
        background: #0B5ED7;
        border-radius: 10px;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .ss-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        min-width: 1200px;
    }

    .ss-table thead th {
        background: #0B5ED7;
        color: white;
        padding: 11px 12px;
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: 700;
        text-align: left;
        white-space: nowrap;
    }

    .ss-table tbody tr:nth-child(even) {
        background: #F8FAFC;
    }

    [data-theme="dark"] .ss-table tbody tr:nth-child(even) {
        background: #1E293B;
    }

    .ss-table tbody tr:hover {
        background: #DBEAFE;
    }

    [data-theme="dark"] .ss-table tbody tr:hover {
        background: #1E40AF;
    }

    .ss-table td {
        padding: 9px 12px;
        border-bottom: 1px solid var(--ss-border-color);
        color: var(--ss-text-primary);
        vertical-align: middle;
    }

    .ss-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* ================================================================
       SEARCH HIGHLIGHT
       ================================================================ */
    mark.ss-highlight {
        background: #FEF08A;
        color: #78350F;
        padding: 1px 3px;
        border-radius: 3px;
        font-weight: 700;
    }

    [data-theme="dark"] mark.ss-highlight {
        background: #FCD34D;
        color: #422006;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .ss-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .ss-badge-blue-1 { background: #E8F0FE; color: #0B5ED7; }
    .ss-badge-blue-2 { background: #DBEAFE; color: #0A4CA8; }
    .ss-badge-blue-3 { background: #E0F2FE; color: #0B3D8A; }

    [data-theme="dark"] .ss-badge-blue-1 { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .ss-badge-blue-2 { background: #1E40AF; color: #DBEAFE; }
    [data-theme="dark"] .ss-badge-blue-3 { background: #0C2A3A; color: #93C5FD; }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .ss-actions {
        display: flex;
        gap: 4px;
        align-items: center;
        justify-content: center;
        flex-wrap: nowrap;
    }

    .ss-btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 7px;
        font-size: 0.72rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        color: white;
        flex-shrink: 0;
    }

    .ss-btn-action:hover {
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .ss-btn-action.view { background: #0B5ED7; }
    .ss-btn-action.view:hover { background: #0A4CA8; color: white; }

    .ss-btn-action.edit { background: #1A73E8; }
    .ss-btn-action.edit:hover { background: #0B5ED7; color: white; }

    .ss-btn-action.download { background: #0A4CA8; }
    .ss-btn-action.download:hover { background: #0B3D8A; color: white; }

    .ss-btn-action.delete { background: #DC2626; }
    .ss-btn-action.delete:hover { background: #B91C1C; color: white; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .ss-empty {
        text-align: center;
        padding: 50px 20px;
        color: var(--ss-text-secondary);
    }

    .ss-empty i {
        font-size: 2.5rem;
        color: var(--ss-border-color);
        display: block;
        margin-bottom: 10px;
    }

    .ss-empty p {
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .ss-footer {
        padding: 14px 0;
        border-top: 1px solid var(--ss-border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--ss-text-secondary);
    }

    .ss-footer .brand {
        color: var(--ss-primary);
        font-weight: 700;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .ss-stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .ss-page-header { padding: 14px 16px; }
        .ss-page-header .title { font-size: 1rem; }
        .ss-page-header .title i { width: 30px; height: 30px; font-size: 0.85rem; }
        .ss-table-header {
            flex-direction: column;
            align-items: stretch;
        }
        .ss-table-header-left {
            flex-direction: column;
            align-items: stretch;
        }
        .ss-search-wrapper {
            max-width: 100%;
        }
        .ss-scroll-btns {
            justify-content: flex-end;
        }
        .ss-table { font-size: 0.7rem; }
        .ss-table th, .ss-table td { padding: 6px 8px; }
    }

    @media (max-width: 480px) {
        .ss-stats-grid { grid-template-columns: 1fr 1fr; }
        .ss-stat-card { padding: 10px 12px; min-height: 70px; }
        .ss-stat-card .num { font-size: 1.2rem; }
        .ss-stat-card .icon { width: 32px; height: 32px; font-size: 0.8rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="ss-page-header">
        <div>
            <h1 class="title">
                <i class="fas fa-file-medical"></i>
                Sick Sheets Management
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?>
                </span>
            </h1>
            <p class="subtitle">Manage all sick sheets (external & registered)</p>
        </div>
        <a href="add_sick_sheet.php?branch=<?= $selected_branch_id ?>" class="btn-new">
            <i class="fas fa-plus-circle"></i> New Sick Sheet
        </a>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="ss-message <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="ss-stats-grid">
        <div class="ss-stat-card blue-1">
            <div>
                <div class="lbl">Total Sick Sheets</div>
                <div class="num"><?= $total ?></div>
            </div>
            <div class="icon"><i class="fas fa-file-medical"></i></div>
        </div>
        <div class="ss-stat-card blue-2">
            <div>
                <div class="lbl">Registered Patients</div>
                <div class="num"><?= $registered_count ?></div>
            </div>
            <div class="icon"><i class="fas fa-user-check"></i></div>
        </div>
        <div class="ss-stat-card blue-3">
            <div>
                <div class="lbl">External Patients</div>
                <div class="num"><?= $external_count ?></div>
            </div>
            <div class="icon"><i class="fas fa-user-plus"></i></div>
        </div>
        <div class="ss-stat-card blue-4">
            <div>
                <div class="lbl">Total Sick Days</div>
                <div class="num"><?= $total_days ?></div>
            </div>
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="ss-table-card">
        
        <!-- TABLE HEADER: TITLE + SEARCH + SCROLL BUTTONS -->
        <div class="ss-table-header">
            <div class="ss-table-header-left">
                <h3 class="ss-table-title">
                    <i class="fas fa-list"></i>
                    All Sick Sheets
                    <span class="count-badge" id="visibleCount"><?= $total ?></span>
                </h3>
                
                <div class="ss-search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           id="liveSearchInput" 
                           placeholder="Search patient, document #, phone..." 
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    <button type="button" class="clear-btn <?= !empty($search) ? 'visible' : '' ?>" id="clearSearchBtn" title="Clear">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="ss-scroll-btns">
                <button type="button" 
                        class="ss-scroll-btn" 
                        id="headerScrollLeftBtn" 
                        onclick="scrollTable('left')" 
                        title="Scroll Left"
                        disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" 
                        class="ss-scroll-btn" 
                        id="headerScrollRightBtn" 
                        onclick="scrollTable('right')" 
                        title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <!-- TABLE BODY -->
        <?php if ($total > 0): ?>
            <div class="ss-table-scroll" id="tableScrollContainer">
                <table class="ss-table" id="sickSheetsTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Document #</th>
                            <th>Patient</th>
                            <th>Source</th>
                            <th>Diagnosis</th>
                            <th>Sick Days</th>
                            <th>From - To</th>
                            <th>Doctor</th>
                            <th>Branch</th>
                            <th>Date</th>
                            <th style="text-align:center;width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php $i = 1; foreach ($all_sheets as $ss): ?>
                            <tr class="ss-row" 
                                data-search="<?= strtolower(htmlspecialchars(
                                    ($ss['document_number'] ?? '') . ' ' . 
                                    ($ss['full_name'] ?? '') . ' ' . 
                                    ($ss['patient_id'] ?? '') . ' ' . 
                                    ($ss['phone'] ?? '') . ' ' . 
                                    ($ss['diagnosis'] ?? '') . ' ' . 
                                    ($ss['doctor_name'] ?? '') . ' ' . 
                                    ($ss['branch_name'] ?? '')
                                )) ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <span style="font-family:monospace;font-size:0.7rem;font-weight:600;color:#0B5ED7;">
                                        <?= htmlspecialchars($ss['document_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:0.78rem;"><?= htmlspecialchars($ss['full_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.68rem;color:var(--ss-text-secondary);"><?= htmlspecialchars($ss['patient_id'] ?? '') ?></div>
                                    <?php if (!empty($ss['phone'])): ?>
                                        <div style="font-size:0.65rem;color:var(--ss-text-secondary);">📞 <?= htmlspecialchars($ss['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ss['source'] === 'external'): ?>
                                        <span class="ss-badge ss-badge-blue-3"><i class="fas fa-user-plus"></i> External</span>
                                    <?php else: ?>
                                        <span class="ss-badge ss-badge-blue-1"><i class="fas fa-user-check"></i> Registered</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.75rem;font-weight:500;">
                                    <?= htmlspecialchars(substr($ss['diagnosis'] ?? 'N/A', 0, 30)) ?>
                                    <?= strlen($ss['diagnosis'] ?? '') > 30 ? '...' : '' ?>
                                </td>
                                <td>
                                    <span class="ss-badge ss-badge-blue-2">
                                        <i class="fas fa-calendar-day"></i> <?= (int)($ss['sick_days'] ?? 0) ?> days
                                    </span>
                                </td>
                                <td style="font-size:0.72rem;">
                                    <div><?= !empty($ss['sick_from']) ? date('M d, Y', strtotime($ss['sick_from'])) : 'N/A' ?></div>
                                    <div style="font-size:0.62rem;color:var(--ss-text-secondary);">→ <?= !empty($ss['sick_to']) ? date('M d, Y', strtotime($ss['sick_to'])) : 'N/A' ?></div>
                                </td>
                                <td style="font-size:0.75rem;">Dr. <?= htmlspecialchars($ss['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="ss-badge ss-badge-blue-1" style="font-size:0.6rem;">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($ss['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;">
                                    <?= !empty($ss['created_at']) ? date('M d, Y', strtotime($ss['created_at'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <div class="ss-actions">
                                        <a href="view_sick_sheet.php?id=<?= $ss['id'] ?>&type=<?= $ss['source'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="ss-btn-action view" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_sick_sheet.php?id=<?= $ss['id'] ?>&type=<?= $ss['source'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="ss-btn-action edit" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="export_sick_sheet_pdf.php?id=<?= $ss['id'] ?>&type=<?= $ss['source'] ?>&branch=<?= $selected_branch_id ?>" 
                                           target="_blank"
                                           class="ss-btn-action download" 
                                           title="Download PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="?delete=<?= $ss['id'] ?>&type=<?= $ss['source'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="ss-btn-action delete" 
                                           title="Delete"
                                           onclick="return confirm('Delete this sick sheet?\n\nDocument #: <?= htmlspecialchars(addslashes($ss['document_number'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($ss['full_name'] ?? 'N/A')) ?>\n\nThis cannot be undone!');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="noResults" style="display:none;">
                <div class="ss-empty">
                    <i class="fas fa-search-minus"></i>
                    <p>No sick sheets match your search</p>
                </div>
            </div>
        <?php else: ?>
            <div class="ss-empty">
                <i class="fas fa-file-medical"></i>
                <p>No sick sheets found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="ss-footer">
        <p>
            <span class="brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;">|</span>
            Sick Sheets Management
            <span style="margin:0 6px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="margin:0 6px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SCROLL TABLE
    // ================================================================
    function scrollTable(direction) {
        var container = document.getElementById('tableScrollContainer');
        if (!container) return;
        
        var scrollAmount = 400;
        
        if (direction === 'left') {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
        
        setTimeout(updateScrollButtons, 350);
    }

    function updateScrollButtons() {
        var container = document.getElementById('tableScrollContainer');
        var leftBtn = document.getElementById('headerScrollLeftBtn');
        var rightBtn = document.getElementById('headerScrollRightBtn');
        
        if (!container || !leftBtn || !rightBtn) return;
        
        var scrollLeft = container.scrollLeft;
        var scrollWidth = container.scrollWidth;
        var clientWidth = container.clientWidth;
        var maxScroll = scrollWidth - clientWidth;
        
        if (maxScroll <= 5) {
            leftBtn.disabled = true;
            rightBtn.disabled = true;
            return;
        }
        
        leftBtn.disabled = (scrollLeft <= 5);
        rightBtn.disabled = (scrollLeft >= maxScroll - 5);
    }

    (function() {
        var container = document.getElementById('tableScrollContainer');
        if (!container) return;
        
        container.addEventListener('scroll', updateScrollButtons);
        window.addEventListener('resize', function() {
            setTimeout(updateScrollButtons, 100);
        });
        
        setTimeout(updateScrollButtons, 200);
        setTimeout(updateScrollButtons, 600);
        
        // Drag to scroll
        var isDown = false;
        var startX = 0;
        var scrollLeftStart = 0;
        
        container.addEventListener('mousedown', function(e) {
            if (e.target.closest('a, button, input')) return;
            isDown = true;
            container.style.cursor = 'grabbing';
            startX = e.pageX - container.offsetLeft;
            scrollLeftStart = container.scrollLeft;
        });
        
        container.addEventListener('mouseleave', function() {
            isDown = false;
            container.style.cursor = '';
        });
        
        container.addEventListener('mouseup', function() {
            isDown = false;
            container.style.cursor = '';
        });
        
        container.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - container.offsetLeft;
            var walk = (x - startX) * 1.5;
            container.scrollLeft = scrollLeftStart - walk;
        });
    })();

    // ================================================================
    // LIVE SEARCH WITH HIGHLIGHT
    // ================================================================
    (function() {
        var searchInput = document.getElementById('liveSearchInput');
        var clearBtn = document.getElementById('clearSearchBtn');
        var noResults = document.getElementById('noResults');
        var visibleCountEl = document.getElementById('visibleCount');
        
        if (!searchInput) return;
        
        var allRows = document.querySelectorAll('.ss-row');
        var totalRows = allRows.length;
        
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function highlightText(text, query) {
            if (!query || !text) return escapeHtml(text);
            var escaped = escapeHtml(text);
            var regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            return escaped.replace(regex, '<mark class="ss-highlight">$1</mark>');
        }
        
        function filterTable() {
            var query = searchInput.value.toLowerCase().trim();
            var visibleCount = 0;
            
            allRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                
                if (query === '' || searchData.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (visibleCountEl) {
                visibleCountEl.textContent = query === '' ? totalRows : visibleCount;
            }
            
            if (clearBtn) {
                if (query.length > 0) {
                    clearBtn.classList.add('visible');
                } else {
                    clearBtn.classList.remove('visible');
                }
            }
            
            if (noResults) {
                noResults.style.display = (visibleCount === 0 && query !== '' && totalRows > 0) ? '' : 'none';
            }
        }
        
        searchInput.addEventListener('input', filterTable);
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterTable();
                this.blur();
            }
        });
        
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterTable();
                searchInput.focus();
            });
        }
    })();

    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c📄 Braick - Sick Sheets Management', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:12px;color:#34D399;');
    console.log('%c✅ Scroll buttons < > in table header', 'font-size:12px;color:#34D399;');
    console.log('%c✅ Live search with highlight', 'font-size:12px;color:#34D399;');
    console.log('%c✅ Download PDF opens in new page', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>