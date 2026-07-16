<?php
// ================================================================
// FILE: frontend/pages/doctor/dashboard.php
// DOCTOR DASHBOARD - FULL VERSION
// 8 CLICKABLE CARDS - SMART AUTO-UPDATE (3 SECONDS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    // Set default doctor - Dr. John Mushi (ID: 5)
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

// Make sure doctor_id and user_id are in sync
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $_SESSION['doctor_id'] = $_SESSION['user_id'];
}
if (isset($_SESSION['doctor_id']) && $_SESSION['doctor_id'] > 0) {
    $_SESSION['user_id'] = $_SESSION['doctor_id'];
}

$doctor_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// FUNCTIONS - Check if already defined to avoid duplication
// ================================================================
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        if (empty($timestamp)) return 'N/A';
        try {
            $time = strtotime($timestamp);
            if ($time === false) return 'N/A';
            $diff = time() - $time;
            if ($diff < 60) return 'Just now';
            if ($diff < 3600) return floor($diff / 60) . 'm ago';
            if ($diff < 86400) return floor($diff / 3600) . 'h ago';
            if ($diff < 604800) return floor($diff / 86400) . 'd ago';
            if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
            return date('M d, Y', $time);
        } catch (Exception $e) {
            return 'N/A';
        }
    }
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCTOR DATA FROM DATABASE (OVERRIDE SESSION IF NEEDED)
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, is_online, profile_pic FROM users WHERE id = ? AND role = 'doctor'");
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
        // If doctor not found, use Dr. John Mushi
        $doctor_id = 5;
        $doctor_name = 'Dr. John Mushi';
        $doctor_branch_id = 1;
        $doctor_specialty = 'General Medicine';
        $_SESSION['user_id'] = 5;
        $_SESSION['doctor_id'] = 5;
        $_SESSION['full_name'] = 'Dr. John Mushi';
        $_SESSION['is_online'] = 1;
    }
} catch (Exception $e) {
    // Use session values if database fails
    error_log("Dashboard database error: " . $e->getMessage());
    $doctor_id = $_SESSION['user_id'] ?? 5;
    $doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
    $doctor_branch_id = $_SESSION['branch_id'] ?? 1;
    $doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
}

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// GET INITIAL STATISTICS
// ================================================================

// 1. Today's Patients
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN status IN ('pending', 'assigned') THEN patient_id END) as pending,
        COUNT(DISTINCT CASE WHEN status = 'completed' THEN patient_id END) as completed
    FROM visits 
    WHERE doctor_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC);
$today_patients_pending = $today_patients['pending'] ?? 0;
$today_patients_completed = $today_patients['completed'] ?? 0;
$today_patients_total = $today_patients_pending + $today_patients_completed;

// 2. Today's Visits
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status IN ('pending', 'assigned') THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM visits 
    WHERE doctor_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC);
$today_visits_pending = $today_visits['pending'] ?? 0;
$today_visits_completed = $today_visits['completed'] ?? 0;
$today_visits_total = $today_visits_pending + $today_visits_completed;

// 3. Total Patients
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 4. Total Visits
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

// 9. Pending Visits Count
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? AND status IN ('pending', 'assigned')
");
$stmt->execute([$doctor_id]);
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

// 11. Pending Patients Queue
$stmt = $db->prepare("
    SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone,
           TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_time
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ? AND v.status IN ('pending', 'assigned')
    ORDER BY v.created_at ASC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
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

// 14. Get Doctor's Branch Name
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
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests_sidebar = $lab_tests_pending;
$pending_prescriptions_sidebar = 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT - REST OF THE FILE STAYS THE SAME -->
<!-- ================================================================ -->
<!-- ... (the rest of the HTML stays exactly as it was) ... -->

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
                                <i class="fas fa-sync-alt fa-spin"></i> Starting...
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
                <button onclick="manualRefresh()" class="btn-refresh" id="refreshBtn">
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

        <!-- CARD 2: Today's Visits -->
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
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       CLICKABLE CARDS
       ================================================================ */
    .card-clickable {
        text-decoration: none;
        color: inherit;
        display: block;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .card-clickable:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    }
    
    .card-clickable .stat-card-inner {
        pointer-events: none;
    }
    
    .card-clickable .stat-card-progress {
        pointer-events: none;
    }
    
    /* ================================================================
       UPDATE BADGE
       ================================================================ */
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
    
    .update-badge .fa-spin {
        animation: fa-spin 2s infinite linear;
    }
    
    @keyframes fa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
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
    
    .welcome-hero::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.03);
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
    
    .greeting-icon {
        font-size: 2.2rem;
        line-height: 1;
    }
    
    .welcome-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        margin: 0;
    }
    
    .welcome-title .doctor-name {
        color: #93C5FD;
    }
    
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
    
    .stats-grid-bottom {
        margin-bottom: 28px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px 22px;
        border: 1px solid #E2E8F0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.08);
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
        color: #94A3B8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1E293B;
        line-height: 1.2;
        transition: all 0.3s ease;
    }
    
    .stat-card-number.text-orange { color: #D97706; }
    
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
        color: #94A3B8;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-card-trend .fa-arrow-up { color: #059669; }
    .stat-card-trend .fa-clock { color: #D97706; }
    .stat-card-trend .fa-check-circle { color: #059669; }
    .stat-card-trend .fa-calendar-day { color: #0B5ED7; }
    .stat-card-trend .fa-prescription { color: #0B5ED7; }
    .stat-card-trend .fa-flask { color: #D97706; }
    .stat-card-trend .fa-chart-line { color: #059669; }
    
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
    
    .stat-card-badge.green {
        background: #059669;
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
    
    [data-theme="dark"] .stat-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card-number {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card-label {
        color: #94A3B8;
    }
    [data-theme="dark"] .stat-card-trend {
        color: #64748B;
    }
    [data-theme="dark"] .stat-card:hover {
        box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    }
    
    /* ================================================================
       DASHBOARD CARDS
       ================================================================ */
    .dashboard-card {
        background: white;
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid #E2E8F0;
        transition: all 0.3s ease;
    }
    
    .dashboard-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
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
        color: #1E293B;
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
        border-top: 1px solid #E2E8F0;
        text-align: center;
    }
    
    .card-link {
        color: #0B5ED7;
        font-size: 0.85rem;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }
    
    .card-link:hover {
        color: #0A4CA8;
        text-decoration: underline;
    }
    
    [data-theme="dark"] .dashboard-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .dashboard-card-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .dashboard-card-footer {
        border-color: #334155;
    }
    
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
       APPOINTMENTS
       ================================================================ */
    .appointments-list {
        max-height: 220px;
        overflow-y: auto;
    }
    
    .appointments-list::-webkit-scrollbar {
        width: 4px;
    }
    .appointments-list::-webkit-scrollbar-track {
        background: #F1F5F9;
        border-radius: 4px;
    }
    .appointments-list::-webkit-scrollbar-thumb {
        background: #0B5ED7;
        border-radius: 4px;
    }
    
    [data-theme="dark"] .appointments-list::-webkit-scrollbar-track {
        background: #0F172A;
    }
    
    .appointment-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid #F1F5F9;
        gap: 12px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .appointment-item:hover {
        background: #E8F0FE;
    }
    
    [data-theme="dark"] .appointment-item:hover {
        background: #1E3A5F;
    }
    [data-theme="dark"] .appointment-item {
        border-color: #334155;
    }
    
    .appointment-item:last-child {
        border-bottom: none;
    }
    
    .appointment-time {
        font-weight: 600;
        font-size: 0.8rem;
        color: #1E293B;
        min-width: 55px;
    }
    
    [data-theme="dark"] .appointment-time {
        color: #F1F5F9;
    }
    
    .appointment-patient {
        flex: 1;
    }
    
    .appointment-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: #1E293B;
    }
    
    [data-theme="dark"] .appointment-name {
        color: #F1F5F9;
    }
    
    .appointment-id {
        font-size: 0.65rem;
        color: #94A3B8;
    }
    
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
    
    [data-theme="dark"] .appointment-status.scheduled { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .appointment-status.confirmed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .appointment-status.pending { background: #3D2E0A; color: #FBBF24; }
    
    /* ================================================================
       QUEUE
       ================================================================ */
    .queue-card {
        border-color: #FDE68A;
    }
    
    [data-theme="dark"] .queue-card {
        border-color: #3D2E0A;
    }
    
    .queue-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .queue-list::-webkit-scrollbar {
        width: 4px;
    }
    .queue-list::-webkit-scrollbar-track {
        background: #F1F5F9;
        border-radius: 4px;
    }
    .queue-list::-webkit-scrollbar-thumb {
        background: #0B5ED7;
        border-radius: 4px;
    }
    
    [data-theme="dark"] .queue-list::-webkit-scrollbar-track {
        background: #0F172A;
    }
    
    .queue-item {
        display: flex;
        align-items: center;
        padding: 10px 14px;
        border-bottom: 1px solid #F1F5F9;
        gap: 14px;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .queue-item:hover {
        background: #F8FAFC;
    }
    
    [data-theme="dark"] .queue-item:hover {
        background: #0F172A;
    }
    [data-theme="dark"] .queue-item {
        border-color: #334155;
    }
    
    .queue-item-first {
        background: #E8F0FE;
        border-left: 4px solid #0B5ED7;
        border-radius: 8px 0 0 8px;
    }
    
    [data-theme="dark"] .queue-item-first {
        background: #1E3A5F;
    }
    
    .queue-number {
        font-weight: 700;
        font-size: 0.9rem;
        color: #94A3B8;
        min-width: 30px;
    }
    
    .queue-patient {
        flex: 1;
    }
    
    .queue-name {
        font-weight: 500;
        font-size: 0.9rem;
        color: #1E293B;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    [data-theme="dark"] .queue-name {
        color: #F1F5F9;
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
    
    .queue-details {
        font-size: 0.65rem;
        color: #94A3B8;
    }
    
    .queue-waiting {
        text-align: right;
        margin-left: auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
    }
    
    .queue-time {
        font-size: 0.7rem;
        color: #94A3B8;
    }
    
    .queue-time-long {
        color: #EF4444;
        font-weight: 600;
    }
    
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
    
    [data-theme="dark"] .queue-status.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .queue-status.assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .queue-status.with_doctor { background: #1A3A2A; color: #34D399; }
    
    .queue-action {
        flex-shrink: 0;
    }
    
    /* ================================================================
       ACTIVITIES
       ================================================================ */
    .activities-list {
        max-height: 260px;
        overflow-y: auto;
    }
    
    .activities-list::-webkit-scrollbar {
        width: 4px;
    }
    .activities-list::-webkit-scrollbar-track {
        background: #F1F5F9;
        border-radius: 4px;
    }
    .activities-list::-webkit-scrollbar-thumb {
        background: #0B5ED7;
        border-radius: 4px;
    }
    
    [data-theme="dark"] .activities-list::-webkit-scrollbar-track {
        background: #0F172A;
    }
    
    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 12px;
        border-bottom: 1px solid #F1F5F9;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .activity-item:hover {
        background: #F8FAFC;
    }
    
    [data-theme="dark"] .activity-item:hover {
        background: #0F172A;
    }
    [data-theme="dark"] .activity-item {
        border-color: #334155;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
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
    
    .activity-content {
        flex: 1;
        min-width: 0;
    }
    
    .activity-action {
        font-weight: 500;
        font-size: 0.85rem;
        color: #1E293B;
    }
    
    [data-theme="dark"] .activity-action {
        color: #F1F5F9;
    }
    
    .activity-patient {
        color: #94A3B8;
        font-weight: 400;
    }
    
    .activity-details {
        font-size: 0.7rem;
        color: #94A3B8;
    }
    
    .activity-time {
        color: #CBD5E1;
    }
    
    [data-theme="dark"] .activity-time {
        color: #64748B;
    }
    
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
        border: 2px solid #E2E8F0;
        background: white;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #1E293B;
        cursor: pointer;
        gap: 6px;
    }
    
    .quick-action:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.1);
    }
    
    .quick-action i {
        font-size: 1.3rem;
    }
    
    .quick-action span {
        font-size: 0.65rem;
        font-weight: 500;
        text-align: center;
    }
    
    .quick-action-blue i { color: #0B5ED7; }
    .quick-action-green i { color: #059669; }
    
    .quick-action-blue:hover { border-color: #0B5ED7; }
    .quick-action-green:hover { border-color: #059669; }
    
    [data-theme="dark"] .quick-action {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
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
        border: none;
        cursor: pointer;
    }
    
    .btn-consult:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
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
    
    .btn-consult-sm:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
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
    
    .btn-view-sm:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 20px 10px;
        color: #94A3B8;
    }
    
    .empty-state i {
        font-size: 2rem;
        color: #E2E8F0;
        display: block;
        margin-bottom: 6px;
    }
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    .empty-state-large {
        padding: 30px 10px;
    }
    
    .empty-state-large i {
        font-size: 3rem;
    }
    
    .text-green-500 { color: #059669; }
    .text-gray-400 { color: #94A3B8; }
    .text-xs { font-size: 0.75rem; }
    .text-blue-500 { color: #0B5ED7; }
    .text-orange { color: #D97706; }
    
    [data-theme="dark"] .empty-state i {
        color: #334155;
    }
    
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
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid #E2E8F0;
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: #94A3B8;
    }
    
    .footer .footer-brand {
        color: #0B5ED7;
        font-weight: 600;
    }
    
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    
    .text-gray-300 { color: #D1D5DB; }
    .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 1024px) {
        .quick-actions-grid {
            grid-template-columns: 1fr 1fr 1fr;
        }
        .welcome-hero {
            padding: 20px 24px;
        }
        .welcome-title {
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .quick-actions-grid {
            grid-template-columns: 1fr 1fr;
        }
        .welcome-hero-content {
            flex-direction: column;
            align-items: flex-start;
        }
        .welcome-hero-right {
            width: 100%;
        }
        .welcome-hero-right .btn-pulse,
        .welcome-hero-right .btn-refresh {
            flex: 1;
            justify-content: center;
        }
        .stat-card-number {
            font-size: 1.4rem;
        }
        .queue-item {
            flex-wrap: wrap;
            gap: 6px;
        }
        .queue-waiting {
            flex-direction: row;
            align-items: center;
            margin-left: 0;
            width: 100%;
            gap: 10px;
        }
        .queue-action {
            width: 100%;
        }
        .queue-action .btn-consult {
            width: 100%;
            justify-content: center;
        }
        .appointment-item {
            flex-wrap: wrap;
        }
        .appointment-time {
            min-width: 50px;
        }
        .activity-item {
            flex-wrap: wrap;
        }
        .activity-item .btn-consult-sm,
        .activity-item .btn-view-sm {
            width: 100%;
            justify-content: center;
        }
        .dashboard-card {
            padding: 14px 16px;
        }
        .chart-container {
            height: 160px;
        }
        .welcome-stats-mini {
            flex-wrap: wrap;
        }
        .welcome-greeting {
            flex-wrap: wrap;
        }
        .greeting-icon {
            font-size: 1.8rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .quick-actions-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card-number {
            font-size: 1.2rem;
        }
        .welcome-title {
            font-size: 1.1rem;
        }
        .welcome-hero {
            padding: 16px 18px;
        }
        .btn-pulse {
            font-size: 0.75rem;
            padding: 8px 14px;
        }
        .btn-refresh {
            font-size: 0.75rem;
            padding: 8px 14px;
        }
        .specialty-badge,
        .branch-badge,
        .date-badge {
            font-size: 0.7rem;
            padding: 2px 10px;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .stat-card, .dashboard-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .welcome-hero { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card-icon { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .chart-container { height: 120px !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT - WITH SMART AUTO-UPDATE (3 SECONDS) -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // ================================================================
    // CHART - INITIAL RENDER
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
                    backgroundColor: '#0B5ED7',
                    borderColor: '#0A4CA8',
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
    // HELPER FUNCTIONS
    // ================================================================
    function formatTime(datetime) {
        var d = new Date(datetime);
        return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function capitalize(text) {
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    // ================================================================
    // SMART AUTO-UPDATE - UPDATES ONLY WHEN DATA CHANGES
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    var updateCount = 0;
    
    function fetchAndUpdateStats() {
        if (isUpdating) return;
        isUpdating = true;
        
        updateCount++;
        
        // Update badge every 3 updates
        if (updateCount % 3 === 0) {
            document.getElementById('lastUpdateBadge').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Checking...';
        }
        
        fetch('get_stats.php')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    // Check if data has changed
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateDashboard(data.data);
                        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + data.data.timestamp;
                        
                        // Show subtle notification on change
                        if (updateCount > 1) {
                            showToast('🔄 Updated', 'Dashboard auto-updated at ' + data.data.timestamp, 'info');
                        }
                    }
                    
                    // Update badge
                    var now = new Date();
                    document.getElementById('lastUpdateBadge').innerHTML = 
                        '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + 
                        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error fetching stats:', error);
                document.getElementById('lastUpdateBadge').innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Connection error';
                isUpdating = false;
            });
    }
    
    function updateDashboard(data) {
        // ================================================================
        // CARD 1: Today's Patients
        // ================================================================
        document.getElementById('todayPatientsTotal').textContent = data.today_patients.total;
        document.getElementById('todayPatientsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.today_patients.pending + ' Pending';
        document.getElementById('todayPatientsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.today_patients.completed + ' Complete';
        var pct1 = data.today_patients.total > 0 ? Math.min(100, (data.today_patients.completed / Math.max(data.today_patients.total, 1)) * 100) : 0;
        document.getElementById('todayPatientsProgress').style.width = pct1 + '%';

        // ================================================================
        // CARD 2: Today's Visits
        // ================================================================
        document.getElementById('todayVisitsTotal').textContent = data.today_visits.total;
        document.getElementById('todayVisitsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.today_visits.pending + ' Pending';
        document.getElementById('todayVisitsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.today_visits.completed + ' Complete';
        var pct2 = data.today_visits.total > 0 ? Math.min(100, (data.today_visits.completed / Math.max(data.today_visits.total, 1)) * 100) : 0;
        document.getElementById('todayVisitsProgress').style.width = pct2 + '%';

        // ================================================================
        // CARD 3: Total Patients
        // ================================================================
        document.getElementById('totalPatients').textContent = Number(data.total_patients).toLocaleString();

        // ================================================================
        // CARD 4: Total Visits
        // ================================================================
        document.getElementById('totalVisits').textContent = Number(data.total_visits).toLocaleString();

        // ================================================================
        // CARD 5: Today's Appointments
        // ================================================================
        document.getElementById('todayAppointmentsTotal').textContent = data.today_appointments.total;
        document.getElementById('todayAppointmentsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.today_appointments.pending + ' Pending';
        document.getElementById('todayAppointmentsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.today_appointments.completed + ' Complete';
        var pct5 = data.today_appointments.total > 0 ? Math.min(100, (data.today_appointments.completed / Math.max(data.today_appointments.total, 1)) * 100) : 0;
        document.getElementById('todayAppointmentsProgress').style.width = pct5 + '%';

        // ================================================================
        // CARD 6: Total Appointments
        // ================================================================
        document.getElementById('totalAppointments').textContent = Number(data.total_appointments).toLocaleString();

        // ================================================================
        // CARD 7: Prescriptions
        // ================================================================
        document.getElementById('totalPrescriptions').textContent = Number(data.total_prescriptions).toLocaleString();

        // ================================================================
        // CARD 8: Lab Tests
        // ================================================================
        document.getElementById('labTestsTotal').textContent = Number(data.lab_tests.total).toLocaleString();
        document.getElementById('labTestsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.lab_tests.pending + ' Pending';
        document.getElementById('labTestsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.lab_tests.completed + ' Complete';
        var pct8 = data.lab_tests.total > 0 ? Math.min(100, (data.lab_tests.completed / Math.max(data.lab_tests.total, 1)) * 100) : 0;
        document.getElementById('labTestsProgress').style.width = pct8 + '%';
        
        // Lab badge
        var badge = document.getElementById('labTestsBadge');
        if (data.lab_tests.pending > 0) {
            if (!badge) {
                var card = document.getElementById('cardLabTests');
                var rightDiv = card.querySelector('.stat-card-right');
                if (!rightDiv) {
                    rightDiv = document.createElement('div');
                    rightDiv.className = 'stat-card-right';
                    card.querySelector('.stat-card-inner').appendChild(rightDiv);
                }
                rightDiv.innerHTML = '<span class="stat-card-badge danger" id="labTestsBadge">' + data.lab_tests.pending + '</span>';
            } else {
                badge.textContent = data.lab_tests.pending;
                badge.style.display = 'inline-block';
            }
            document.getElementById('cardLabTests').classList.add('has-badge');
        } else {
            if (badge) badge.style.display = 'none';
            document.getElementById('cardLabTests').classList.remove('has-badge');
        }

        // ================================================================
        // UPDATE WELCOME MINI STATS
        // ================================================================
        document.getElementById('miniAppointments').textContent = data.today_appointments.total;
        document.getElementById('miniPending').textContent = data.today_patients.pending;
        document.getElementById('miniTotalPatients').textContent = data.total_patients;

        // ================================================================
        // UPDATE APPOINTMENTS COUNT
        // ================================================================
        document.getElementById('appointmentsCount').textContent = '(' + data.today_appointments.total + ')';

        // ================================================================
        // UPDATE PULSE BUTTON
        // ================================================================
        var btnPulse = document.getElementById('btnPulse');
        var btnPulseText = document.getElementById('btnPulseText');
        if (data.today_patients.pending > 0) {
            btnPulse.style.display = 'inline-flex';
            btnPulseText.textContent = data.today_patients.pending + ' Patient(s) Waiting';
        } else {
            btnPulse.style.display = 'none';
        }

        // ================================================================
        // UPDATE QUEUE COUNT
        // ================================================================
        document.getElementById('queueCount').textContent = '(' + data.pending_visits + ' waiting)';

        // ================================================================
        // UPDATE APPOINTMENTS LIST
        // ================================================================
        var appointmentsList = document.getElementById('appointmentsList');
        if (data.today_appointments.list) {
            var currentItems = appointmentsList.querySelectorAll('.appointment-item');
            var newItems = data.today_appointments.list;
            
            if (currentItems.length !== newItems.length || newItems.length > 0) {
                var listHtml = '';
                if (newItems.length > 0) {
                    newItems.forEach(function(appt) {
                        var statusClass = appt.status || 'scheduled';
                        listHtml += `
                            <div class="appointment-item">
                                <div class="appointment-time">${formatTime(appt.appointment_date)}</div>
                                <div class="appointment-patient">
                                    <div class="appointment-name">${escapeHtml(appt.patient_name)}</div>
                                    <div class="appointment-id">${escapeHtml(appt.patient_id)}</div>
                                </div>
                                <span class="appointment-status ${statusClass}">
                                    ${capitalize(statusClass)}
                                </span>
                            </div>
                        `;
                    });
                } else {
                    listHtml = `
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for today</p>
                        </div>
                    `;
                }
                appointmentsList.innerHTML = listHtml;
            }
        }

        // ================================================================
        // UPDATE QUEUE LIST
        // ================================================================
        var queueList = document.getElementById('queueList');
        if (data.pending_patients) {
            var currentQueueItems = queueList.querySelectorAll('.queue-item');
            var newQueueItems = data.pending_patients;
            
            if (currentQueueItems.length !== newQueueItems.length) {
                var queueHtml = '';
                if (newQueueItems.length > 0) {
                    newQueueItems.forEach(function(patient, index) {
                        var isFirst = index === 0 ? 'queue-item-first' : '';
                        var waitingTime = patient.waiting_time > 0 ? patient.waiting_time + ' min' : 'Just now';
                        var isLong = patient.waiting_time > 30 ? 'queue-time-long' : '';
                        queueHtml += `
                            <div class="queue-item ${isFirst}">
                                <div class="queue-number">#${index + 1}</div>
                                <div class="queue-patient">
                                    <div class="queue-name">
                                        ${escapeHtml(patient.patient_name)}
                                        ${index === 0 ? '<span class="queue-badge">Next</span>' : ''}
                                    </div>
                                    <div class="queue-details">
                                        ${escapeHtml(patient.patient_id || 'N/A')} • ${escapeHtml(patient.phone || '')}
                                    </div>
                                </div>
                                <div class="queue-waiting">
                                    <span class="queue-time ${isLong}">${waitingTime}</span>
                                    <span class="queue-status ${patient.status || 'pending'}">${capitalize(patient.status || 'Pending')}</span>
                                </div>
                                <div class="queue-action">
                                    <a href="consultation.php?visit_id=${patient.id}" class="btn-consult">
                                        <i class="fas fa-stethoscope"></i> Consult
                                    </a>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    queueHtml = `
                        <div class="empty-state empty-state-large">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <p class="text-gray-400">No patients waiting! All clear.</p>
                            <p class="text-xs text-gray-400">Take a break or review completed cases</p>
                        </div>
                    `;
                }
                queueList.innerHTML = queueHtml;
            }
        }

        // ================================================================
        // UPDATE RECENT ACTIVITIES
        // ================================================================
        // For simplicity, we don't auto-update activities to keep performance
        // This would require additional API call if needed
    }

    // ================================================================
    // START AUTO-UPDATE - EVERY 3 SECONDS
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        // Update every 3 seconds
        updateInterval = setInterval(fetchAndUpdateStats, 3000);
        document.getElementById('lastUpdateBadge').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Live mode active';
        fetchAndUpdateStats();
    }

    // ================================================================
    // STOP AUTO-UPDATE
    // ================================================================
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            document.getElementById('lastUpdateBadge').innerHTML = '<i class="fas fa-pause"></i> Paused';
        }
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Loading...';
        btn.disabled = true;
        
        // Force reset hash to force update
        lastHash = null;
        fetchAndUpdateStats();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
        }, 1000);
    }

    // ================================================================
    // INITIALIZE DASHBOARD
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Render chart
        renderChart(chartLabels, chartValues);
        
        // Start auto-update after 2 seconds
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    // ================================================================
    // NOTIFICATIONS
    // ================================================================
    <?php if ($today_patients_pending > 0): ?>
    setTimeout(function() {
        showToast('👋 Patients Waiting', 
            'You have <?= $today_patients_pending ?> patient(s) waiting for consultation', 
            'info'
        );
    }, 1000);
    <?php endif; ?>

    <?php if ($today_appointments_completed > 0): ?>
    setTimeout(function() {
        showToast('✅ Appointments Completed', 
            'You have completed <?= $today_appointments_completed ?> appointment(s) today', 
            'success'
        );
    }, 2000);
    <?php endif; ?>

    console.log('%c👨‍⚕️ Doctor Dashboard - <?= htmlspecialchars($doctor_name) ?>', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Today Patients: <?= $today_patients_total ?> (Pending: <?= $today_patients_pending ?>, Complete: <?= $today_patients_completed ?>)', 'font-size:12px; color:#0B5ED7;');
    console.log('%c📅 Today Visits: <?= $today_visits_total ?> (Pending: <?= $today_visits_pending ?>, Complete: <?= $today_visits_completed ?>)', 'font-size:12px; color:#059669;');
    console.log('%c📋 Today Appointments: <?= $today_appointments_total ?> (Pending: <?= $today_appointments_pending ?>, Complete: <?= $today_appointments_completed ?>)', 'font-size:12px; color:#D97706;');
    console.log('%c🧪 Lab Tests: <?= $lab_tests_total ?> (Pending: <?= $lab_tests_pending ?>, Complete: <?= $lab_tests_completed ?>)', 'font-size:12px; color:#7C3AED;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Smart - only when data changes)', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Click any card to navigate to related page', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>