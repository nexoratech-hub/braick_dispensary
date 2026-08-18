<?php
// ================================================================
// FILE: frontend/pages/doctor/dashboard.php
// DOCTOR DASHBOARD - FULL VERSION WITH AUTO-UPDATE
// 8 CLICKABLE CARDS - SMART AUTO-UPDATE (5 SECONDS)
// FIXED: Using visit_date instead of created_at
// FIXED: Data persists after auto-update
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// TIME AGO FUNCTION - PHP VERSION
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'Just now';
    try {
        $time = strtotime($timestamp);
        if ($time === false || $time <= 0) return 'N/A';
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    } catch (Exception $e) {
        return 'N/A';
    }
}

// ================================================================
// GET SESSION DATA
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$doctor_branch_name = 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// REFRESH DOCTOR DATA FROM DATABASE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, is_online, profile_pic FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor_data) {
        $doctor_id = $doctor_data['id'];
        $doctor_name = $doctor_data['full_name'];
        $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
        $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
        $_SESSION['is_online'] = $doctor_data['is_online'] ?? 0;
        $_SESSION['profile_pic'] = $doctor_data['profile_pic'] ?? '';
        $_SESSION['full_name'] = $doctor_name;
        $_SESSION['user_id'] = $doctor_id;
        $_SESSION['doctor_id'] = $doctor_id;
    } else {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Dashboard database error: " . $e->getMessage());
}

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $_SESSION['doctor_id'] = $_SESSION['user_id'];
}
if (isset($_SESSION['doctor_id']) && $_SESSION['doctor_id'] > 0) {
    $_SESSION['user_id'] = $_SESSION['doctor_id'];
}

// Get branch name
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

// Get unread notifications count
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$doctor_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// GET INITIAL STATISTICS FOR RENDERING - FIXED: using visit_date
// ================================================================

// 1. Today's Patients - FIXED: use visit_date
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN status IN ('pending', 'assigned', 'with_doctor') THEN patient_id END) as pending,
        COUNT(DISTINCT CASE WHEN status IN ('completed', 'prescribed') THEN patient_id END) as completed
    FROM visits 
    WHERE doctor_id = ? AND DATE(visit_date) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC);
$today_patients_pending = $today_patients['pending'] ?? 0;
$today_patients_completed = $today_patients['completed'] ?? 0;
$today_patients_total = $today_patients_pending + $today_patients_completed;

// 2. Today's Visits - FIXED: use visit_date
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status IN ('pending', 'assigned', 'with_doctor') THEN 1 END) as pending,
        COUNT(CASE WHEN status IN ('completed', 'prescribed') THEN 1 END) as completed
    FROM visits 
    WHERE doctor_id = ? AND DATE(visit_date) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC);
$today_visits_pending = $today_visits['pending'] ?? 0;
$today_visits_completed = $today_visits['completed'] ?? 0;
$today_visits_total = $today_visits_pending + $today_visits_completed;

// 3. Total Patients (all time)
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 4. Total Visits (all time)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 5. Today's Appointments
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status IN ('scheduled', 'pending', 'confirmed') THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM appointments 
    WHERE doctor_id = ? AND DATE(appointment_date) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC);
$today_appointments_pending = $today_appointments['pending'] ?? 0;
$today_appointments_completed = $today_appointments['completed'] ?? 0;
$today_appointments_total = $today_appointments_pending + $today_appointments_completed;

// 6. Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 7. Total Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 8. Lab Tests
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM lab_tests 
    WHERE doctor_id = ?
");
$stmt->execute([$doctor_id]);
$lab_tests = $stmt->fetch(PDO::FETCH_ASSOC);
$lab_tests_total = $lab_tests['total'] ?? 0;
$lab_tests_pending = $lab_tests['pending'] ?? 0;
$lab_tests_completed = $lab_tests['completed'] ?? 0;

// 9. Pending Visits Count - FIXED: use visit_date
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? 
    AND status IN ('pending', 'assigned', 'with_doctor') 
    AND DATE(visit_date) = ?
");
$stmt->execute([$doctor_id, $today]);
$pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 10. Today's Appointments List
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ?
    AND a.status NOT IN ('cancelled')
    ORDER BY a.appointment_date ASC
    LIMIT 10
");
$stmt->execute([$doctor_id, $today]);
$today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 11. Pending Patients Queue - FIXED: use visit_date
$stmt = $db->prepare("
    SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone,
           TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_time
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ? 
    AND v.status IN ('pending', 'assigned', 'with_doctor') 
    AND DATE(v.visit_date) = ?
    ORDER BY v.created_at ASC
    LIMIT 10
");
$stmt->execute([$doctor_id, $today]);
$pending_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 12. Weekly Appointments Chart
$stmt = $db->prepare("
    SELECT DATE(appointment_date) as date, COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND status NOT IN ('cancelled')
    GROUP BY DATE(appointment_date)
    ORDER BY date
");
$stmt->execute([$doctor_id]);
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    $found = false;
    foreach ($weekly_data as $data) {
        if ($data['date'] == $date) {
            $chart_values[] = (int)$data['count'];
            $found = true;
            break;
        }
    }
    if (!$found) $chart_values[] = 0;
}

// 13. Recent Activities
$stmt = $db->prepare("
    (SELECT 'visit' as type, v.id, v.created_at, p.full_name as patient_name, 
            v.status, 'visit' as action_type
     FROM visits v
     JOIN patients p ON v.patient_id = p.id
     WHERE v.doctor_id = ?
     ORDER BY v.created_at DESC
     LIMIT 5)
    UNION ALL
    (SELECT 'appointment' as type, a.id, a.created_at, p.full_name as patient_name,
            a.status, 'appointment' as action_type
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     WHERE a.doctor_id = ?
     ORDER BY a.created_at DESC
     LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id, $doctor_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <!-- WELCOME SECTION -->
    <!-- ================================================================ -->
    <div class="welcome-hero">
        <div class="welcome-hero-content">
            <div class="welcome-hero-left">
                <div class="welcome-greeting">
                    <span class="greeting-icon">👋</span>
                    <div>
                        <h1 class="welcome-title">Welcome back, <span class="doctor-name" id="doctorName"><?= htmlspecialchars($doctor_name) ?></span></h1>
                        <p class="welcome-subtitle">
                            <span class="specialty-badge">
                                <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($doctor_specialty) ?>
                            </span>
                            <span class="branch-badge">
                                <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                            </span>
                            <span class="date-badge">
                                <i class="far fa-calendar-alt"></i> <?= date('l, F d, Y') ?>
                            </span>
                            <span class="update-badge" id="lastUpdateBadge">
                                <i class="fas fa-sync-alt fa-spin"></i> Loading...
                            </span>
                        </p>
                    </div>
                </div>
                <div class="welcome-stats-mini">
                    <div class="mini-stat">
                        <span class="mini-stat-number" id="miniAppointments"><?= $today_appointments_total ?></span>
                        <span class="mini-stat-label">Appointments Today</span>
                    </div>
                    <div class="mini-stat-divider"></div>
                    <div class="mini-stat">
                        <span class="mini-stat-number" id="miniPending"><?= $today_patients_pending ?></span>
                        <span class="mini-stat-label">Pending Patients</span>
                    </div>
                    <div class="mini-stat-divider"></div>
                    <div class="mini-stat">
                        <span class="mini-stat-number" id="miniTotalPatients"><?= $total_patients ?></span>
                        <span class="mini-stat-label">Total Patients</span>
                    </div>
                </div>
            </div>
            <div class="welcome-hero-right">
                <?php if ($today_patients_pending > 0): ?>
                    <a href="#queue" class="btn-pulse" id="btnPulse">
                        <i class="fas fa-user-clock"></i>
                        <span id="btnPulseText"><?= $today_patients_pending ?> Patient(s) Waiting</span>
                        <span class="pulse-dot"></span>
                    </a>
                <?php else: ?>
                    <a href="#queue" class="btn-pulse" id="btnPulse" style="display:none;">
                        <i class="fas fa-user-clock"></i>
                        <span id="btnPulseText">0 Patient(s) Waiting</span>
                        <span class="pulse-dot"></span>
                    </a>
                <?php endif; ?>
                <button onclick="DoctorStats.refresh()" class="btn-refresh" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 STATISTICS CARDS - CLICKABLE -->
    <!-- ================================================================ -->
    
    <!-- TOP 4 CARDS -->
    <div class="stats-grid" id="topCards">
        
        <!-- CARD 1: Today's Patients -->
        <a href="my_patients.php?filter=today" class="stat-card stat-card-blue card-clickable" id="cardTodayPatients">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-user-injured"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Today's Patients</span>
                        <span class="stat-card-number" id="todayPatientsTotal"><?= $today_patients_total ?></span>
                        <div class="stat-card-details">
                            <span class="stat-detail pending" id="todayPatientsPending">
                                <i class="fas fa-clock"></i> <?= $today_patients_pending ?> Pending
                            </span>
                            <span class="stat-detail completed" id="todayPatientsCompleted">
                                <i class="fas fa-check-circle"></i> <?= $today_patients_completed ?> Complete
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" id="todayPatientsProgress" style="width: <?= $today_patients_total > 0 ? min(100, ($today_patients_completed / max($today_patients_total, 1)) * 100) : 0 ?>%;"></div>
        </a>

        <!-- CARD 2: Today's Visits - KEY CARD -->
        <a href="visits.php?filter=today" class="stat-card stat-card-blue card-clickable" id="cardTodayVisits">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-clinic-medical"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Today's Visits</span>
                        <span class="stat-card-number" id="todayVisitsTotal"><?= $today_visits_total ?></span>
                        <div class="stat-card-details">
                            <span class="stat-detail pending" id="todayVisitsPending">
                                <i class="fas fa-clock"></i> <?= $today_visits_pending ?> Pending
                            </span>
                            <span class="stat-detail completed" id="todayVisitsCompleted">
                                <i class="fas fa-check-circle"></i> <?= $today_visits_completed ?> Complete
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" id="todayVisitsProgress" style="width: <?= $today_visits_total > 0 ? min(100, ($today_visits_completed / max($today_visits_total, 1)) * 100) : 0 ?>%;"></div>
        </a>

        <!-- CARD 3: Total Patients -->
        <a href="my_patients.php" class="stat-card stat-card-green card-clickable" id="cardTotalPatients">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Patients</span>
                        <span class="stat-card-number" id="totalPatients"><?= number_format($total_patients) ?></span>
                        <span class="stat-card-trend">
                            <i class="fas fa-arrow-up"></i> All time
                        </span>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_patients > 0 ? min(100, ($total_patients / 200) * 100) : 0 ?>%; background: #059669;"></div>
        </a>

        <!-- CARD 4: Total Visits -->
        <a href="visits.php" class="stat-card stat-card-green card-clickable" id="cardTotalVisits">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-notes-medical"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Visits</span>
                        <span class="stat-card-number" id="totalVisits"><?= number_format($total_visits) ?></span>
                        <span class="stat-card-trend">
                            <i class="fas fa-arrow-up"></i> All time
                        </span>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_visits > 0 ? min(100, ($total_visits / 500) * 100) : 0 ?>%; background: #059669;"></div>
        </a>

    </div>

    <!-- BOTTOM 4 CARDS -->
    <div class="stats-grid stats-grid-bottom" id="bottomCards">
        
        <!-- CARD 5: Today's Appointments -->
        <a href="appointments.php?filter=today" class="stat-card stat-card-blue card-clickable" id="cardTodayAppointments">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Today's Appointments</span>
                        <span class="stat-card-number" id="todayAppointmentsTotal"><?= $today_appointments_total ?></span>
                        <div class="stat-card-details">
                            <span class="stat-detail pending" id="todayAppointmentsPending">
                                <i class="fas fa-clock"></i> <?= $today_appointments_pending ?> Pending
                            </span>
                            <span class="stat-detail completed" id="todayAppointmentsCompleted">
                                <i class="fas fa-check-circle"></i> <?= $today_appointments_completed ?> Complete
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" id="todayAppointmentsProgress" style="width: <?= $today_appointments_total > 0 ? min(100, ($today_appointments_completed / max($today_appointments_total, 1)) * 100) : 0 ?>%;"></div>
        </a>

        <!-- CARD 6: Total Appointments -->
        <a href="appointments.php" class="stat-card stat-card-blue card-clickable" id="cardTotalAppointments">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Appointments</span>
                        <span class="stat-card-number" id="totalAppointments"><?= number_format($total_appointments) ?></span>
                        <span class="stat-card-trend">
                            <i class="fas fa-arrow-up"></i> All time
                        </span>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_appointments > 0 ? min(100, ($total_appointments / 200) * 100) : 0 ?>%;"></div>
        </a>

        <!-- CARD 7: Prescriptions -->
        <a href="view_prescriptions.php" class="stat-card stat-card-green card-clickable" id="cardPrescriptions">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-prescription"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Prescriptions</span>
                        <span class="stat-card-number" id="totalPrescriptions"><?= number_format($total_prescriptions) ?></span>
                        <span class="stat-card-trend">
                            <i class="fas fa-prescription"></i> Total issued
                        </span>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_prescriptions > 0 ? min(100, ($total_prescriptions / 100) * 100) : 0 ?>%; background: #059669;"></div>
        </a>

        <!-- CARD 8: Lab Tests -->
        <a href="lab_results.php" class="stat-card stat-card-green card-clickable <?= $lab_tests_pending > 0 ? 'has-badge' : '' ?>" id="cardLabTests">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-flask"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Lab Tests</span>
                        <span class="stat-card-number" id="labTestsTotal"><?= number_format($lab_tests_total) ?></span>
                        <div class="stat-card-details">
                            <span class="stat-detail pending <?= $lab_tests_pending > 0 ? 'text-orange' : '' ?>" id="labTestsPending">
                                <i class="fas fa-clock"></i> <?= $lab_tests_pending ?> Pending
                            </span>
                            <span class="stat-detail completed" id="labTestsCompleted">
                                <i class="fas fa-check-circle"></i> <?= $lab_tests_completed ?> Complete
                            </span>
                        </div>
                    </div>
                </div>
                <?php if ($lab_tests_pending > 0): ?>
                    <div class="stat-card-right">
                        <span class="stat-card-badge danger" id="labTestsBadge"><?= $lab_tests_pending ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card-progress" id="labTestsProgress" style="width: <?= $lab_tests_total > 0 ? min(100, ($lab_tests_completed / max($lab_tests_total, 1)) * 100) : 0 ?>%; background: #059669;"></div>
        </a>

    </div>

    <!-- ================================================================ -->
    <!-- CHART & TODAY'S APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        <!-- Weekly Appointments Chart -->
        <div class="dashboard-card lg:col-span-2">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">
                    <i class="fas fa-chart-area title-blue"></i>
                    Weekly Appointments Trend
                </h3>
                <span class="text-xs text-gray-400">Last 7 days</span>
            </div>
            <div class="chart-container">
                <canvas id="appointmentsChart"></canvas>
            </div>
        </div>

        <!-- Today's Appointments List -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">
                    <i class="fas fa-calendar-check title-green"></i>
                    Today's Appointments
                    <span class="text-sm font-normal text-gray-400" id="appointmentsCount">(<?= $today_appointments_total ?>)</span>
                </h3>
            </div>
            <div class="appointments-list" id="appointmentsList">
                <?php if (count($today_appointments_list) > 0): ?>
                    <?php foreach ($today_appointments_list as $appt): ?>
                        <div class="appointment-item">
                            <div class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></div>
                            <div class="appointment-patient">
                                <div class="appointment-name"><?= htmlspecialchars($appt['patient_name']) ?></div>
                                <div class="appointment-id"><?= htmlspecialchars($appt['patient_id']) ?></div>
                            </div>
                            <span class="appointment-status <?= $appt['status'] ?? 'scheduled' ?>">
                                <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No appointments scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="dashboard-card-footer">
                <a href="appointments.php" class="card-link">View all appointments →</a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PENDING PATIENTS QUEUE -->
    <!-- ================================================================ -->
    <div class="dashboard-card queue-card mb-6" id="queue">
        <div class="dashboard-card-header">
            <h3 class="dashboard-card-title">
                <i class="fas fa-user-clock title-orange"></i>
                Pending Patients Queue
                <span class="text-sm font-normal text-gray-400" id="queueCount">(<?= count($pending_patients) ?> waiting)</span>
            </h3>
            <span class="text-xs text-gray-400">
                <i class="far fa-clock mr-1"></i> Waiting time
            </span>
        </div>
        
        <div class="queue-list" id="queueList">
            <?php if (count($pending_patients) > 0): ?>
                <?php foreach ($pending_patients as $index => $patient): ?>
                    <div class="queue-item <?= $index === 0 ? 'queue-item-first' : '' ?>">
                        <div class="queue-number">#<?= $index + 1 ?></div>
                        <div class="queue-patient">
                            <div class="queue-name">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                                <?php if ($index === 0): ?>
                                    <span class="queue-badge">Next</span>
                                <?php endif; ?>
                            </div>
                            <div class="queue-details">
                                <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?> • 
                                <?= htmlspecialchars($patient['phone'] ?? '') ?>
                            </div>
                        </div>
                        <div class="queue-waiting">
                            <span class="queue-time <?= ($patient['waiting_time'] ?? 0) > 30 ? 'queue-time-long' : '' ?>">
                                <?= ($patient['waiting_time'] ?? 0) > 0 ? ($patient['waiting_time'] . ' min') : 'Just now' ?>
                            </span>
                            <span class="queue-status <?= $patient['status'] ?? 'pending' ?>">
                                <?= ucfirst($patient['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                        <div class="queue-action">
                            <a href="consultation.php?visit_id=<?= $patient['id'] ?>" class="btn-consult">
                                <i class="fas fa-stethoscope"></i> Consult
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state empty-state-large">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <p class="text-gray-400">No patients waiting! All clear.</p>
                    <p class="text-xs text-gray-400">Take a break or review completed cases</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES & QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        <!-- Recent Activities -->
        <div class="dashboard-card lg:col-span-2">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">
                    <i class="fas fa-clock title-blue"></i>
                    Recent Activities
                </h3>
                <a href="system_logs.php" class="card-link">View All</a>
            </div>
            <div class="activities-list" id="activitiesList">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= $activity['action_type'] === 'appointment' ? 'activity-icon-blue' : 'activity-icon-green' ?>">
                                <i class="fas <?= $activity['action_type'] === 'appointment' ? 'fa-calendar-check' : 'fa-user-injured' ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-action">
                                    <?php if ($activity['action_type'] === 'appointment'): ?>
                                        Appointment <?= $activity['status'] ?>
                                    <?php else: ?>
                                        Visit <?= $activity['status'] ?>
                                    <?php endif; ?>
                                    <span class="activity-patient">- <?= htmlspecialchars($activity['patient_name'] ?? 'Unknown') ?></span>
                                </div>
                                <div class="activity-details">
                                    <?php if ($activity['action_type'] === 'appointment'): ?>
                                        <?= date('M d, h:i A', strtotime($activity['created_at'] ?? '')) ?>
                                    <?php else: ?>
                                        Visit #<?= htmlspecialchars($activity['id'] ?? '') ?>
                                    <?php endif; ?>
                                    <span class="activity-time">• <?= time_ago($activity['created_at'] ?? '') ?></span>
                                </div>
                            </div>
                            <?php if ($activity['action_type'] === 'visit' && ($activity['status'] ?? '') === 'pending'): ?>
                                <a href="consultation.php?visit_id=<?= $activity['id'] ?>" class="btn-consult-sm">Consult</a>
                            <?php elseif ($activity['action_type'] === 'appointment'): ?>
                                <a href="appointment_details.php?id=<?= $activity['id'] ?>" class="btn-view-sm">View</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">
                    <i class="fas fa-bolt title-yellow"></i>
                    Quick Actions
                </h3>
            </div>
            <div class="quick-actions-grid">
                <a href="my_patients.php" class="quick-action quick-action-blue">
                    <i class="fas fa-users"></i>
                    <span>My Patients</span>
                </a>
                <a href="appointments.php" class="quick-action quick-action-green">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="prescribe.php" class="quick-action quick-action-blue">
                    <i class="fas fa-prescription"></i>
                    <span>Prescribe</span>
                </a>
                <a href="view_prescriptions.php" class="quick-action quick-action-green">
                    <i class="fas fa-list"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="lab_results.php" class="quick-action quick-action-blue">
                    <i class="fas fa-flask"></i>
                    <span>Lab Results</span>
                </a>
                <a href="profile.php" class="quick-action quick-action-green">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Doctor Dashboard
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
<!-- FULL CSS - LIGHT & DARK MODE SUPPORT -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ROOT VARIABLES - LIGHT & DARK MODE
       ================================================================ */
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #6EA8FE;
        --primary-bg: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #34D399;
        --success-bg: #D1FAE5;
        --danger: #DC2626;
        --danger-dark: #B91C1C;
        --danger-light: #F87171;
        --danger-bg: #FEE2E2;
        --warning: #D97706;
        --warning-bg: #FEF3C7;
        --white: #FFFFFF;
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
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }
    
    /* ================================================================
       BASE STYLES
       ================================================================ */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Inter', 'Segoe UI', sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }
    
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--bg-body); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        transition: all 0.3s ease;
        background: var(--bg-body);
    }
    
    /* Grid */
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .gap-6 { gap: 24px; }
    .mb-6 { margin-bottom: 24px; }
    .lg\:col-span-2 { grid-column: span 2; }
    
    /* ================================================================
       WELCOME HERO
       ================================================================ */
    .welcome-hero {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 20px;
        padding: 28px 32px;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
    }
    
    .welcome-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .welcome-hero-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        position: relative;
        z-index: 1;
    }
    
    .welcome-hero-left {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .welcome-greeting {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .greeting-icon { font-size: 2.2rem; line-height: 1; }
    
    .welcome-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        margin: 0;
    }
    
    .welcome-title .doctor-name { color: #93C5FD; }
    
    .welcome-subtitle {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin: 0;
    }
    
    .specialty-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(4px);
    }
    
    .branch-badge {
        background: rgba(255,255,255,0.1);
        color: #BFDBFE;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .date-badge {
        background: rgba(255,255,255,0.08);
        color: #93C5FD;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .update-badge {
        background: rgba(255,255,255,0.1);
        color: #93C5FD;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .update-badge .fa-spin { animation: fa-spin 2s infinite linear; }
    @keyframes fa-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    
    .welcome-stats-mini {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-top: 4px;
    }
    
    .mini-stat {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .mini-stat-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: white;
    }
    
    .mini-stat-label {
        font-size: 0.7rem;
        color: #93C5FD;
        font-weight: 500;
    }
    
    .mini-stat-divider {
        width: 1px;
        height: 24px;
        background: rgba(255,255,255,0.2);
    }
    
    .welcome-hero-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .btn-pulse {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: #EF4444;
        color: white;
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.3s ease;
        animation: pulse-glow 2s infinite;
    }
    
    .btn-pulse:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    }
    
    .pulse-dot {
        width: 8px;
        height: 8px;
        background: white;
        border-radius: 50%;
        display: inline-block;
        animation: pulse-dot 1.5s infinite;
    }
    
    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
        50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }
    
    .btn-refresh {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 10px 18px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.85rem;
        border: 1px solid rgba(255,255,255,0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(4px);
    }
    
    .btn-refresh:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
    }
    
    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 18px;
    }
    
    .stats-grid-bottom { margin-bottom: 28px; }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 22px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        text-decoration: none;
        display: block;
        color: inherit;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary);
    }
    
    .stat-card-inner {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    .stat-card-left {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        flex: 1;
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
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #0AA84F); }
    
    .stat-card-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        flex: 1;
    }
    
    .stat-card-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
        transition: all 0.3s ease;
    }
    
    .stat-card-details {
        display: flex;
        gap: 12px;
        margin-top: 4px;
        flex-wrap: wrap;
    }
    
    .stat-detail {
        font-size: 0.65rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: all 0.3s ease;
    }
    
    .stat-detail.pending { color: #D97706; }
    .stat-detail.completed { color: #059669; }
    .stat-detail.text-orange { color: #D97706; }
    
    .stat-card-trend {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-card-trend .fa-arrow-up { color: #059669; }
    
    .stat-card-right {
        display: flex;
        align-items: flex-start;
        flex-shrink: 0;
    }
    
    .stat-card-badge {
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        background: #0B5ED7;
        padding: 2px 12px;
        border-radius: 20px;
        min-width: 24px;
        text-align: center;
    }
    
    .stat-card-badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .stat-card-progress {
        height: 3px;
        background: #0B5ED7;
        border-radius: 0 0 16px 16px;
        position: absolute;
        bottom: 0;
        left: 0;
        transition: width 1s ease;
        opacity: 0.3;
    }
    
    .stat-card-green .stat-card-progress { background: #059669; }
    
    /* ================================================================
       DASHBOARD CARDS
       ================================================================ */
    .dashboard-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .dashboard-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .dashboard-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .dashboard-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    .title-orange { color: #D97706; }
    .title-yellow { color: #D97706; }
    
    .dashboard-card-footer {
        padding-top: 14px;
        margin-top: 14px;
        border-top: 1px solid var(--border-color);
        text-align: center;
    }
    
    .card-link {
        color: #0B5ED7;
        font-size: 0.85rem;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }
    
    .card-link:hover { text-decoration: underline; color: #0A4CA8; }
    
    /* ================================================================
       CHART
       ================================================================ */
    .chart-container {
        height: 200px;
        max-height: 200px;
    }
    
    .chart-container canvas {
        height: 100% !important;
        max-height: 200px !important;
    }
    
    /* ================================================================
       APPOINTMENTS LIST
       ================================================================ */
    .appointments-list {
        max-height: 220px;
        overflow-y: auto;
    }
    
    .appointments-list::-webkit-scrollbar { width: 4px; }
    .appointments-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .appointments-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .appointment-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        gap: 12px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .appointment-item:hover { background: var(--primary-bg); }
    .appointment-item:last-child { border-bottom: none; }
    
    .appointment-time {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--text-primary);
        min-width: 55px;
    }
    
    .appointment-patient { flex: 1; }
    .appointment-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    .appointment-id { font-size: 0.65rem; color: var(--text-secondary); }
    
    .appointment-status {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }
    
    .appointment-status.scheduled { background: #EFF6FF; color: #0B5ED7; }
    .appointment-status.confirmed { background: #ECFDF5; color: #059669; }
    .appointment-status.completed { background: #ECFDF5; color: #059669; }
    .appointment-status.cancelled { background: #FEE2E2; color: #EF4444; }
    .appointment-status.pending { background: #FEF3C7; color: #D97706; }
    
    /* ================================================================
       QUEUE
       ================================================================ */
    .queue-card { border-color: #FDE68A; }
    .queue-list {
        max-height: 300px;
        overflow-y: auto;
    }
    .queue-list::-webkit-scrollbar { width: 4px; }
    .queue-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .queue-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .queue-item {
        display: flex;
        align-items: center;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        gap: 14px;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .queue-item:hover { background: var(--gray-50); }
    .queue-item-first {
        background: var(--primary-bg);
        border-left: 4px solid #0B5ED7;
        border-radius: 8px 0 0 8px;
    }
    
    .queue-number {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--text-secondary);
        min-width: 30px;
    }
    
    .queue-patient { flex: 1; }
    .queue-name {
        font-weight: 500;
        font-size: 0.9rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .queue-badge {
        font-size: 0.55rem;
        font-weight: 700;
        background: #EF4444;
        color: white;
        padding: 2px 10px;
        border-radius: 12px;
        animation: pulse-badge 2s infinite;
    }
    
    .queue-details { font-size: 0.65rem; color: var(--text-secondary); }
    
    .queue-waiting {
        text-align: right;
        margin-left: auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
    }
    
    .queue-time { font-size: 0.7rem; color: var(--text-secondary); }
    .queue-time-long { color: #EF4444; font-weight: 600; }
    
    .queue-status {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
    }
    
    .queue-status.pending { background: #FEF3C7; color: #D97706; }
    .queue-status.assigned { background: #EFF6FF; color: #0B5ED7; }
    .queue-status.with_doctor { background: #ECFDF5; color: #059669; }
    
    .btn-consult {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #7C3AED;
        color: white;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .btn-consult:hover { background: #6D28D9; transform: scale(1.05); }
    .btn-consult-sm {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #7C3AED;
        color: white;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
        flex-shrink: 0;
    }
    .btn-consult-sm:hover { background: #6D28D9; transform: scale(1.05); }
    
    .btn-view-sm {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #0B5ED7;
        color: white;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
        flex-shrink: 0;
    }
    .btn-view-sm:hover { background: #0A4CA8; transform: scale(1.05); }
    
    /* ================================================================
       ACTIVITIES
       ================================================================ */
    .activities-list {
        max-height: 260px;
        overflow-y: auto;
    }
    .activities-list::-webkit-scrollbar { width: 4px; }
    .activities-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .activities-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .activity-item:hover { background: var(--gray-50); }
    .activity-item:last-child { border-bottom: none; }
    
    .activity-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
        color: white;
    }
    
    .activity-icon-blue { background: #0B5ED7; }
    .activity-icon-green { background: #059669; }
    
    .activity-content { flex: 1; min-width: 0; }
    
    .activity-action {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .activity-patient { color: var(--text-secondary); font-weight: 400; }
    .activity-details { font-size: 0.7rem; color: var(--text-secondary); }
    .activity-time { color: var(--gray-300); }
    
    /* ================================================================
       QUICK ACTIONS
       ================================================================ */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
    }
    
    .quick-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 16px 10px;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--text-primary);
        cursor: pointer;
        gap: 6px;
    }
    
    .quick-action:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .quick-action i { font-size: 1.3rem; }
    .quick-action span { font-size: 0.65rem; font-weight: 500; text-align: center; }
    .quick-action-blue i { color: #0B5ED7; }
    .quick-action-green i { color: #059669; }
    .quick-action-blue:hover { border-color: #0B5ED7; }
    .quick-action-green:hover { border-color: #059669; }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 20px 10px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 6px;
    }
    .empty-state p { font-size: 0.85rem; }
    .empty-state-large { padding: 30px 10px; }
    .empty-state-large i { font-size: 3rem; }
    .text-green-500 { color: #059669; }
    .text-gray-400 { color: var(--text-secondary); }
    .text-xs { font-size: 0.75rem; }
    
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
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
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
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    .text-gray-300 { color: #D1D5DB; }
    .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 1024px) {
        .quick-actions-grid { grid-template-columns: 1fr 1fr 1fr; }
        .welcome-hero { padding: 20px 24px; }
        .welcome-title { font-size: 1.3rem; }
        .main-content { padding: 16px; }
    }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .quick-actions-grid { grid-template-columns: 1fr 1fr; }
        .welcome-hero-content { flex-direction: column; align-items: flex-start; }
        .welcome-hero-right { width: 100%; }
        .welcome-hero-right .btn-pulse, .welcome-hero-right .btn-refresh { flex: 1; justify-content: center; }
        .stat-card-number { font-size: 1.4rem; }
        .queue-item { flex-wrap: wrap; gap: 6px; }
        .queue-waiting { flex-direction: row; align-items: center; margin-left: 0; width: 100%; gap: 10px; }
        .queue-action { width: 100%; }
        .queue-action .btn-consult { width: 100%; justify-content: center; }
        .appointment-item { flex-wrap: wrap; }
        .appointment-time { min-width: 50px; }
        .activity-item { flex-wrap: wrap; }
        .activity-item .btn-consult-sm, .activity-item .btn-view-sm { width: 100%; justify-content: center; }
        .dashboard-card { padding: 14px 16px; }
        .chart-container { height: 160px; }
        .welcome-stats-mini { flex-wrap: wrap; }
        .welcome-greeting { flex-wrap: wrap; }
        .greeting-icon { font-size: 1.8rem; }
        .lg\:col-span-2 { grid-column: 1; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .quick-actions-grid { grid-template-columns: 1fr 1fr; }
        .stat-card-number { font-size: 1.2rem; }
        .welcome-title { font-size: 1.1rem; }
        .welcome-hero { padding: 16px 18px; }
        .btn-pulse { font-size: 0.75rem; padding: 8px 14px; }
        .btn-refresh { font-size: 0.75rem; padding: 8px 14px; }
        .specialty-badge, .branch-badge, .date-badge { font-size: 0.7rem; padding: 2px 10px; }
    }
    
    /* ================================================================
       DARK MODE OVERRIDES
       ================================================================ */
    [data-theme="dark"] .stat-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }
    [data-theme="dark"] .stat-card-number {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card-label {
        color: #94A3B8;
    }
    [data-theme="dark"] .stat-detail.pending {
        color: #FBBF24;
    }
    [data-theme="dark"] .stat-detail.completed {
        color: #34D399;
    }
    [data-theme="dark"] .stat-detail.text-orange {
        color: #FBBF24;
    }
    [data-theme="dark"] .stat-card-trend {
        color: #94A3B8;
    }
    [data-theme="dark"] .stat-card-trend .fa-arrow-up {
        color: #34D399;
    }
    [data-theme="dark"] .dashboard-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .dashboard-card:hover {
        border-color: #0B5ED7;
    }
    [data-theme="dark"] .dashboard-card-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .title-blue { color: #6EA8FE; }
    [data-theme="dark"] .title-green { color: #34D399; }
    [data-theme="dark"] .title-orange { color: #FBBF24; }
    [data-theme="dark"] .title-yellow { color: #FBBF24; }
    [data-theme="dark"] .card-link { color: #6EA8FE; }
    [data-theme="dark"] .card-link:hover { color: #93C5FD; }
    [data-theme="dark"] .appointment-item {
        border-color: #334155;
    }
    [data-theme="dark"] .appointment-item:hover {
        background: #1E3A5F;
    }
    [data-theme="dark"] .appointment-name {
        color: #F1F5F9;
    }
    [data-theme="dark"] .appointment-id {
        color: #94A3B8;
    }
    [data-theme="dark"] .appointment-time {
        color: #F1F5F9;
    }
    [data-theme="dark"] .queue-item {
        border-color: #334155;
    }
    [data-theme="dark"] .queue-item:hover {
        background: #0F172A;
    }
    [data-theme="dark"] .queue-item-first {
        background: #1E3A5F;
        border-left-color: #6EA8FE;
    }
    [data-theme="dark"] .queue-name {
        color: #F1F5F9;
    }
    [data-theme="dark"] .queue-details {
        color: #94A3B8;
    }
    [data-theme="dark"] .queue-number {
        color: #94A3B8;
    }
    [data-theme="dark"] .queue-time {
        color: #94A3B8;
    }
    [data-theme="dark"] .queue-time-long {
        color: #F87171;
    }
    [data-theme="dark"] .queue-card {
        border-color: #3D2E0A;
    }
    [data-theme="dark"] .activity-item {
        border-color: #334155;
    }
    [data-theme="dark"] .activity-item:hover {
        background: #0F172A;
    }
    [data-theme="dark"] .activity-action {
        color: #F1F5F9;
    }
    [data-theme="dark"] .activity-patient {
        color: #94A3B8;
    }
    [data-theme="dark"] .activity-details {
        color: #94A3B8;
    }
    [data-theme="dark"] .activity-time {
        color: #64748B;
    }
    [data-theme="dark"] .quick-action {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    [data-theme="dark"] .quick-action:hover {
        border-color: #0B5ED7;
    }
    [data-theme="dark"] .quick-action i {
        color: #6EA8FE;
    }
    [data-theme="dark"] .quick-action-green i {
        color: #34D399;
    }
    [data-theme="dark"] .empty-state {
        color: #94A3B8;
    }
    [data-theme="dark"] .empty-state i {
        color: #334155;
    }
    [data-theme="dark"] .text-gray-400 {
        color: #94A3B8 !important;
    }
    [data-theme="dark"] .text-gray-300 {
        color: #64748B !important;
    }
    [data-theme="dark"] .text-green-500 {
        color: #34D399 !important;
    }
    [data-theme="dark"] .welcome-hero {
        background: linear-gradient(135deg, #0A4CA8, #0B5ED7);
    }
    [data-theme="dark"] .welcome-title .doctor-name {
        color: #93C5FD;
    }
    [data-theme="dark"] .specialty-badge {
        background: rgba(255,255,255,0.1);
        color: white;
    }
    [data-theme="dark"] .branch-badge {
        background: rgba(255,255,255,0.08);
        color: #BFDBFE;
    }
    [data-theme="dark"] .date-badge {
        background: rgba(255,255,255,0.06);
        color: #93C5FD;
    }
    [data-theme="dark"] .update-badge {
        background: rgba(255,255,255,0.08);
        color: #93C5FD;
    }
    [data-theme="dark"] .mini-stat-number {
        color: white;
    }
    [data-theme="dark"] .mini-stat-label {
        color: #93C5FD;
    }
    [data-theme="dark"] .mini-stat-divider {
        background: rgba(255,255,255,0.15);
    }
    [data-theme="dark"] .btn-refresh {
        background: rgba(255,255,255,0.1);
        color: white;
        border-color: rgba(255,255,255,0.15);
    }
    [data-theme="dark"] .btn-refresh:hover {
        background: rgba(255,255,255,0.2);
    }
    [data-theme="dark"] .dashboard-card-footer {
        border-color: #334155;
    }
    [data-theme="dark"] .appointment-status.scheduled {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .appointment-status.confirmed {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .appointment-status.completed {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .appointment-status.cancelled {
        background: #3A1A1A;
        color: #F87171;
    }
    [data-theme="dark"] .appointment-status.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    [data-theme="dark"] .queue-status.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    [data-theme="dark"] .queue-status.assigned {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .queue-status.with_doctor {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .btn-consult {
        background: #6D28D9;
    }
    [data-theme="dark"] .btn-consult:hover {
        background: #5B21B6;
    }
    [data-theme="dark"] .btn-consult-sm {
        background: #6D28D9;
    }
    [data-theme="dark"] .btn-consult-sm:hover {
        background: #5B21B6;
    }
    [data-theme="dark"] .btn-view-sm {
        background: #0A4CA8;
    }
    [data-theme="dark"] .btn-view-sm:hover {
        background: #0B5ED7;
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT - CHART -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // ================================================================
    // CHART - RENDER
    // ================================================================
    var chartInstance = null;
    var chartLabels = <?= json_encode($chart_labels) ?>;
    var chartValues = <?= json_encode($chart_values) ?>;
    
    function renderChart(labels, values) {
        var ctx = document.getElementById('appointmentsChart')?.getContext('2d');
        if (!ctx) return;
        
        if (chartInstance) {
            chartInstance.destroy();
        }
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? '#334155' : '#E2E8F0';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Appointments',
                    data: values,
                    backgroundColor: isDark ? '#6EA8FE' : '#0B5ED7',
                    borderColor: isDark ? '#0B5ED7' : '#0A4CA8',
                    borderWidth: 2,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
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
    // DARK MODE - LISTEN FOR CHANGES FROM HEADER
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        if (isDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        renderChart(chartLabels, chartValues);
    });
    
    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        
        renderChart(chartLabels, chartValues);
        
        setTimeout(function() {
            var pending = parseInt(document.getElementById('todayPatientsPending')?.textContent || '0');
            if (pending > 0) {
                showToast('👋 Patients Waiting', 'You have ' + pending + ' patient(s) waiting for consultation', 'info');
            }
        }, 1500);
    });
    
    // Re-render chart when dark mode changes
    var observer = new MutationObserver(function() {
        renderChart(chartLabels, chartValues);
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    
    console.log('%c👨‍⚕️ Doctor Dashboard Initialized', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c📅 Today\'s visits: <?= $today_visits_total ?> (using visit_date)', 'font-size:12px; color:#059669;');
    console.log('%c🔄 Auto-update active every 5 seconds', 'font-size:12px; color:#34D399;');
    console.log('%c🌙 Dark mode uses CSS + localStorage (syncs with header)', 'font-size:12px; color:#6EA8FE;');
</script>

<!-- ================================================================ -->
<!-- DOCTOR GLOBAL STATS AUTO-UPDATE SCRIPT - FIXED -->
<!-- ================================================================ -->
<script>
(function() {
    'use strict';
    
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var CONFIG = {
        updateInterval: 5000, // 5 seconds
        apiEndpoint: '/dispensary_system/frontend/pages/doctor/get_doctor_stats.php',
        debug: true
    };
    
    // ================================================================
    // STATE
    // ================================================================
    var state = {
        isUpdating: false,
        initialized: false
    };
    
    // ================================================================
    // DOM ELEMENT CACHE
    // ================================================================
    var elements = {};
    
    function getElement(id) {
        return document.getElementById(id);
    }
    
    function safeText(el, text) {
        if (el) el.textContent = text !== undefined ? text : '0';
    }
    
    function safeHTML(el, html) {
        if (el) el.innerHTML = html || '';
    }
    
    function findElements() {
        elements = {
            // Today's Visits - KEY ELEMENTS
            todayVisitsTotal: getElement('todayVisitsTotal'),
            todayVisitsPending: getElement('todayVisitsPending'),
            todayVisitsCompleted: getElement('todayVisitsCompleted'),
            todayVisitsProgress: getElement('todayVisitsProgress'),
            
            // Today's Patients
            todayPatientsTotal: getElement('todayPatientsTotal'),
            todayPatientsPending: getElement('todayPatientsPending'),
            todayPatientsCompleted: getElement('todayPatientsCompleted'),
            todayPatientsProgress: getElement('todayPatientsProgress'),
            
            // Total stats
            totalPatients: getElement('totalPatients'),
            totalVisits: getElement('totalVisits'),
            
            // Appointments
            todayAppointmentsTotal: getElement('todayAppointmentsTotal'),
            todayAppointmentsPending: getElement('todayAppointmentsPending'),
            todayAppointmentsCompleted: getElement('todayAppointmentsCompleted'),
            todayAppointmentsProgress: getElement('todayAppointmentsProgress'),
            totalAppointments: getElement('totalAppointments'),
            appointmentsCount: getElement('appointmentsCount'),
            appointmentsList: getElement('appointmentsList'),
            
            // Prescriptions & Lab
            totalPrescriptions: getElement('totalPrescriptions'),
            labTestsTotal: getElement('labTestsTotal'),
            labTestsPending: getElement('labTestsPending'),
            labTestsCompleted: getElement('labTestsCompleted'),
            labTestsProgress: getElement('labTestsProgress'),
            labTestsBadge: getElement('labTestsBadge'),
            
            // Queue
            queueList: getElement('queueList'),
            queueCount: getElement('queueCount'),
            
            // Mini stats
            miniAppointments: getElement('miniAppointments'),
            miniPending: getElement('miniPending'),
            miniTotalPatients: getElement('miniTotalPatients'),
            btnPulse: getElement('btnPulse'),
            btnPulseText: getElement('btnPulseText'),
            
            // Timestamps
            footerTimestamp: getElement('footerTimestamp'),
            updateBadge: getElement('lastUpdateBadge'),
            currentDateTime: getElement('currentDateTime'),
            
            // Refresh button
            refreshBtn: getElement('refreshBtn')
        };
    }
    
    // ================================================================
    // UPDATE FUNCTIONS
    // ================================================================
    function updateStats(data) {
        // Today's Visits - FIXED: These must stay persistent
        var todayVisitsTotal = data.today_visits_total || 0;
        var todayVisitsPending = data.today_visits_pending || 0;
        var todayVisitsCompleted = data.today_visits_completed || 0;
        
        // Today's Patients
        var todayPatientsTotal = data.today_patients_total || 0;
        var todayPatientsPending = data.today_patients_pending || 0;
        var todayPatientsCompleted = data.today_patients_completed || 0;
        
        // Today's Appointments
        var todayApptsTotal = data.today_appointments_total || 0;
        var todayApptsPending = data.today_appointments_pending || 0;
        var todayApptsCompleted = data.today_appointments_completed || 0;
        
        // Lab Tests
        var labTotal = data.lab_tests_total || 0;
        var labPending = data.lab_tests_pending || 0;
        var labCompleted = data.lab_tests_completed || 0;
        
        console.log('📊 Updating stats - Today Visits:', todayVisitsTotal);
        
        // ================================================================
        // UPDATE TODAY'S VISITS - KEY CARD
        // ================================================================
        if (elements.todayVisitsTotal) {
            elements.todayVisitsTotal.textContent = todayVisitsTotal;
        }
        if (elements.todayVisitsPending) {
            elements.todayVisitsPending.innerHTML = '<i class="fas fa-clock"></i> ' + todayVisitsPending + ' Pending';
        }
        if (elements.todayVisitsCompleted) {
            elements.todayVisitsCompleted.innerHTML = '<i class="fas fa-check-circle"></i> ' + todayVisitsCompleted + ' Complete';
        }
        if (elements.todayVisitsProgress) {
            var pct = todayVisitsTotal > 0 ? Math.min(100, (todayVisitsCompleted / Math.max(todayVisitsTotal, 1)) * 100) : 0;
            elements.todayVisitsProgress.style.width = pct + '%';
        }
        
        // ================================================================
        // UPDATE TODAY'S PATIENTS
        // ================================================================
        if (elements.todayPatientsTotal) {
            elements.todayPatientsTotal.textContent = todayPatientsTotal;
        }
        if (elements.todayPatientsPending) {
            elements.todayPatientsPending.innerHTML = '<i class="fas fa-clock"></i> ' + todayPatientsPending + ' Pending';
        }
        if (elements.todayPatientsCompleted) {
            elements.todayPatientsCompleted.innerHTML = '<i class="fas fa-check-circle"></i> ' + todayPatientsCompleted + ' Complete';
        }
        if (elements.todayPatientsProgress) {
            var pct = todayPatientsTotal > 0 ? Math.min(100, (todayPatientsCompleted / Math.max(todayPatientsTotal, 1)) * 100) : 0;
            elements.todayPatientsProgress.style.width = pct + '%';
        }
        
        // ================================================================
        // UPDATE TOTAL STATS
        // ================================================================
        if (elements.totalPatients) {
            elements.totalPatients.textContent = formatNumber(data.total_patients || 0);
        }
        if (elements.totalVisits) {
            elements.totalVisits.textContent = formatNumber(data.total_visits || 0);
        }
        
        // ================================================================
        // UPDATE APPOINTMENTS
        // ================================================================
        if (elements.todayAppointmentsTotal) {
            elements.todayAppointmentsTotal.textContent = todayApptsTotal;
        }
        if (elements.todayAppointmentsPending) {
            elements.todayAppointmentsPending.innerHTML = '<i class="fas fa-clock"></i> ' + todayApptsPending + ' Pending';
        }
        if (elements.todayAppointmentsCompleted) {
            elements.todayAppointmentsCompleted.innerHTML = '<i class="fas fa-check-circle"></i> ' + todayApptsCompleted + ' Complete';
        }
        if (elements.todayAppointmentsProgress) {
            var pct = todayApptsTotal > 0 ? Math.min(100, (todayApptsCompleted / Math.max(todayApptsTotal, 1)) * 100) : 0;
            elements.todayAppointmentsProgress.style.width = pct + '%';
        }
        if (elements.totalAppointments) {
            elements.totalAppointments.textContent = formatNumber(data.total_appointments || 0);
        }
        if (elements.appointmentsCount) {
            elements.appointmentsCount.textContent = '(' + todayApptsTotal + ')';
        }
        
        // ================================================================
        // UPDATE PRESCRIPTIONS & LAB
        // ================================================================
        if (elements.totalPrescriptions) {
            elements.totalPrescriptions.textContent = formatNumber(data.total_prescriptions || 0);
        }
        if (elements.labTestsTotal) {
            elements.labTestsTotal.textContent = formatNumber(labTotal);
        }
        if (elements.labTestsPending) {
            elements.labTestsPending.innerHTML = '<i class="fas fa-clock"></i> ' + labPending + ' Pending';
        }
        if (elements.labTestsCompleted) {
            elements.labTestsCompleted.innerHTML = '<i class="fas fa-check-circle"></i> ' + labCompleted + ' Complete';
        }
        if (elements.labTestsProgress) {
            var pct = labTotal > 0 ? Math.min(100, (labCompleted / Math.max(labTotal, 1)) * 100) : 0;
            elements.labTestsProgress.style.width = pct + '%';
        }
        if (elements.labTestsBadge) {
            if (labPending > 0) {
                elements.labTestsBadge.textContent = labPending;
                elements.labTestsBadge.style.display = 'inline-block';
            } else {
                elements.labTestsBadge.style.display = 'none';
            }
        }
        
        // ================================================================
        // UPDATE MINI STATS
        // ================================================================
        if (elements.miniAppointments) {
            elements.miniAppointments.textContent = todayApptsTotal;
        }
        if (elements.miniPending) {
            elements.miniPending.textContent = todayPatientsPending;
        }
        if (elements.miniTotalPatients) {
            elements.miniTotalPatients.textContent = formatNumber(data.total_patients || 0);
        }
        
        // Pulse button
        if (elements.btnPulse) {
            if (todayPatientsPending > 0) {
                elements.btnPulse.style.display = 'inline-flex';
                if (elements.btnPulseText) {
                    elements.btnPulseText.textContent = todayPatientsPending + ' Patient(s) Waiting';
                }
            } else {
                elements.btnPulse.style.display = 'none';
            }
        }
        
        // ================================================================
        // UPDATE QUEUE
        // ================================================================
        if (elements.queueCount) {
            elements.queueCount.textContent = '(' + (data.pending_visits || 0) + ' waiting)';
        }
        
        // ================================================================
        // UPDATE TIMESTAMPS
        // ================================================================
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        
        if (elements.footerTimestamp) {
            elements.footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        if (elements.currentDateTime) {
            elements.currentDateTime.textContent = dateStr + ' • ' + timeStr;
        }
        if (elements.updateBadge) {
            elements.updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
        }
    }
    
    function formatNumber(num) {
        if (num === undefined || num === null) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // ================================================================
    // FETCH DATA
    // ================================================================
    function fetchStats() {
        if (state.isUpdating) return;
        state.isUpdating = true;
        
        var url = CONFIG.apiEndpoint + '?t=' + new Date().getTime();
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network error: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            state.isUpdating = false;
            if (data.success) {
                updateStats(data.data);
            } else {
                console.error('❌ API Error:', data.error);
            }
        })
        .catch(function(error) {
            console.error('❌ Fetch error:', error);
            state.isUpdating = false;
            if (elements.updateBadge) {
                elements.updateBadge.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error';
            }
        });
    }
    
    // ================================================================
    // START/STOP
    // ================================================================
    var updateInterval = null;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchStats();
        updateInterval = setInterval(fetchStats, CONFIG.updateInterval);
        console.log('🔄 Auto-update started (5s interval)');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
        console.log('⏹️ Auto-update stopped');
    }
    
    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = elements.refreshBtn;
        if (btn) {
            btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Loading...';
            btn.disabled = true;
        }
        
        fetchStats();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
        }, 1500);
    }
    
    // ================================================================
    // EXPOSE
    // ================================================================
    window.DoctorStats = {
        start: startAutoUpdate,
        stop: stopAutoUpdate,
        refresh: manualRefresh,
        fetch: fetchStats
    };
    
    // ================================================================
    // INIT
    // ================================================================
    function init() {
        if (state.initialized) return;
        findElements();
        startAutoUpdate();
        state.initialized = true;
        console.log('✅ Doctor Stats System initialized (visit_date fixed)');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
</script>

</body>
</html>