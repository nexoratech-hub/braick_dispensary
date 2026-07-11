<?php
// ================================================================
// FILE: frontend/components/doctor_sidebar.php
// DOCTOR SIDEBAR
// BRAICK DISPENSARY
// ================================================================

// Variables needed: $doctor, $current_page
$doctor_name = $doctor['full_name'] ?? 'Doctor';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$selected_branch_id = $selected_branch_id ?? 'all';

// Counts for badges
$pending_prescriptions = $pending_prescriptions ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$total_patients = $total_patients ?? 0;
$today_appointments = $today_appointments ?? 0;
?>

<aside class="sidebar" id="doctorSidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-stethoscope"></i>
        </div>
        <div>
            <span class="brand-name">Braick</span>
            <span class="brand-sub">Doctor Panel</span>
        </div>
    </div>
    
    <!-- User Profile -->
    <div class="sidebar-user">
        <div class="user-avatar" style="background: <?= getUserColor($doctor_name) ?>;">
            <?= strtoupper(substr($doctor_name, 0, 1)) ?>
        </div>
        <div>
            <div class="user-name"><?= htmlspecialchars($doctor_name) ?></div>
            <div class="user-role">
                <span class="online-dot"></span> Online
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        
        <!-- Dashboard -->
        <a href="dashboard.php" class="sidebar-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <!-- My Patients -->
        <a href="my_patients.php" class="sidebar-link <?= $current_page === 'my_patients' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>My Patients</span>
            <span class="badge"><?= $total_patients ?></span>
        </a>
        
        <div class="nav-section">Clinical</div>
        
        <!-- New Visit -->
        <a href="new_visit.php" class="sidebar-link <?= $current_page === 'new_visit' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>New Visit</span>
        </a>
        
        <!-- Prescribe -->
        <a href="prescribe.php" class="sidebar-link <?= $current_page === 'prescribe' ? 'active' : '' ?>">
            <i class="fas fa-prescription"></i>
            <span>Prescribe</span>
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge badge-warning"><?= $pending_prescriptions ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Lab Results -->
        <a href="lab_results.php" class="sidebar-link <?= $current_page === 'lab_results' ? 'active' : '' ?>">
            <i class="fas fa-flask"></i>
            <span>Lab Results</span>
            <?php if ($pending_lab_tests > 0): ?>
                <span class="badge badge-danger"><?= $pending_lab_tests ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Referrals -->
        <a href="referrals.php" class="sidebar-link <?= $current_page === 'referrals' ? 'active' : '' ?>">
            <i class="fas fa-share-alt"></i>
            <span>Referrals</span>
        </a>
        
        <div class="nav-section">Management</div>
        
        <!-- Appointments -->
        <a href="appointments.php" class="sidebar-link <?= $current_page === 'appointments' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <?php if ($today_appointments > 0): ?>
                <span class="badge"><?= $today_appointments ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Documents -->
        <a href="documents.php" class="sidebar-link <?= $current_page === 'documents' ? 'active' : '' ?>">
            <i class="fas fa-file-medical"></i>
            <span>Documents</span>
        </a>
        
        <!-- History -->
        <a href="history.php" class="sidebar-link <?= $current_page === 'history' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Patient History</span>
        </a>
        
        <div class="nav-section">Account</div>
        
        <!-- Profile -->
        <a href="profile.php" class="sidebar-link <?= $current_page === 'profile' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>
        
        <!-- Logout -->
        <a href="../../logout.php" class="sidebar-link" style="color: #EF4444;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<?php
function getUserColor($name) {
    $colors = ['#059669', '#0B5ED7', '#7C3AED', '#DC2626', '#F59E0B', '#0891B2', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}
?>