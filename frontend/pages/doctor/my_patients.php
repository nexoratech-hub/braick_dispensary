<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR - MY PATIENTS (REDUCED COLUMNS, CLEAN DESIGN)
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
// FUNCTIONS
// ================================================================
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

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET SEARCH PARAMETER
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// TABLE 1: PENDING PATIENTS - ORDERED BY WAITING TIME (OLDEST FIRST)
// ================================================================
$sql_pending = "
    SELECT DISTINCT 
        p.id,
        p.full_name,
        p.patient_id,
        p.phone,
        p.gender,
        v.id as visit_id,
        v.status as visit_status,
        v.created_at as visit_date,
        TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_time,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as total_visits
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ? 
    AND v.status IN ('pending', 'assigned', 'with_doctor')
";

$params_pending = [$doctor_id, $doctor_id];

if (!empty($search)) {
    $sql_pending .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $params_pending[] = "%$search%";
    $params_pending[] = "%$search%";
    $params_pending[] = "%$search%";
}

$sql_pending .= " ORDER BY v.created_at ASC"; // Oldest first

$stmt = $db->prepare($sql_pending);
$stmt->execute($params_pending);
$pending_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// TABLE 2: ALL PATIENTS - ORDERED BY LAST VISIT (RECENT FIRST)
// ================================================================
$sql_all = "
    SELECT DISTINCT 
        p.id,
        p.full_name,
        p.patient_id,
        p.phone,
        p.gender,
        p.created_at as registered_date,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as total_visits,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ? AND status = 'completed') as completed_visits,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor')) as pending_visits,
        (SELECT MAX(created_at) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as last_visit_date
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
";

$params_all = [$doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id];

if (!empty($search)) {
    $sql_all .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $params_all[] = "%$search%";
    $params_all[] = "%$search%";
    $params_all[] = "%$search%";
}

$sql_all .= " ORDER BY last_visit_date DESC"; // Most recent first

$stmt = $db->prepare($sql_all);
$stmt->execute($params_all);
$all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_pending = count($pending_patients);
$total_patients = count($all_patients);

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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-injured mr-2" style="color: #0B5ED7;"></i> My Patients
            </h1>
            <p class="page-subtitle">
                View all patients assigned to you
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-users mr-1"></i> <?= $total_patients ?> total
                </span>
                <?php if ($total_pending > 0): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> <?= $total_pending ?> pending
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <span class="text-sm text-gray-500 flex items-center">
                <i class="fas fa-user-md mr-1"></i> <?= htmlspecialchars($doctor_name) ?>
            </span>
            <a href="consultation.php" class="btn btn-consult btn-sm">
                <i class="fas fa-stethoscope"></i> Consult
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SEARCH -->
    <!-- ================================================================ -->
    <div class="search-card mb-5">
        <form method="GET" class="search-form">
            <div class="search-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search): ?>
                    <a href="my_patients.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE 1: PENDING PATIENTS -->
    <!-- ================================================================ -->
    <div class="table-card mb-5">
        <div class="table-header pending-header">
            <div class="table-title">
                <span class="table-dot pending-dot"></span>
                Pending Patients
                <span class="table-count"><?= $total_pending ?></span>
                <span class="table-sub">Waiting for consultation</span>
            </div>
        </div>

        <?php if ($total_pending > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0; width:50px;">#</th>
                            <th>Patient</th>
                            <th style="width:130px;">Patient ID</th>
                            <th style="width:130px;">Phone</th>
                            <th style="width:100px;">Waiting</th>
                            <th style="width:130px;">Status</th>
                            <th style="width:100px;">Visits</th>
                            <th style="border-radius: 0 8px 0 0; width:120px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_patients as $index => $patient): 
                            $waiting = $patient['waiting_time'] ?? 0;
                            $waiting_class = $waiting > 30 ? 'waiting-long' : '';
                        ?>
                            <tr class="pending-row">
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-sm" style="background: <?= getUserColor($patient['full_name']) ?>;">
                                            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($patient['full_name']) ?></div>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="waiting-time <?= $waiting_class ?>">
                                        <?= $waiting > 0 ? $waiting . ' min' : 'Just now' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $patient['visit_status'] ?? 'pending' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $patient['visit_status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td><span class="badge badge-info"><?= $patient['total_visits'] ?? 0 ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="consultation.php?visit_id=<?= $patient['visit_id'] ?>" class="btn btn-consult btn-sm" title="Consult">
                                            <i class="fas fa-stethoscope"></i>
                                        </a>
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>" class="btn btn-view btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: #059669;"></i>
                <h4>No Pending Patients</h4>
                <p>All patients have been attended to. Great job! 👏</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE 2: ALL PATIENTS -->
    <!-- ================================================================ -->
    <div class="table-card">
        <div class="table-header all-header">
            <div class="table-title">
                <span class="table-dot all-dot"></span>
                All Patients
                <span class="table-count all-count"><?= $total_patients ?></span>
                <span class="table-sub">Complete patient list</span>
            </div>
        </div>

        <?php if ($total_patients > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0; width:50px;">#</th>
                            <th>Patient</th>
                            <th style="width:130px;">Patient ID</th>
                            <th style="width:130px;">Phone</th>
                            <th style="width:100px;">Visits</th>
                            <th style="width:140px;">Last Visit</th>
                            <th style="border-radius: 0 8px 0 0; width:120px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_patients as $index => $patient): ?>
                            <tr class="all-row">
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-sm" style="background: <?= getUserColor($patient['full_name']) ?>;">
                                            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($patient['full_name']) ?></div>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-primary"><?= $patient['total_visits'] ?? 0 ?></span>
                                </td>
                                <td class="text-sm"><?= isset($patient['last_visit_date']) ? time_ago($patient['last_visit_date']) : 'N/A' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>" class="btn btn-view btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="prescribe.php?patient_id=<?= $patient['id'] ?>" class="btn btn-green btn-sm" title="Prescribe">
                                            <i class="fas fa-prescription"></i>
                                        </a>
                                        <a href="view_prescriptions.php?patient_id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm" title="Prescriptions">
                                            <i class="fas fa-file-prescription"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-injured"></i>
                <h4>No Patients Yet</h4>
                <p>Patients will appear here once assigned by reception</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Patients
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
       MY PATIENTS - CLEAN TABLE DESIGN
       ================================================================ */

    /* ===== SEARCH CARD ===== */
    .search-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .search-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    .search-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .search-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        width: 100%;
    }
    .search-wrapper {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 180px;
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        transition: all 0.3s;
        padding: 0 12px;
    }
    .search-wrapper:focus-within {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    .search-wrapper .fa-search {
        color: var(--text-secondary);
        font-size: 0.85rem;
        opacity: 0.5;
    }
    .search-input {
        border: none;
        background: transparent;
        padding: 10px 12px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    .search-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    [data-theme="dark"] .search-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .search-wrapper {
        background: #0F172A;
        border-color: #334155;
    }

    /* ===== TABLE CARD ===== */
    .table-card {
        background: var(--bg-card);
        border-radius: 14px;
        border: 2px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .table-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    [data-theme="dark"] .table-card {
        background: #1E293B;
        border-color: #334155;
    }

    /* ===== TABLE HEADER ===== */
    .table-header {
        padding: 12px 18px;
        border-bottom: 2px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .table-header.pending-header {
        background: #FFFBEB;
        border-bottom-color: #D97706;
    }
    .table-header.all-header {
        background: #F0FDF4;
        border-bottom-color: #059669;
    }
    [data-theme="dark"] .table-header.pending-header {
        background: #1E293B;
        border-bottom-color: #D97706;
    }
    [data-theme="dark"] .table-header.all-header {
        background: #1E293B;
        border-bottom-color: #059669;
    }

    .table-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .table-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .table-dot.pending-dot { background: #D97706; }
    .table-dot.all-dot { background: #059669; }
    .table-count {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 20px;
        background: #FEF3C7;
        color: #D97706;
    }
    .table-count.all-count {
        background: #D1FAE5;
        color: #059669;
    }
    .table-sub {
        font-size: 0.65rem;
        font-weight: 400;
        color: var(--text-secondary);
        opacity: 0.6;
    }

    /* ===== TABLE WRAP ===== */
    .table-wrap {
        overflow-x: auto;
        padding: 0;
    }

    /* ===== DATA TABLE ===== */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        min-width: 650px;
    }
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .data-table tbody tr {
        transition: background 0.2s ease;
    }
    .data-table tbody tr:nth-child(even) {
        background: #F8FAFC;
    }
    .data-table tbody tr:nth-child(odd) {
        background: #fff;
    }
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #0F172A;
    }
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }

    /* ===== ROW STYLES ===== */
    .pending-row {
        border-left: 3px solid #D97706;
    }
    .pending-row td:first-child {
        padding-left: 12px;
    }
    .all-row {
        border-left: 3px solid #059669;
    }
    .all-row td:first-child {
        padding-left: 12px;
    }

    /* ===== AVATAR ===== */
    .avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }

    /* ===== BADGES ===== */
    .badge {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #fff;
        border: none;
    }
    .badge-success { background: #059669; }
    .badge-warning { background: #D97706; }
    .badge-info { background: #0B5ED7; }
    .badge-primary { background: #0B5ED7; }

    /* ===== STATUS BADGE ===== */
    .status-badge {
        font-size: 0.55rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.assigned { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.with_doctor { background: #FEF3C7; color: #D97706; }
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.with_doctor { background: #3D2E0A; color: #FBBF24; }

    /* ===== WAITING TIME ===== */
    .waiting-time {
        font-weight: 500;
        color: var(--text-secondary);
    }
    .waiting-long {
        color: #EF4444;
        font-weight: 600;
        animation: pulse-waiting 2s infinite;
    }
    @keyframes pulse-waiting {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* ===== BUTTONS ===== */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.65rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-blue {
        background: #0B5ED7;
        color: #fff;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    .btn-consult {
        background: #7C3AED;
        color: #fff;
    }
    .btn-consult:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    .btn-green {
        background: #059669;
        color: #fff;
    }
    .btn-green:hover {
        background: #047857;
        transform: scale(1.05);
    }
    .btn-view {
        background: #0B5ED7;
        color: #fff;
    }
    .btn-view:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: scale(1.05);
    }
    .btn-sm {
        padding: 3px 8px;
        font-size: 0.6rem;
        border-radius: 4px;
    }

    .action-buttons {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
        justify-content: center;
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    .empty-state h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .empty-state p {
        font-size: 0.8rem;
    }

    /* ===== UTILITIES ===== */
    .font-medium { font-weight: 500; }
    .font-mono { font-family: monospace; }
    .text-xs { font-size: 0.7rem; }
    .text-sm { font-size: 0.8rem; }
    .text-muted { color: var(--text-secondary); }
    .mb-5 { margin-bottom: 1.25rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }

    /* ===== PAGE HEADER ===== */
    .page-header {
        border-bottom: 3px solid #0B5ED7;
        padding-bottom: 12px;
    }
    .page-header .page-title {
        color: #0B3D8A;
        font-size: 1.5rem;
        font-weight: 700;
    }
    [data-theme="dark"] .page-header .page-title {
        color: #6EA8FE;
    }
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    .branch-tag {
        background: #059669;
        color: #fff;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* ===== FOOTER ===== */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
        .data-table {
            font-size: 0.75rem;
            min-width: 600px;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
    }
    @media (max-width: 768px) {
        .search-group {
            flex-direction: column;
            align-items: stretch;
        }
        .search-wrapper {
            min-width: 100%;
        }
        .search-form .btn {
            width: 100%;
            justify-content: center;
        }
        .table-title {
            font-size: 0.8rem;
        }
        .table-sub {
            display: none;
        }
        .data-table {
            font-size: 0.7rem;
            min-width: 500px;
        }
        .data-table th,
        .data-table td {
            padding: 4px 8px;
        }
        .action-buttons {
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            flex: 1;
            justify-content: center;
            min-width: 28px;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.5rem;
        }
        .avatar-sm {
            width: 24px;
            height: 24px;
            font-size: 0.6rem;
        }
        .page-header .page-title {
            font-size: 1.1rem;
        }
        .table-header {
            padding: 8px 12px;
        }
        .table-count {
            font-size: 0.55rem;
            padding: 1px 8px;
        }
    }
    @media (max-width: 480px) {
        .data-table {
            font-size: 0.6rem;
            min-width: 420px;
        }
        .data-table th,
        .data-table td {
            padding: 3px 6px;
        }
        .action-buttons {
            flex-direction: column;
            gap: 2px;
        }
        .action-buttons .btn {
            width: 100%;
        }
        .table-title {
            font-size: 0.7rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // TOAST
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

    console.log('%c👨‍⚕️ My Patients - 2 Tables', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Pending: <?= $total_pending ?> | Total: <?= $total_patients ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ Pending ordered by waiting time (oldest first)', 'font-size:13px; color:#D97706;');
    console.log('%c📋 All patients ordered by last visit (most recent first)', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>