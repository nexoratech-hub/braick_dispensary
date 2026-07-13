<?php
// ================================================================
// FILE: frontend/pages/reception/view_doctor.php
// RECEPTION - VIEW DOCTOR DETAILS (BRANCH FILTERED)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

if ($doctor_id <= 0) {
    header('Location: online_doctors.php');
    exit;
}

try {
    $db = getDB();
    
    // ================================================================
    // GET DOCTOR DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.id = ? AND u.role = 'doctor' AND u.branch_id = ?
    ");
    $stmt->execute([$doctor_id, $user_branch_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        header('Location: online_doctors.php');
        exit;
    }
    
    // ================================================================
    // GET DOCTOR STATISTICS
    // ================================================================
    
    // 1. Total Patients (distinct)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 2. Today's Visits
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$doctor_id, $today]);
    $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 3. Pending Visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned')");
    $stmt->execute([$doctor_id]);
    $pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 4. Completed Visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 5. Total Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 6. Today's Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ?");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 7. Total Prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 8. Today's Appointments List
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ?
        ORDER BY a.appointment_date
        LIMIT 10
    ");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 9. Recent Patients
    $stmt = $db->prepare("
        SELECT DISTINCT p.*, v.created_at as last_visit 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $doctor = null;
    $total_patients = 0;
    $today_visits = 0;
    $pending_visits = 0;
    $completed_visits = 0;
    $total_appointments = 0;
    $today_appointments = 0;
    $total_prescriptions = 0;
    $today_appointments_list = [];
    $recent_patients = [];
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    /* ================================================================
       VIEW DOCTOR STYLES
       ================================================================ */
    .doctor-profile-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
        transition: all 0.3s ease;
    }
    .doctor-profile-header:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    .doctor-avatar-large {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    }
    .doctor-name-large {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    .doctor-specialty-badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 14px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
    }
    [data-theme="dark"] .doctor-specialty-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    .doctor-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 8px;
        margin-top: 8px;
    }
    .doctor-meta-grid .meta-item {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--bg-body);
        padding: 4px 12px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    .doctor-meta-grid .meta-item i {
        color: var(--primary);
        font-size: 0.8rem;
    }
    
    .stats-grid-doctor {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 16px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .stat-box .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-box .stat-number.green { color: #059669; }
    .stat-box .stat-number.orange { color: #D97706; }
    .stat-box .stat-number.purple { color: #7C3AED; }
    .stat-box .stat-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .appointment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .appointment-item:hover {
        background: var(--bg-body);
    }
    .appointment-item:last-child {
        border-bottom: none;
    }
    .appointment-time {
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-primary);
        min-width: 60px;
    }
    .appointment-patient .name {
        font-weight: 500;
        font-size: 0.8rem;
        color: var(--text-primary);
    }
    .appointment-patient .id {
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    .appointment-status {
        font-size: 0.55rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
    }
    .appointment-status.scheduled { background: #E8F0FE; color: #0B5ED7; }
    .appointment-status.confirmed { background: #D1FAE5; color: #059669; }
    .appointment-status.completed { background: #D1FAE5; color: #059669; }
    .appointment-status.cancelled { background: #FEE2E2; color: #DC2626; }
    .appointment-status.pending { background: #FEF3C7; color: #D97706; }
    
    [data-theme="dark"] .appointment-status.scheduled { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .appointment-status.confirmed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .appointment-status.pending { background: #3D2E0A; color: #FBBF24; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
    }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .role-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
    }
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s;
    }
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .card-title .title-blue { color: #0B5ED7; }
    .card-title .title-green { color: #059669; }
    
    .scroll-container {
        max-height: 200px;
        overflow-y: auto;
    }
    .scroll-container::-webkit-scrollbar {
        width: 4px;
    }
    .scroll-container::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    .scroll-container::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    
    .patient-avatar-sm {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.6rem;
        flex-shrink: 0;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md mr-2" style="color: var(--primary);"></i> Doctor Details
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View doctor information and statistics
                <?php if ($doctor): ?>
                    <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                        <i class="fas fa-hashtag mr-1"></i> ID: <?= $doctor['id'] ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="online_doctors.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($doctor): ?>
    
    <!-- ================================================================ -->
    <!-- DOCTOR PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="doctor-profile-header mb-5">
        <div class="doctor-avatar-large" style="background: <?= getUserColor($doctor['full_name']) ?>;">
            <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="doctor-name-large"><?= htmlspecialchars($doctor['full_name']) ?></span>
                <span class="doctor-specialty-badge">
                    <i class="fas fa-stethoscope mr-1"></i>
                    <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                </span>
                <?php if ($doctor['is_online'] ?? 0): ?>
                    <span class="status-badge" style="background:#D1FAE5;color:#059669;font-size:0.65rem;padding:2px 12px;border-radius:20px;">
                        <span class="online-dot" style="display:inline-block;width:6px;height:6px;background:#059669;border-radius:50%;animation:pulse-dot 1.5s infinite;"></span>
                        Online
                    </span>
                <?php else: ?>
                    <span class="status-badge" style="background:#F1F5F9;color:#94A3B8;font-size:0.65rem;padding:2px 12px;border-radius:20px;">
                        <span class="offline-dot" style="display:inline-block;width:6px;height:6px;background:#94A3B8;border-radius:50%;"></span>
                        Offline
                    </span>
                <?php endif; ?>
            </div>
            <div class="doctor-meta-grid">
                <span class="meta-item">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name'] ?? 'Not Assigned') ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-calendar-alt"></i> Joined: <?= isset($doctor['created_at']) ? date('M d, Y', strtotime($doctor['created_at'])) : 'N/A' ?>
                </span>
            </div>
        </div>
        <div>
            <a href="assign_doctor.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-green">
                <i class="fas fa-user-md"></i> Assign Patient
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid-doctor">
        <div class="stat-box">
            <p class="stat-number"><?= number_format($total_patients) ?></p>
            <p class="stat-label">Total Patients</p>
        </div>
        <div class="stat-box">
            <p class="stat-number green"><?= number_format($today_visits) ?></p>
            <p class="stat-label">Today's Visits</p>
        </div>
        <div class="stat-box">
            <p class="stat-number orange"><?= number_format($pending_visits) ?></p>
            <p class="stat-label">Pending Visits</p>
        </div>
        <div class="stat-box">
            <p class="stat-number green"><?= number_format($completed_visits) ?></p>
            <p class="stat-label">Completed Visits</p>
        </div>
        <div class="stat-box">
            <p class="stat-number purple"><?= number_format($total_appointments) ?></p>
            <p class="stat-label">Total Appointments</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= number_format($today_appointments) ?></p>
            <p class="stat-label">Today's Appointments</p>
        </div>
        <div class="stat-box">
            <p class="stat-number" style="color: #7C3AED;"><?= number_format($total_prescriptions) ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        <div class="stat-box">
            <p class="stat-number" style="color: #D97706;"><?= number_format($pending_visits + $today_visits) ?></p>
            <p class="stat-label">Total Workload</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TODAY'S APPOINTMENTS & RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Today's Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue mr-2"></i> Today's Appointments
                    <span class="text-sm font-normal text-gray-400">(<?= count($today_appointments_list) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container">
                <?php if (count($today_appointments_list) > 0): ?>
                    <?php foreach ($today_appointments_list as $appt): ?>
                        <div class="appointment-item">
                            <span class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                            <div class="appointment-patient flex-1 ml-3">
                                <span class="name"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                <span class="id"><?= htmlspecialchars($appt['patient_id']) ?></span>
                            </div>
                            <span class="appointment-status <?= $appt['status'] ?>">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-calendar-check text-xl block mb-2"></i>
                        <p class="text-sm">No appointments today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Patients -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-green mr-2"></i> Recent Patients
                    <span class="text-sm font-normal text-gray-400">(<?= count($recent_patients) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                            <div class="flex items-center gap-3">
                                <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                                    <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-medium text-sm text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400"><?= isset($patient['last_visit']) ? time_ago($patient['last_visit']) : 'N/A' ?></p>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="text-primary text-xs hover:underline">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-users text-xl block mb-2"></i>
                        <p class="text-sm">No patients yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-user-md text-4xl block mb-3"></i>
            <p class="text-lg">Doctor not found</p>
            <a href="online_doctors.php" class="text-primary hover:underline">Back to doctors list</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Doctor
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

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
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // TIME AGO
    // ================================================================
    function time_ago(timestamp) {
        var now = new Date();
        var past = new Date(timestamp);
        var diff = Math.floor((now - past) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return past.toLocaleDateString();
    }

    // ================================================================
    // GET USER COLOR
    // ================================================================
    function getUserColor(name) {
        var colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
        var index = 0;
        for (var i = 0; i < name.length; i++) {
            index = (index + name.charCodeAt(i)) % colors.length;
        }
        return colors[index];
    }

    console.log('%c👨‍⚕️ Braick - View Doctor', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Doctor: <?= htmlspecialchars($doctor['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Today Appointments: <?= number_format($today_appointments) ?>', 'font-size:13px; color:#64748B;');
</script>

<?php
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}
?>

</body>
</html>