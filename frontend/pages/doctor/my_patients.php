<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR - MY PATIENTS LIST
// SHOWS PENDING PATIENTS + ALL PATIENTS
// WITH SEARCH AND AUTO-UPDATE STATS
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
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all'; // pending, all

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCTOR DATA
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($doctor_data) {
        $doctor_name = $doctor_data['full_name'];
        $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
        $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    }
} catch (Exception $e) {
    error_log("Doctor data error: " . $e->getMessage());
}

// ================================================================
// GET PENDING PATIENTS (QUEUE)
// ================================================================
try {
    $query = "
        SELECT v.*, p.full_name as patient_name, p.patient_id as patient_number, 
               p.phone, p.gender, p.date_of_birth,
               TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_time
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE v.doctor_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor')
    ";
    $params = [$doctor_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY v.created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $pending_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $pending_patients = [];
    error_log("Pending patients error: " . $e->getMessage());
}

// ================================================================
// GET ALL PATIENTS (HISTORY)
// ================================================================
try {
    $query = "
        SELECT DISTINCT p.*, 
               COUNT(v.id) as total_visits,
               MAX(v.created_at) as last_visit,
               (SELECT status FROM visits WHERE patient_id = p.id AND doctor_id = ? 
                ORDER BY created_at DESC LIMIT 1) as last_status
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
    ";
    $params = [$doctor_id, $doctor_id];
    
    if ($status_filter !== 'all') {
        $query .= " AND (SELECT status FROM visits WHERE patient_id = p.id AND doctor_id = ? 
                          ORDER BY created_at DESC LIMIT 1) = ?";
        $params[] = $doctor_id;
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " GROUP BY p.id ORDER BY last_visit DESC LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $all_patients = [];
    error_log("All patients error: " . $e->getMessage());
}

// ================================================================
// GET STATS COUNTS
// ================================================================
try {
    // Total patients
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Pending patients count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor')");
    $stmt->execute([$doctor_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Completed patients count
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $total_patients = 0;
    $pending_count = 0;
    $completed_count = 0;
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-users"></i> My Patients
                <span class="page-badge"><?= $total_patients ?> total</span>
            </h1>
            <p class="page-subtitle">
                Manage all patients assigned to you
                <span class="separator">|</span>
                <span class="status-badge status-pending">
                    <i class="fas fa-clock"></i> <?= $pending_count ?> Pending
                </span>
                <span class="status-badge status-completed">
                    <i class="fas fa-check-circle"></i> <?= $completed_count ?> Completed
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="appointments.php" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Appointments
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Patients</span>
                        <span class="stat-card-number"><?= $total_patients ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-orange">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Pending</span>
                        <span class="stat-card-number"><?= $pending_count ?></span>
                        <span class="stat-card-trend">Awaiting consultation</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Completed</span>
                        <span class="stat-card-number"><?= $completed_count ?></span>
                        <span class="stat-card-trend">Successfully treated</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-search"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Search</span>
                        <span class="stat-card-number" style="font-size:1rem;">
                            <input type="text" id="searchInput" class="stat-search" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER TABS -->
    <!-- ================================================================ -->
    <div class="filter-tabs">
        <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending Patients
            <span class="badge"><?= $pending_count ?></span>
        </a>
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' || $filter === '' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All Patients
            <span class="badge"><?= $total_patients ?></span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- PENDING PATIENTS SECTION -->
    <!-- ================================================================ -->
    <?php if ($filter === 'pending' || $filter === ''): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-user-clock title-orange"></i> Pending Patients
                <span class="section-badge"><?= count($pending_patients) ?> waiting</span>
            </h2>
            <span class="section-subtitle">Patients waiting for your consultation</span>
        </div>

        <?php if (count($pending_patients) > 0): ?>
            <div class="patient-grid">
                <?php foreach ($pending_patients as $index => $patient): ?>
                    <div class="patient-card pending-card <?= $index === 0 ? 'first' : '' ?>">
                        <div class="patient-card-header">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div class="patient-card-info">
                                <h3 class="patient-name"><?= htmlspecialchars($patient['patient_name']) ?></h3>
                                <p class="patient-id"><?= htmlspecialchars($patient['patient_number'] ?? 'N/A') ?></p>
                            </div>
                            <?php if ($index === 0): ?>
                                <span class="next-badge">⬅️ Next</span>
                            <?php endif; ?>
                        </div>
                        <div class="patient-card-body">
                            <div class="patient-details">
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                                <span><i class="fas fa-clock"></i> Waiting: <strong><?= ($patient['waiting_time'] ?? 0) > 0 ? $patient['waiting_time'] . ' min' : 'Just now' ?></strong></span>
                                <span><i class="fas fa-calendar-alt"></i> Since: <?= isset($patient['created_at']) ? date('h:i A', strtotime($patient['created_at'])) : 'N/A' ?></span>
                            </div>
                            <div class="patient-status">
                                <span class="status-badge status-<?= $patient['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $patient['status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="patient-card-actions">
                            <a href="consultation.php?visit_id=<?= $patient['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-stethoscope"></i> Consult Now
                            </a>
                            <a href="view_patient.php?id=<?= $patient['patient_id'] ?>" class="btn btn-outline">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state large">
                <i class="fas fa-check-circle text-green-500"></i>
                <h3>All Clear!</h3>
                <p>No patients waiting for consultation.</p>
                <p class="text-sm text-gray-400">Take a break or review completed cases</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ALL PATIENTS SECTION -->
    <!-- ================================================================ -->
    <?php if ($filter === 'all' || $filter === ''): ?>
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-users title-blue"></i> All Patients
                <span class="section-badge"><?= count($all_patients) ?> patients</span>
            </h2>
            <div class="section-actions">
                <select id="statusFilter" class="form-control-sm" onchange="window.location.href='my_patients.php?status='+this.value+'&search=<?= urlencode($search) ?>'">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="with_doctor" <?= $status_filter === 'with_doctor' ? 'selected' : '' ?>>With Doctor</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <?php if (count($all_patients) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Patient ID</th>
                            <th>Phone</th>
                            <th>Visits</th>
                            <th>Last Visit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_patients as $patient): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="patient-cell">
                                        <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                                            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($patient['full_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="patient-id-text"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td><span class="visit-count"><?= $patient['total_visits'] ?? 0 ?></span></td>
                                <td class="text-sm"><?= isset($patient['last_visit']) ? date('M d, Y', strtotime($patient['last_visit'])) : 'N/A' ?></td>
                                <td>
                                    <span class="status-badge status-<?= $patient['last_status'] ?? 'pending' ?>">
                                        <?= ucfirst($patient['last_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="consultation.php?patient_id=<?= $patient['id'] ?>" class="btn btn-primary btn-sm" title="Consult">
                                            <i class="fas fa-stethoscope"></i>
                                        </a>
                                        <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm" title="View Profile">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <a href="appointment.php?patient_id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm" title="New Appointment">
                                            <i class="fas fa-calendar-plus"></i>
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
                <i class="fas fa-users"></i>
                <h3>No Patients Found</h3>
                <p><?= !empty($search) ? 'No patients matching "' . htmlspecialchars($search) . '"' : 'You haven\'t treated any patients yet' ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            My Patients
            <span class="separator">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
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
    /* ================================================================
       MY PATIENTS STYLES
       ================================================================ */
    
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
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
    
    .page-badge {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 14px;
        border-radius: 20px;
    }
    
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .separator { color: var(--border-color); margin: 0 4px; }
    
    .status-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 14px;
        border-radius: 20px;
    }
    
    .status-pending { background: #FEF3C7; color: #D97706; }
    .status-assigned { background: #E8F0FE; color: #0B5ED7; }
    .status-with_doctor { background: #E8F0FE; color: #0B5ED7; }
    .status-completed { background: #D1FAE5; color: #059669; }
    .status-cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-with_doctor { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-cancelled { background: #3A1A1A; color: #F87171; }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    /* ================================================================
       STATS GRID
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .stat-card-inner {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card-blue .stat-card-icon { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .stat-card-orange .stat-card-icon { background: linear-gradient(135deg, #D97706, #F59E0B); }
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #34D399); }
    .stat-card-purple .stat-card-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
    
    .stat-card-info {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    
    .stat-card-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .stat-card-trend {
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    
    .stat-search {
        width: 100%;
        border: none;
        background: transparent;
        padding: 4px 0;
        font-size: 0.9rem;
        color: var(--text-primary);
        outline: none;
    }
    
    .stat-search::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    /* ================================================================
       FILTER TABS
       ================================================================ */
    .filter-tabs {
        display: flex;
        gap: 4px;
        background: var(--bg-card);
        border-radius: 12px;
        padding: 4px;
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
    }
    
    .filter-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 20px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .filter-tab:hover {
        background: var(--bg-body);
        color: var(--text-primary);
    }
    
    .filter-tab.active {
        background: var(--primary);
        color: white;
    }
    
    .filter-tab .badge {
        font-size: 0.65rem;
        font-weight: 600;
        background: rgba(255,255,255,0.2);
        padding: 1px 10px;
        border-radius: 20px;
    }
    
    .filter-tab.active .badge {
        background: rgba(255,255,255,0.25);
    }
    
    /* ================================================================
       SECTION
       ================================================================ */
    .section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .title-blue { color: var(--primary); }
    .title-orange { color: #D97706; }
    
    .section-badge {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--bg-body);
        color: var(--text-secondary);
        padding: 2px 14px;
        border-radius: 20px;
    }
    
    .section-subtitle {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .section-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .form-control-sm {
        padding: 4px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    
    .form-control-sm:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    /* ================================================================
       PATIENT GRID (PENDING)
       ================================================================ */
    .patient-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 16px;
    }
    
    .patient-card {
        background: var(--bg-body);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .patient-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }
    
    .patient-card.first {
        border-color: #0B5ED7;
        background: var(--primary-bg);
    }
    
    [data-theme="dark"] .patient-card.first {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }
    
    .patient-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 12px;
    }
    
    .patient-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .patient-card-info {
        flex: 1;
    }
    
    .patient-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .patient-id {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    .next-badge {
        font-size: 0.7rem;
        font-weight: 600;
        color: #0B5ED7;
        background: rgba(11, 94, 215, 0.1);
        padding: 2px 12px;
        border-radius: 20px;
        animation: pulse-badge 2s infinite;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .patient-card-body {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .patient-details {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .patient-details i {
        width: 16px;
        color: var(--text-secondary);
    }
    
    .patient-details strong {
        color: var(--text-primary);
    }
    
    .patient-status {
        flex-shrink: 0;
    }
    
    .patient-card-actions {
        display: flex;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }
    
    .patient-card-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
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
    }
    
    .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
    
    .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: center;
    }
    
    .action-buttons .btn {
        padding: 4px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }
    
    /* ================================================================
       TABLE
       ================================================================ */
    .table-container {
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
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table thead th:last-child { border-radius: 0 8px 0 0; }
    
    .data-table tbody tr {
        transition: all 0.2s ease;
    }
    
    .data-table tbody tr:hover {
        background: var(--bg-body);
    }
    
    .data-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .patient-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .patient-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .patient-id-text {
        font-family: monospace;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .visit-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: var(--primary);
        background: var(--primary-bg);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        min-width: 32px;
    }
    
    .text-sm { font-size: 0.8rem; }
    .text-gray-400 { color: var(--text-secondary); }
    .text-green-500 { color: #059669; }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .empty-state h3 {
        font-size: 1.2rem;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    
    .empty-state p {
        margin: 2px 0;
        font-size: 0.9rem;
    }
    
    .empty-state.large { padding: 60px 20px; }
    .empty-state.large i { font-size: 4rem; }
    
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
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    /* ================================================================
       TOAST
       ================================================================ */
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
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 1024px) {
        .main-content { padding: 20px; }
        .patient-grid { grid-template-columns: 1fr 1fr; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 16px; margin-left: 0; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .patient-grid { grid-template-columns: 1fr; }
        .page-title { font-size: 1.2rem; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .filter-tabs { flex-direction: column; }
        .filter-tab { justify-content: center; }
        .section-header { flex-direction: column; align-items: flex-start; }
        .section-actions { width: 100%; }
        .section-actions select { width: 100%; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 6px 8px; }
        .action-buttons { flex-wrap: wrap; }
        .patient-card-actions { flex-direction: column; }
        .patient-card-actions .btn { width: 100%; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .patient-details { flex-direction: column; gap: 4px; }
        .patient-card-body { flex-direction: column; align-items: flex-start; }
    }
    
    /* Dark theme overrides */
    [data-theme="dark"] .patient-card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .patient-card:hover { border-color: var(--primary); }
    [data-theme="dark"] .data-table tbody tr:hover { background: #0F172A; }
    [data-theme="dark"] .filter-tabs { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .filter-tab:hover { background: #0F172A; }
    [data-theme="dark"] .section { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .stat-card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .stat-card:hover { border-color: var(--primary); }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SEARCH WITH ENTER KEY
    // ================================================================
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                var query = this.value.trim();
                var currentFilter = '<?= $filter ?>';
                var currentStatus = '<?= $status_filter ?>';
                if (query.length > 0) {
                    window.location.href = 'my_patients.php?search=' + encodeURIComponent(query) + '&filter=' + currentFilter + '&status=' + currentStatus;
                } else {
                    window.location.href = 'my_patients.php?filter=' + currentFilter + '&status=' + currentStatus;
                }
            }
        });
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

    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    console.log('%c👨‍⚕️ My Patients - Doctor Panel', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Patients: <?= $total_patients ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= $pending_count ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Completed: <?= $completed_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c💡 Type in search box and press Enter to filter', 'font-size:12px; color:#64748B;');
</script>

<!-- ================================================================ -->
<!-- DOCTOR STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/doctor_global_stats.js"></script>

</body>
</html>