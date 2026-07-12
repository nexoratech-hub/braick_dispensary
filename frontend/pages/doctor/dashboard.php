<?php
// ================================================================
// FILE: frontend/pages/doctor/dashboard.php
// DOCTOR - FULL DASHBOARD (FIXED)
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
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';

// ================================================================
// FUNCTIONS - ADDED time_ago()
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
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCTOR STATISTICS
// ================================================================

// 1. Total Patients
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as total 
    FROM visits 
    WHERE doctor_id = ?
");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 2. Pending Visits
$stmt = $db->prepare("
    SELECT COUNT(*) as pending 
    FROM visits 
    WHERE doctor_id = ? AND status IN ('pending', 'assigned')
");
$stmt->execute([$doctor_id]);
$pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;

// 3. Today's Visits
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as today 
    FROM visits 
    WHERE doctor_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['today'] ?? 0;

// 4. Completed Visits
$stmt = $db->prepare("
    SELECT COUNT(*) as completed 
    FROM visits 
    WHERE doctor_id = ? AND status = 'completed'
");
$stmt->execute([$doctor_id]);
$completed_visits = $stmt->fetch(PDO::FETCH_ASSOC)['completed'] ?? 0;

// 5. Total Prescriptions
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM prescriptions 
    WHERE doctor_id = ?
");
$stmt->execute([$doctor_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 6. Pending Lab Tests
$stmt = $db->prepare("
    SELECT COUNT(*) as pending 
    FROM lab_tests 
    WHERE doctor_id = ? AND status IN ('pending', 'in_progress')
");
$stmt->execute([$doctor_id]);
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;

// 7. Today's Appointments
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.patient_id 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ?
    ORDER BY a.appointment_date ASC
");
$stmt->execute([$doctor_id, $today]);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Pending Patients (Queue)
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

// 9. Recent Activities
$stmt = $db->prepare("
    (SELECT 'visit' as type, v.id, v.created_at, p.full_name as patient_name, 
            v.status, 'visit' as action_type
     FROM visits v
     JOIN patients p ON v.patient_id = p.id
     WHERE v.doctor_id = ?
     ORDER BY v.created_at DESC
     LIMIT 5)
    UNION ALL
    (SELECT 'prescription' as type, pr.id, pr.created_at, p.full_name as patient_name,
            pr.status, 'prescription' as action_type
     FROM prescriptions pr
     JOIN patients p ON pr.patient_id = p.id
     WHERE pr.doctor_id = ?
     ORDER BY pr.created_at DESC
     LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->execute([$doctor_id, $doctor_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. Weekly Visits Data for Chart
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$doctor_id]);
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart labels and values
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
    if (!$found) {
        $chart_values[] = 0;
    }
}

// 11. New Patients Today
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as new_patients
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ? AND DATE(v.created_at) = ?
");
$stmt->execute([$doctor_id, $today]);
$new_patients_today = $stmt->fetch(PDO::FETCH_ASSOC)['new_patients'] ?? 0;

// 12. Get Doctor's Branch Name
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

// 13. New Patients Count (for notification)
$stmt = $db->prepare("
    SELECT COUNT(*) as new_count 
    FROM visits 
    WHERE doctor_id = ? AND status = 'pending' 
    AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute([$doctor_id]);
$new_patients_count = $stmt->fetch(PDO::FETCH_ASSOC)['new_count'] ?? 0;

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests_sidebar = $pending_lab_tests;
$pending_prescriptions_sidebar = 0;

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

    <!-- Welcome Section -->
    <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-home text-blue-600"></i>
                Welcome back, <?= htmlspecialchars($doctor_name) ?>!
            </h1>
            <p class="text-gray-500 text-sm">
                <i class="fas fa-stethoscope mr-1"></i>
                <?= htmlspecialchars($doctor_specialty) ?> • 
                <span class="bg-blue-600 text-white px-3 py-0.5 rounded-full text-xs">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 text-gray-400 text-xs">
                    <i class="far fa-calendar-alt mr-1"></i>
                    <?= date('l, F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php if ($pending_visits > 0): ?>
                <a href="#queue" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-orange-600 transition flex items-center gap-2 animate-pulse">
                    <i class="fas fa-user-clock"></i>
                    <?= $pending_visits ?> Patient(s) Waiting
                </a>
            <?php endif; ?>
            <button onclick="window.location.reload()" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-2">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        
        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-600 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Total Patients</p>
                    <p class="text-2xl font-bold text-blue-600"><?= number_format($total_patients) ?></p>
                    <span class="text-xs text-green-600"><i class="fas fa-user-plus"></i> +<?= $new_patients_today ?> today</span>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 <?= $pending_visits > 0 ? 'border-orange-500' : 'border-gray-300' ?> hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending Patients</p>
                    <p class="text-2xl font-bold <?= $pending_visits > 0 ? 'text-orange-500' : 'text-gray-400' ?>">
                        <?= number_format($pending_visits) ?>
                    </p>
                    <span class="text-xs text-gray-400">
                        <?php if ($pending_visits > 0): ?>
                            <i class="fas fa-clock text-orange-500"></i> Waiting for you
                        <?php else: ?>
                            <i class="fas fa-check-circle text-green-500"></i> All clear
                        <?php endif; ?>
                    </span>
                </div>
                <div class="w-12 h-12 <?= $pending_visits > 0 ? 'bg-orange-100 text-orange-500' : 'bg-gray-100 text-gray-400' ?> rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
            <?php if ($pending_visits > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pending_visits ?></span>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-600 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Today's Visits</p>
                    <p class="text-2xl font-bold text-green-600"><?= number_format($today_visits) ?></p>
                    <span class="text-xs text-gray-400"><i class="fas fa-calendar-day"></i> <?= date('M d, Y') ?></span>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-600">
                    <i class="fas fa-clinic-medical text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-600 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Completed</p>
                    <p class="text-2xl font-bold text-purple-600"><?= number_format($completed_visits) ?></p>
                    <span class="text-xs text-gray-400"><i class="fas fa-check-circle"></i> Total completed</span>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center text-purple-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        
        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-teal-600 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Prescriptions</p>
                    <p class="text-2xl font-bold text-teal-600"><?= number_format($total_prescriptions) ?></p>
                    <span class="text-xs text-gray-400"><i class="fas fa-prescription"></i> Total issued</span>
                </div>
                <div class="w-12 h-12 bg-teal-100 rounded-full flex items-center justify-center text-teal-600">
                    <i class="fas fa-prescription text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 <?= $pending_lab_tests > 0 ? 'border-yellow-500' : 'border-gray-300' ?> hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending Lab Tests</p>
                    <p class="text-2xl font-bold <?= $pending_lab_tests > 0 ? 'text-yellow-500' : 'text-gray-400' ?>">
                        <?= number_format($pending_lab_tests) ?>
                    </p>
                    <span class="text-xs text-gray-400">
                        <?php if ($pending_lab_tests > 0): ?>
                            <i class="fas fa-flask text-yellow-500"></i> Waiting for results
                        <?php else: ?>
                            <i class="fas fa-check-circle text-green-500"></i> No pending
                        <?php endif; ?>
                    </span>
                </div>
                <div class="w-12 h-12 <?= $pending_lab_tests > 0 ? 'bg-yellow-100 text-yellow-500' : 'bg-gray-100 text-gray-400' ?> rounded-full flex items-center justify-center">
                    <i class="fas fa-flask text-xl"></i>
                </div>
            </div>
            <?php if ($pending_lab_tests > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pending_lab_tests ?></span>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-indigo-600 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Appointments Today</p>
                    <p class="text-2xl font-bold text-indigo-600"><?= number_format(count($today_appointments)) ?></p>
                    <span class="text-xs text-gray-400"><i class="fas fa-calendar-check"></i> Scheduled</span>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600">
                    <i class="fas fa-calendar-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-rose-600 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Completion Rate</p>
                    <p class="text-2xl font-bold text-rose-600">
                        <?php 
                            $total = $total_patients > 0 ? $total_patients : 1;
                            $rate = round(($completed_visits / $total) * 100);
                            echo $rate . '%';
                        ?>
                    </p>
                    <span class="text-xs text-gray-400"><i class="fas fa-chart-line"></i> Overall performance</span>
                </div>
                <div class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center text-rose-600">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHART & APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        <!-- Weekly Visits Chart -->
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200 lg:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-800 font-semibold flex items-center gap-2">
                    <i class="fas fa-chart-area text-blue-600"></i>
                    Weekly Visits Trend
                </h3>
                <span class="text-xs text-gray-400">Last 7 days</span>
            </div>
            <div class="h-48">
                <canvas id="visitsChart"></canvas>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-800 font-semibold flex items-center gap-2">
                    <i class="fas fa-calendar-check text-green-600"></i>
                    Today's Appointments
                    <span class="text-sm font-normal text-gray-400">(<?= count($today_appointments) ?>)</span>
                </h3>
            </div>
            <div class="max-h-52 overflow-y-auto">
                <?php if (count($today_appointments) > 0): ?>
                    <?php foreach ($today_appointments as $appt): ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <span class="font-semibold text-sm text-gray-800"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                                <span class="text-sm text-gray-600 ml-2"><?= htmlspecialchars($appt['patient_name']) ?></span>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full <?= ($appt['status'] ?? 'scheduled') === 'confirmed' ? 'bg-green-100 text-green-600' : (($appt['status'] ?? 'scheduled') === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600') ?>">
                                <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-calendar-check text-2xl block mb-2"></i>
                        <p>No appointments scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3 pt-3 border-t text-center">
                <a href="appointments.php" class="text-blue-600 text-sm hover:underline">View all appointments →</a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PENDING PATIENTS QUEUE -->
    <!-- ================================================================ -->
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200 mb-6" id="queue">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-gray-800 font-semibold flex items-center gap-2">
                <i class="fas fa-user-clock text-orange-500"></i>
                Pending Patients Queue
                <span class="text-sm font-normal text-gray-400">(<?= count($pending_patients) ?> waiting)</span>
            </h3>
            <span class="text-xs text-gray-400">
                <i class="far fa-clock mr-1"></i> Waiting time
            </span>
        </div>
        
        <?php if (count($pending_patients) > 0): ?>
            <div class="max-h-72 overflow-y-auto">
                <?php foreach ($pending_patients as $index => $patient): ?>
                    <div class="flex items-center gap-4 py-3 border-b border-gray-100 last:border-0 <?= $index === 0 ? 'bg-blue-50 rounded-lg px-3 -mx-3' : '' ?>">
                        <div class="font-bold text-gray-400 w-8">#<?= $index + 1 ?></div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                                <?php if ($index === 0): ?>
                                    <span class="text-xs bg-orange-500 text-white px-2 py-0.5 rounded-full ml-2">Next</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?> • 
                                <?= htmlspecialchars($patient['phone'] ?? '') ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm <?= ($patient['waiting_time'] ?? 0) > 30 ? 'text-red-500 font-semibold' : 'text-gray-500' ?>">
                                <?= ($patient['waiting_time'] ?? 0) > 0 ? ($patient['waiting_time'] . ' min') : 'Just now' ?>
                            </span>
                            <div>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= ($patient['status'] ?? 'pending') === 'pending' ? 'bg-yellow-100 text-yellow-600' : 'bg-blue-100 text-blue-600' ?>">
                                    <?= ucfirst($patient['status'] ?? 'Pending') ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <a href="consultation.php?visit_id=<?= $patient['id'] ?>" class="bg-purple-600 text-white px-3 py-1 rounded text-xs hover:bg-purple-700 transition flex items-center gap-1">
                                <i class="fas fa-stethoscope"></i> Consult
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($pending_patients) >= 10): ?>
                <div class="mt-3 pt-3 border-t text-center">
                    <a href="my_patients.php?filter=pending" class="text-blue-600 text-sm hover:underline">
                        View all waiting patients →
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-check-circle text-4xl text-green-500 block mb-3"></i>
                <p>No patients waiting! All clear.</p>
                <p class="text-xs text-gray-400 mt-1">Take a break or review completed cases</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES & QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        <!-- Recent Activities -->
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200 lg:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-800 font-semibold flex items-center gap-2">
                    <i class="fas fa-clock text-blue-600"></i>
                    Recent Activities
                </h3>
                <a href="system_logs.php" class="text-blue-600 text-sm hover:underline">View All</a>
            </div>
            <div class="max-h-60 overflow-y-auto">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm <?= $activity['action_type'] === 'prescription' ? 'bg-green-500' : 'bg-blue-500' ?>">
                                <i class="fas <?= $activity['action_type'] === 'prescription' ? 'fa-prescription' : 'fa-user-injured' ?>"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-800">
                                    <?= $activity['action_type'] === 'prescription' ? 'Prescription created' : 'Visit ' . $activity['status'] ?>
                                    <span class="text-gray-500">- <?= htmlspecialchars($activity['patient_name'] ?? 'Unknown') ?></span>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?php if ($activity['action_type'] === 'prescription'): ?>
                                        Status: <?= ucfirst($activity['status'] ?? 'Pending') ?>
                                    <?php else: ?>
                                        Visit #<?= htmlspecialchars($activity['id'] ?? '') ?>
                                    <?php endif; ?>
                                    • <?= time_ago($activity['created_at'] ?? '') ?>
                                </p>
                            </div>
                            <?php if ($activity['action_type'] === 'visit' && ($activity['status'] ?? '') === 'pending'): ?>
                                <a href="consultation.php?visit_id=<?= $activity['id'] ?>" class="bg-purple-600 text-white px-3 py-1 rounded text-xs hover:bg-purple-700 transition">
                                    Consult
                                </a>
                            <?php elseif ($activity['action_type'] === 'prescription'): ?>
                                <a href="view_prescription.php?id=<?= $activity['id'] ?>" class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 transition">
                                    View
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-inbox text-2xl block mb-2"></i>
                        <p>No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-800 font-semibold flex items-center gap-2">
                    <i class="fas fa-bolt text-yellow-500"></i>
                    Quick Actions
                </h3>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a href="my_patients.php" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:shadow-md transition group">
                    <i class="fas fa-users text-blue-500 text-2xl group-hover:scale-110 transition"></i>
                    <span class="text-xs font-medium text-gray-600 mt-1">My Patients</span>
                </a>
                <a href="consultation.php?patient_id=0" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl hover:border-green-500 hover:shadow-md transition group">
                    <i class="fas fa-stethoscope text-green-500 text-2xl group-hover:scale-110 transition"></i>
                    <span class="text-xs font-medium text-gray-600 mt-1">Consultation</span>
                </a>
                <a href="prescribe.php" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl hover:border-purple-500 hover:shadow-md transition group">
                    <i class="fas fa-prescription text-purple-500 text-2xl group-hover:scale-110 transition"></i>
                    <span class="text-xs font-medium text-gray-600 mt-1">Prescribe</span>
                </a>
                <a href="view_prescriptions.php" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl hover:border-orange-500 hover:shadow-md transition group">
                    <i class="fas fa-list text-orange-500 text-2xl group-hover:scale-110 transition"></i>
                    <span class="text-xs font-medium text-gray-600 mt-1">Prescriptions</span>
                </a>
                <a href="lab_results.php" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl hover:border-teal-500 hover:shadow-md transition group">
                    <i class="fas fa-flask text-teal-500 text-2xl group-hover:scale-110 transition"></i>
                    <span class="text-xs font-medium text-gray-600 mt-1">Lab Results</span>
                </a>
                <a href="profile.php" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl hover:border-gray-500 hover:shadow-md transition group">
                    <i class="fas fa-user-cog text-gray-500 text-2xl group-hover:scale-110 transition"></i>
                    <span class="text-xs font-medium text-gray-600 mt-1">My Profile</span>
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
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST NOTIFICATION -->
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
    }
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid #E2E8F0;
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: #94A3B8;
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .text-gray-300 { color: #D1D5DB; }
    .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
    .animate-pulse { animation: pulse 2s infinite; }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    [data-theme="dark"] .bg-white { background: #1E293B; }
    [data-theme="dark"] .border-gray-200 { border-color: #334155; }
    [data-theme="dark"] .text-gray-800 { color: #F1F5F9; }
    [data-theme="dark"] .text-gray-600 { color: #94A3B8; }
    [data-theme="dark"] .text-gray-500 { color: #94A3B8; }
    [data-theme="dark"] .text-gray-400 { color: #64748B; }
    [data-theme="dark"] .bg-blue-50 { background: #1E3A5F; }
    [data-theme="dark"] .border-l-4 { border-left-color: #0B5ED7; }
    [data-theme="dark"] .bg-gray-100 { background: #0F172A; }
    [data-theme="dark"] .border-gray-100 { border-color: #334155; }
    [data-theme="dark"] .footer { border-top-color: #334155; color: #64748B; }
    [data-theme="dark"] .hover\:bg-gray-200:hover { background: #1E293B; }
    [data-theme="dark"] .bg-blue-100 { background: #1E3A5F; }
    [data-theme="dark"] .bg-green-100 { background: #1A3A2A; }
    [data-theme="dark"] .bg-purple-100 { background: #2A1A3A; }
    [data-theme="dark"] .bg-orange-100 { background: #3D2E0A; }
    [data-theme="dark"] .bg-teal-100 { background: #1A3A3A; }
    [data-theme="dark"] .bg-indigo-100 { background: #1A1A3A; }
    [data-theme="dark"] .bg-rose-100 { background: #3A1A2A; }
    [data-theme="dark"] .bg-yellow-100 { background: #3D2E0A; }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // ================================================================
    // CHART - Weekly Visits
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('visitsChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var gridColor = isDark ? '#334155' : '#E2E8F0';
            var textColor = isDark ? '#94A3B8' : '#64748B';
            
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Visits',
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
    });

    // ================================================================
    // SHOW TOAST
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
    // NOTIFICATIONS
    // ================================================================
    <?php if ($pending_visits > 0): ?>
    setTimeout(function() {
        showToast('👋 Patients Waiting', 
            'You have <?= $pending_visits ?> patient(s) waiting for consultation', 
            'info'
        );
    }, 1000);
    <?php endif; ?>

    <?php if ($new_patients_count > 0): ?>
    setTimeout(function() {
        showToast('🆕 New Patient Assigned', 
            '<?= $new_patients_count ?> new patient(s) have been assigned to you', 
            'success'
        );
    }, 2000);
    <?php endif; ?>

    console.log('%c👨‍⚕️ Doctor Dashboard - <?= htmlspecialchars($doctor_name) ?>', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= number_format($pending_visits) ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Completed: <?= number_format($completed_visits) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📅 Today: <?= number_format($today_visits) ?> visits', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>