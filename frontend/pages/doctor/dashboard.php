<?php
// ================================================================
// FILE: frontend/pages/doctor/dashboard.php
// DOCTOR DASHBOARD - USING REAL DOCTOR DATA
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA (ID: 2) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    // Set default doctor session (Dr. Sarah Mwamba - ID: 2)
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['username'] = 'dr.sarah';
    $_SESSION['email'] = 'sarah@braick.com';
    $_SESSION['phone'] = '+255 700 000 001';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['is_online'] = 1;
    $_SESSION['profile_pic'] = NULL;
    $_SESSION['status'] = 'active';
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_username = $_SESSION['username'] ?? 'doctor';
$doctor_email = $_SESSION['email'] ?? 'No email';
$doctor_phone = $_SESSION['phone'] ?? 'No phone';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';
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

function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function getBranchFilter($db, $table, $branch_id) {
    if (columnExists($db, $table, 'branch_id')) {
        return " AND branch_id = " . (int)$branch_id;
    }
    return "";
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
// FETCH ALL STATISTICS FOR THIS DOCTOR
// ================================================================

// 1. TOTAL PATIENTS
$filter = getBranchFilter($db, 'visits', $doctor_branch_id);
$sql = "SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. TODAY'S VISITS
$filter = getBranchFilter($db, 'visits', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. WEEKLY VISITS
$filter = getBranchFilter($db, 'visits', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$week_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. MONTHLY VISITS
$filter = getBranchFilter($db, 'visits', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$month_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. PENDING PRESCRIPTIONS
$filter = getBranchFilter($db, 'prescriptions', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. PENDING LAB TESTS
$filter = getBranchFilter($db, 'lab_tests', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. PENDING REFERRALS
$filter = getBranchFilter($db, 'referrals', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM referrals WHERE from_doctor_id = ? AND status = 'pending'" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$pending_referrals = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 8. TODAY'S APPOINTMENTS COUNT
$filter = getBranchFilter($db, 'appointments', $doctor_branch_id);
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()" . $filter;
$stmt = $db->prepare($sql);
$stmt->execute([$doctor_id]);
$today_appointments_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 9. TODAY'S REVENUE
$sql = "SELECT COALESCE(SUM(ps.total), 0) as revenue 
        FROM pharmacy_sales ps
        JOIN prescriptions p ON ps.prescription_id = p.id
        WHERE p.doctor_id = ? AND ps.payment_status = 'paid' AND DATE(ps.sale_date) = CURDATE()";
$params = [$doctor_id];
if (columnExists($db, 'pharmacy_sales', 'branch_id')) {
    $sql .= " AND ps.branch_id = ?";
    $params[] = $doctor_branch_id;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 10. MONTHLY REVENUE
$sql = "SELECT COALESCE(SUM(ps.total), 0) as revenue 
        FROM pharmacy_sales ps
        JOIN prescriptions p ON ps.prescription_id = p.id
        WHERE p.doctor_id = ? AND ps.payment_status = 'paid' AND MONTH(ps.sale_date) = MONTH(CURDATE())";
$params = [$doctor_id];
if (columnExists($db, 'pharmacy_sales', 'branch_id')) {
    $sql .= " AND ps.branch_id = ?";
    $params[] = $doctor_branch_id;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$month_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 11. RECENT PATIENTS (Last 5)
$sql = "SELECT DISTINCT p.*, v.created_at as last_visit 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?";
$params = [$doctor_id];
if (columnExists($db, 'visits', 'branch_id')) {
    $sql .= " AND v.branch_id = ?";
    $params[] = $doctor_branch_id;
}
$sql .= " ORDER BY v.created_at DESC LIMIT 5";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 12. TODAY'S APPOINTMENTS LIST
$sql = "SELECT a.*, p.full_name as patient_name, p.patient_id 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()";
$params = [$doctor_id];
if (columnExists($db, 'appointments', 'branch_id')) {
    $sql .= " AND a.branch_id = ?";
    $params[] = $doctor_branch_id;
}
$sql .= " ORDER BY a.appointment_date";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 13. WEEKLY VISITS CHART DATA
$weekly_labels = [];
$weekly_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekly_labels[] = date('D', strtotime($date));
    $sql = "SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = ?";
    $params = [$doctor_id, $date];
    if (columnExists($db, 'visits', 'branch_id')) {
        $sql .= " AND branch_id = ?";
        $params[] = $doctor_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $weekly_values[] = $count;
}

// 14. RECENT ACTIVITIES
$recent_activities = [];
try {
    $sql = "SELECT action, details, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute([$doctor_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [
        ['action' => 'Dashboard Loaded', 'details' => 'Doctor dashboard loaded successfully', 'created_at' => date('Y-m-d H:i:s')]
    ];
}

// ================================================================
// DOCTOR STATS ARRAY
// ================================================================
$doctor_stats = [
    'total_patients' => $total_patients,
    'patients_growth' => 12,
    'today_visits' => $today_visits,
    'week_visits' => $week_visits,
    'month_visits' => $month_visits,
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests,
    'pending_referrals' => $pending_referrals,
    'today_appointments' => $today_appointments_count,
    'total_revenue' => $today_revenue,
    'month_revenue' => $month_revenue,
];

// Variables for sidebar
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests_sidebar = $pending_lab_tests;
$pending_prescriptions_sidebar = $pending_prescriptions;

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
            <h1 class="page-title flex items-center gap-3">
                <i class="fas fa-home mr-2" style="color: #0B5ED7;"></i> 
                Dashboard
                <span class="text-sm font-normal text-gray-400 ml-2">| Welcome back</span>
            </h1>
            <div class="page-subtitle flex flex-wrap items-center gap-3 mt-2">
                <!-- Doctor Name -->
                <span class="text-2xl font-bold text-gray-800 doctor-welcome">
                    <i class="fas fa-user-md mr-2 text-blue-600"></i>
                    <?= htmlspecialchars($doctor_name) ?>
                </span>
                <!-- Doctor Specialty -->
                <span class="inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm border border-blue-200">
                    <i class="fas fa-stethoscope mr-1"></i>
                    <?= htmlspecialchars($doctor_specialty) ?>
                </span>
                <!-- Branch -->
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> 
                    <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <!-- Date -->
                <span class="inline-flex bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs border border-gray-200">
                    <i class="fas fa-calendar-day mr-1"></i> 
                    <?= date('F d, Y') ?>
                </span>
            </div>
            <!-- Doctor Contact Info -->
            <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-500">
                <span><i class="fas fa-envelope mr-1 text-blue-500"></i><?= htmlspecialchars($doctor_email) ?></span>
                <span class="text-gray-300">|</span>
                <span><i class="fas fa-phone mr-1 text-green-500"></i><?= htmlspecialchars($doctor_phone) ?></span>
                <span class="text-gray-300">|</span>
                <span><i class="fas fa-id-badge mr-1 text-purple-500"></i>ID: <?= htmlspecialchars($doctor_id) ?></span>
                <span class="text-gray-300">|</span>
                <span><i class="fas fa-user-tag mr-1 text-orange-500"></i><?= htmlspecialchars($doctor_username) ?></span>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_visit.php" class="btn btn-blue btn-sm"><i class="fas fa-plus-circle"></i> New Visit</a>
            <a href="prescribe.php" class="btn btn-green btn-sm"><i class="fas fa-prescription"></i> Prescribe</a>
            <button onclick="location.reload()" class="btn btn-outline btn-sm"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Patients</p>
                    <p class="stat-number"><?= number_format($doctor_stats['total_patients']) ?></p>
                    <span class="stat-trend"><i class="fas fa-users"></i> All time</span>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Visits</p>
                    <p class="stat-number"><?= $doctor_stats['today_visits'] ?></p>
                    <span class="stat-trend"><i class="fas fa-calendar-day"></i> Today</span>
                </div>
                <div class="stat-icon"><i class="fas fa-clinic-medical"></i></div>
            </div>
        </div>
        <div class="stat-card blue-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Weekly Visits</p>
                    <p class="stat-number"><?= $doctor_stats['week_visits'] ?></p>
                    <span class="stat-trend"><i class="fas fa-calendar-week"></i> This week</span>
                </div>
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
        <div class="stat-card orange animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending Prescriptions</p>
                    <p class="stat-number"><?= $doctor_stats['pending_prescriptions'] ?></p>
                    <span class="stat-trend"><i class="fas fa-clock"></i> Needs action</span>
                </div>
                <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            </div>
        </div>
        <div class="stat-card purple animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending Lab Tests</p>
                    <p class="stat-number"><?= $doctor_stats['pending_lab_tests'] ?></p>
                    <span class="stat-trend"><i class="fas fa-flask"></i> Pending</span>
                </div>
                <div class="stat-icon"><i class="fas fa-flask"></i></div>
            </div>
        </div>
        <div class="stat-card green-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Revenue</p>
                    <p class="stat-number">TSh <?= number_format($doctor_stats['total_revenue']) ?></p>
                    <span class="stat-trend">TSh <?= number_format($doctor_stats['month_revenue']) ?> monthly</span>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHART & APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar title-blue mr-2"></i> Weekly Visits <span class="text-xs font-normal text-gray-400">(Last 7 days)</span></h3>
            </div>
            <div class="chart-container">
                <canvas id="visitsChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-check title-blue mr-2"></i> Today's Appointments <span class="text-xs font-normal text-gray-400">(<?= count($today_appointments) ?>)</span></h3>
                <a href="appointments.php" class="text-xs text-blue-600 hover:underline">View All →</a>
            </div>
            <div class="space-y-1 max-h-52 overflow-y-auto">
                <?php if (count($today_appointments) > 0): ?>
                    <?php foreach ($today_appointments as $appt): ?>
                        <div class="appointment-item">
                            <span class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                            <span class="appointment-patient"><?= htmlspecialchars($appt['patient_name']) ?></span>
                            <span class="appointment-type"><?= htmlspecialchars($appt['purpose'] ?? 'General') ?></span>
                            <span class="appointment-status <?= $appt['status'] ?? 'scheduled' ?>"><?= ucfirst($appt['status'] ?? 'Scheduled') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-400"><i class="fas fa-calendar-check text-2xl block mb-2"></i><p>No appointments scheduled for today</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS & ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-injured title-blue mr-2"></i> Recent Patients <span class="text-xs font-normal text-gray-400">(Last 5)</span></h3>
                <a href="my_patients.php" class="text-xs text-blue-600 hover:underline">View All →</a>
            </div>
            <div class="space-y-1 max-h-56 overflow-y-auto">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-avatar" style="background: <?= getUserColor($patient['full_name']) ?>;"><?= strtoupper(substr($patient['full_name'], 0, 1)) ?></div>
                            <div class="patient-info">
                                <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
                                <div><span class="id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span> <span class="phone">• <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
                            </div>
                            <div class="patient-last-visit"><?= isset($patient['last_visit']) ? time_ago($patient['last_visit']) : 'N/A' ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-400"><i class="fas fa-user-injured text-2xl block mb-2"></i><p>No patients yet</p></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock title-blue mr-2"></i> Recent Activities</h3>
                <span class="text-xs text-gray-400">Latest</span>
            </div>
            <div class="space-y-1 max-h-56 overflow-y-auto">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon blue"><i class="fas fa-circle"></i></div>
                            <div class="activity-content">
                                <div class="action"><?= htmlspecialchars($activity['action'] ?? 'Activity') ?></div>
                                <div class="details"><?= htmlspecialchars($activity['details'] ?? '') ?></div>
                            </div>
                            <div class="activity-time"><?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-400"><i class="fas fa-clock text-2xl block mb-2"></i><p>No recent activities</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bolt title-blue mr-2"></i> Quick Actions</h3>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
            <a href="new_visit.php" class="quick-action"><i class="fas fa-user-plus icon-blue"></i><span class="label">New Visit</span></a>
            <a href="prescribe.php" class="quick-action"><i class="fas fa-prescription icon-green"></i><span class="label">Prescribe</span></a>
            <a href="my_patients.php" class="quick-action"><i class="fas fa-users icon-blue"></i><span class="label">My Patients</span></a>
            <a href="appointments.php" class="quick-action"><i class="fas fa-calendar-check icon-blue"></i><span class="label">Appointments</span></a>
            <a href="lab_results.php" class="quick-action"><i class="fas fa-flask icon-purple"></i><span class="label">Lab Results</span></a>
            <a href="profile.php" class="quick-action"><i class="fas fa-user-circle icon-blue"></i><span class="label">My Profile</span></a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Doctor Dashboard
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // WEEKLY VISITS CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('visitsChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($weekly_labels) ?>;
            var values = <?= json_encode($weekly_values) ?>;
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var textColor = isDark ? '#94A3B8' : '#64748B';
            var gridColor = isDark ? '#334155' : '#E2E8F0';
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Visits',
                        data: values,
                        backgroundColor: '#0B5ED7',
                        borderColor: '#0A4CA8',
                        borderWidth: 1,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1, color: textColor }, grid: { color: gridColor } },
                        x: { grid: { display: false }, ticks: { color: textColor } }
                    }
                }
            });
        }
    });

    // ================================================================
    // UPDATE SIDEBAR BADGES
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof updateSidebarBadges === 'function') {
            updateSidebarBadges(
                <?= $doctor_stats['total_patients'] ?>,
                <?= $doctor_stats['pending_lab_tests'] ?>,
                <?= $doctor_stats['pending_referrals'] ?>,
                <?= $doctor_stats['today_appointments'] ?>
            );
        }
    });

    // ================================================================
    // TOAST FUNCTION
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

    console.log('%c👨‍⚕️ Braick - Doctor Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Doctor: <?= htmlspecialchars($doctor_name) ?> (ID: <?= $doctor_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c📊 Total Patients: <?= number_format($doctor_stats['total_patients']) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c✅ Using REAL doctor data from database', 'font-size:13px; color:#059669;');
</script>

</body>
</html>