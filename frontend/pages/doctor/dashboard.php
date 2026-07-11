<?php
// ================================================================
// FILE: frontend/pages/doctor/dashboard.php
// DOCTOR'S OWN DASHBOARD
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK DOCTOR LOGIN
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$doctor_id = $_SESSION['user_id'];
$branch_id = $_SESSION['branch_id'] ?? 0;

// ================================================================
// GET DOCTOR DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: ../auth/login.php');
    exit;
}

// ================================================================
// GET STATISTICS
// ================================================================

// 1. Total Patients
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Pending Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Pending Lab Tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Today's Appointments
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')
");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Revenue Generated
$stmt = $db->prepare("
    SELECT COALESCE(SUM(ps.total), 0) as revenue 
    FROM pharmacy_sales ps
    JOIN prescriptions p ON ps.prescription_id = p.id
    WHERE p.doctor_id = ? AND ps.payment_status = 'paid'
");
$stmt->execute([$doctor_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 7. Online Status - Update
$stmt = $db->prepare("UPDATE users SET is_online = 1, last_online = NOW() WHERE id = ?");
$stmt->execute([$doctor_id]);

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================

// Recent Patients
$stmt = $db->prepare("
    SELECT DISTINCT p.*, v.created_at as last_visit 
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY v.created_at DESC LIMIT 5
");
$stmt->execute([$doctor_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's Appointments List
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.patient_id 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_date LIMIT 5
");
$stmt->execute([$doctor_id]);
$appointments_today = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCHES (for selector)
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
$selected_branch_id = $branch_id;
include_once '../../components/doctor_header.php';
include_once '../../components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home mr-2" style="color: var(--primary);"></i> Doctor Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <strong>Dr. <?= htmlspecialchars($doctor['full_name']) ?></strong>!
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-circle text-[6px] text-green-500 mr-1"></i> Online
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_visit.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> New Visit
            </a>
            <a href="prescribe.php" class="btn btn-primary btn-sm" style="background: #0B5ED7;">
                <i class="fas fa-prescription"></i> Prescribe
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid-cols-4 mb-5">
        
        <div class="stat-card animate-fade-in-up">
            <div class="stat-icon green"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-number"><?= number_format($total_patients) ?></p>
                <p class="stat-label">Total Patients</p>
                <span class="stat-trend"><i class="fas fa-arrow-up text-green-600"></i> All time</span>
            </div>
        </div>
        
        <div class="stat-card animate-fade-in-up">
            <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
            <div>
                <p class="stat-number"><?= number_format($today_patients) ?></p>
                <p class="stat-label">Today's Patients</p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Today</span>
            </div>
        </div>
        
        <div class="stat-card animate-fade-in-up">
            <div class="stat-icon yellow"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-number"><?= number_format($pending_prescriptions) ?></p>
                <p class="stat-label">Pending Prescriptions</p>
                <?php if ($pending_prescriptions > 0): ?>
                    <span class="stat-trend text-yellow-600"><i class="fas fa-clock"></i> Needs attention</span>
                <?php else: ?>
                    <span class="stat-trend text-green-600"><i class="fas fa-check"></i> All done</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stat-card animate-fade-in-up">
            <div class="stat-icon purple"><i class="fas fa-calendar-check"></i></div>
            <div>
                <p class="stat-number"><?= number_format($today_appointments) ?></p>
                <p class="stat-label">Today's Appointments</p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Scheduled</span>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- SECOND ROW - Revenue & Pending Lab -->
    <!-- ================================================================ -->
    <div class="grid-cols-2 mb-5">
        
        <div class="stat-card animate-fade-in-up" style="grid-column: span 1;">
            <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-number">TSh <?= number_format($revenue) ?></p>
                <p class="stat-label">Revenue Generated</p>
                <span class="stat-trend"><i class="fas fa-arrow-up text-green-600"></i> All time</span>
            </div>
        </div>
        
        <div class="stat-card animate-fade-in-up" style="grid-column: span 1;">
            <div class="stat-icon purple"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-number"><?= number_format($pending_lab_tests) ?></p>
                <p class="stat-label">Pending Lab Tests</p>
                <?php if ($pending_lab_tests > 0): ?>
                    <span class="stat-trend text-yellow-600"><i class="fas fa-clock"></i> Waiting for results</span>
                <?php else: ?>
                    <span class="stat-trend text-green-600"><i class="fas fa-check"></i> All completed</span>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TODAY'S APPOINTMENTS & RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="grid-cols-2">
        
        <!-- Today's Appointments -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-calendar-check text-green-600"></i> Today's Appointments
                <span class="text-sm font-normal text-gray-400">(<?= count($appointments_today) ?>)</span>
            </h3>
            
            <?php if (count($appointments_today) > 0): ?>
                <?php foreach ($appointments_today as $appt): ?>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <span class="font-medium text-sm"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                            <span class="text-sm ml-3"><?= htmlspecialchars($appt['patient_name']) ?></span>
                            <span class="text-xs text-gray-400 block"><?= htmlspecialchars($appt['patient_id']) ?></span>
                        </div>
                        <span class="text-xs px-3 py-1 rounded-full bg-green-100 text-green-700">
                            <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-400 py-4">
                    <i class="fas fa-calendar-check text-2xl block mb-2"></i>
                    No appointments scheduled for today
                </p>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <a href="appointments.php" class="text-sm text-green-600 hover:underline">
                    View all appointments →
                </a>
            </div>
        </div>
        
        <!-- Recent Patients -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-user-injured text-blue-600"></i> Recent Patients
                <span class="text-sm font-normal text-gray-400">(<?= count($recent_patients) ?>)</span>
            </h3>
            
            <?php if (count($recent_patients) > 0): ?>
                <?php foreach ($recent_patients as $patient): ?>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <span class="font-medium text-sm"><?= htmlspecialchars($patient['full_name']) ?></span>
                            <span class="text-xs text-gray-400 block"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                        </div>
                        <span class="text-xs text-gray-400">
                            <?= isset($patient['last_visit']) ? time_ago($patient['last_visit']) : 'N/A' ?>
                        </span>
                        <a href="patient_details.php?id=<?= $patient['id'] ?>" 
                           class="text-sm text-blue-600 hover:underline">
                            View
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-400 py-4">
                    <i class="fas fa-user-injured text-2xl block mb-2"></i>
                    No patients yet
                </p>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <a href="my_patients.php" class="text-sm text-blue-600 hover:underline">
                    View all patients →
                </a>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="card mt-5 animate-fade-in-up">
        <h3 class="card-title">
            <i class="fas fa-bolt text-green-600"></i> Quick Actions
        </h3>
        <div class="grid-cols-4" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
            
            <a href="new_visit.php" class="stat-card" style="flex-direction: column; text-align: center; padding: 16px; cursor: pointer;">
                <div class="stat-icon green" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <span class="font-medium text-sm mt-2">New Visit</span>
            </a>
            
            <a href="prescribe.php" class="stat-card" style="flex-direction: column; text-align: center; padding: 16px; cursor: pointer;">
                <div class="stat-icon blue" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-prescription"></i>
                </div>
                <span class="font-medium text-sm mt-2">Prescribe</span>
            </a>
            
            <a href="lab_results.php" class="stat-card" style="flex-direction: column; text-align: center; padding: 16px; cursor: pointer;">
                <div class="stat-icon purple" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-flask"></i>
                </div>
                <span class="font-medium text-sm mt-2">Lab Results</span>
            </a>
            
            <a href="referrals.php" class="stat-card" style="flex-direction: column; text-align: center; padding: 16px; cursor: pointer;">
                <div class="stat-icon yellow" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-share-alt"></i>
                </div>
                <span class="font-medium text-sm mt-2">Refer Patient</span>
            </a>
            
            <a href="appointments.php" class="stat-card" style="flex-direction: column; text-align: center; padding: 16px; cursor: pointer;">
                <div class="stat-icon green" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <span class="font-medium text-sm mt-2">Appointments</span>
            </a>
            
            <a href="history.php" class="stat-card" style="flex-direction: column; text-align: center; padding: 16px; cursor: pointer;">
                <div class="stat-icon blue" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-history"></i>
                </div>
                <span class="font-medium text-sm mt-2">Patient History</span>
            </a>
            
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
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // SIDEBAR TOGGLE (already in header)
    // ================================================================
    
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

    console.log('%c✅ Doctor Dashboard loaded successfully', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>