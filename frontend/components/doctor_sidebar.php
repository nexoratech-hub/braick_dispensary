<?php
// ================================================================
// FILE: frontend/components/doctor_sidebar.php
// DOCTOR - SHARED SIDEBAR (BLUE BACKGROUND)
// WITH CONSULTATIONS MENU (Pending, Completed, Cancelled)
// ORDER: Dashboard → My Patients → Prescribe → Prescriptions → Lab Results → Consultations → Referrals → Appointments → Documents
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
// ================================================================
$patient_count = 0;
$lab_count = 0;
$referral_count = 0;
$appointment_count = 0;
$pending_consultations = 0;
$completed_consultations = 0;
$cancelled_consultations = 0;
$pending_prescriptions = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $doctor_id = $_SESSION['user_id'];
    $doctor_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // 1. Total Patients (distinct patients)
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Pending Lab Tests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([$doctor_id]);
        $lab_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 3. Pending Referrals
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM referrals WHERE from_doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctor_id]);
        $referral_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 4. Today's Appointments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')");
        $stmt->execute([$doctor_id]);
        $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 5. Pending Consultations
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
            AND is_completed = 0
        ");
        $stmt->execute([$doctor_id]);
        $pending_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 6. Completed Consultations
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status = 'completed'
            AND is_completed = 1
        ");
        $stmt->execute([$doctor_id]);
        $completed_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 7. Cancelled Consultations
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status = 'cancelled'
        ");
        $stmt->execute([$doctor_id]);
        $cancelled_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 8. Pending Prescriptions
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctor_id]);
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    } catch (Exception $e) {
        // If error, keep counts as 0
    }
}

// Detect current page
$current_page = basename($_SERVER['PHP_SELF']);

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE
// ================================================================
function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// FUNCTION TO CHECK ACTIVE FOR CONSULTATION SUB-MENU
// ================================================================
function isConsultationActive($pages) {
    global $current_page;
    if (in_array($current_page, $pages)) {
        return 'active';
    }
    return '';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - BLUE BACKGROUND
       ================================================================ */
    .sidebar {
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0;
        width: 270px; 
        background: #0B4EA8;
        color: white;
        z-index: 50; 
        overflow-y: auto;
        transition: transform 0.3s ease;
    }
    
    [data-theme="dark"] .sidebar {
        background: #0A3D7A;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0A3D7A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    
    .sidebar-brand {
        padding: 22px 20px 16px;
        border-bottom: 2px solid #0A3D7A;
    }
    
    .sidebar-brand .logo {
        width: 48px; 
        height: 48px; 
        border-radius: 12px;
        object-fit: cover; 
        background: white; 
        padding: 4px;
    }
    
    .sidebar-brand .brand-text { 
        color: white; 
        font-weight: 700; 
        font-size: 1rem; 
    }
    
    .sidebar-brand .brand-sub { 
        color: #9EC5FE; 
        font-size: 0.7rem; 
    }
    
    .sidebar-nav { 
        padding: 14px 10px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.55rem; 
        text-transform: uppercase;
        letter-spacing: 0.1em; 
        color: #9EC5FE;
        padding: 0 12px; 
        margin: 12px 0 6px; 
        font-weight: 700;
    }
    
    .sidebar-link {
        display: flex; 
        align-items: center; 
        gap: 12px;
        padding: 9px 14px; 
        border-radius: 10px;
        color: #D2E3FC; 
        text-decoration: none;
        transition: all 0.3s ease; 
        font-size: 0.85rem; 
        font-weight: 500;
        margin: 2px 0;
        background: transparent;
        cursor: pointer;
    }
    
    .sidebar-link:hover {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.4);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.4);
    }
    
    .sidebar-link i { 
        width: 20px; 
        text-align: center; 
        font-size: 1rem; 
    }
    
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        padding: 1px 9px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        min-width: 20px;
        text-align: center;
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-link .badge.warning {
        background: #D97706;
    }
    
    .sidebar-link .badge.green {
        background: #059669;
    }
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
    }
    
    .sidebar-link .badge.purple {
        background: #7C3AED;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* ================================================================
       SUB-MENU STYLES
       ================================================================ */
    .sidebar-sub-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 7px 14px 7px 46px;
        border-radius: 8px;
        color: #B8D4FE;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.8rem;
        font-weight: 400;
        margin: 1px 0;
        background: transparent;
        cursor: pointer;
        position: relative;
    }
    
    .sidebar-sub-link:hover {
        background: rgba(11, 94, 215, 0.3);
        color: white;
        transform: translateX(2px);
    }
    
    .sidebar-sub-link.active {
        background: rgba(11, 94, 215, 0.4);
        color: white;
        border-left: 3px solid #6EA8FE;
    }
    
    .sidebar-sub-link i {
        width: 16px;
        text-align: center;
        font-size: 0.75rem;
        color: #6EA8FE;
    }
    
    .sidebar-sub-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.1);
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: #B8D4FE;
        transition: all 0.3s ease;
        min-width: 18px;
        text-align: center;
    }
    
    .sidebar-sub-link:hover .badge {
        background: rgba(255,255,255,0.2);
    }
    
    .sidebar-sub-link .badge.danger {
        background: #EF4444;
        color: white;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-sub-link .badge.green {
        background: #059669;
        color: white;
    }
    
    .sidebar-sub-link .badge.gray {
        background: #64748B;
        color: white;
    }
    
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.1);
        padding-top: 12px;
        margin-top: 8px;
        color: #FCA5A5;
    }
    
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    .sidebar-status {
        padding: 12px 20px;
        border-top: 2px solid #0A3D7A;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-status .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .sidebar-status .status-dot.online {
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
    }
    
    .sidebar-status .status-text {
        font-size: 0.75rem;
        color: #D2E3FC;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    
    @media (max-width: 1024px) {
        .sidebar { 
            transform: translateX(-100%); 
        }
        .sidebar.open { 
            transform: translateX(0); 
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR - DOCTOR PANEL -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Doctor Panel</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ================================================================ -->
        <!-- 1. DASHBOARD -->
        <!-- ================================================================ -->
        <div class="nav-label">Main Menu</div>
        
        <a href="../doctor/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ================================================================ -->
        <!-- 2. MY PATIENTS -->
        <!-- ================================================================ -->
        <div class="nav-label mt-2">Patients</div>
        
        <a href="../doctor/my_patients.php" class="sidebar-link <?= isActive('my_patients.php') ?>">
            <i class="fas fa-users"></i> My Patients
            <span class="badge" id="patientCount"><?= $patient_count ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- 3. PRESCRIBE -->
        <!-- ================================================================ -->
        <a href="../doctor/prescribe.php" class="sidebar-link <?= isActive('prescribe.php') ?>">
            <i class="fas fa-prescription"></i> Prescribe
        </a>
        
        <!-- ================================================================ -->
        <!-- 4. PRESCRIPTIONS -->
        <!-- ================================================================ -->
        <div class="nav-label mt-2">Clinical</div>
        
        <a href="../doctor/view_prescriptions.php" class="sidebar-link <?= isActive('view_prescriptions.php') ?>">
            <i class="fas fa-file-prescription"></i> Prescriptions
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge warning" id="prescriptionBadge"><?= $pending_prescriptions ?></span>
            <?php else: ?>
                <span class="badge" id="prescriptionBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- 5. LAB RESULTS -->
        <!-- ================================================================ -->
        <a href="../doctor/lab_results.php" class="sidebar-link <?= isActive('lab_results.php') ?>">
            <i class="fas fa-flask"></i> Lab Results
            <?php if ($lab_count > 0): ?>
                <span class="badge warning" id="labCount"><?= $lab_count ?></span>
            <?php else: ?>
                <span class="badge" id="labCount">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- 6. CONSULTATIONS (SUB-MENU) -->
        <!-- ================================================================ -->
        <div class="nav-label mt-2">Consultations</div>
        
        <!-- Pending -->
        <a href="../doctor/consultations.php?filter=pending" 
           class="sidebar-sub-link <?= isConsultationActive(['consultations.php', 'pending_consultations.php']) ?>">
            <i class="fas fa-clock"></i> Pending
            <?php if ($pending_consultations > 0): ?>
                <span class="badge danger" id="pendingConsultBadge"><?= $pending_consultations ?></span>
            <?php else: ?>
                <span class="badge" id="pendingConsultBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Completed -->
        <a href="../doctor/consultations.php?filter=completed" 
           class="sidebar-sub-link <?= isConsultationActive(['consultations.php', 'completed_consultations.php']) ?>">
            <i class="fas fa-check-circle"></i> Completed
            <?php if ($completed_consultations > 0): ?>
                <span class="badge green" id="completedConsultBadge"><?= $completed_consultations ?></span>
            <?php else: ?>
                <span class="badge" id="completedConsultBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Cancelled -->
        <a href="../doctor/consultations.php?filter=cancelled" 
           class="sidebar-sub-link <?= isConsultationActive(['consultations.php', 'cancelled_consultations.php']) ?>">
            <i class="fas fa-times-circle"></i> Cancelled
            <?php if ($cancelled_consultations > 0): ?>
                <span class="badge gray" id="cancelledConsultBadge"><?= $cancelled_consultations ?></span>
            <?php else: ?>
                <span class="badge" id="cancelledConsultBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- 7. REFERRALS -->
        <!-- ================================================================ -->
        <a href="../doctor/referrals.php" class="sidebar-link <?= isActive('referrals.php') ?>">
            <i class="fas fa-share-alt"></i> Referrals
            <?php if ($referral_count > 0): ?>
                <span class="badge warning" id="referralCount"><?= $referral_count ?></span>
            <?php else: ?>
                <span class="badge" id="referralCount">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- 8. APPOINTMENTS -->
        <!-- ================================================================ -->
        <div class="nav-label mt-2">Schedule</div>
        
        <a href="../doctor/appointments.php" class="sidebar-link <?= isActive('appointments.php') ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <?php if ($appointment_count > 0): ?>
                <span class="badge blue" id="appointmentCount"><?= $appointment_count ?></span>
            <?php else: ?>
                <span class="badge" id="appointmentCount">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- 9. DOCUMENTS -->
        <!-- ================================================================ -->
        <a href="../doctor/documents.php" class="sidebar-link <?= isActive('documents.php') ?>">
            <i class="fas fa-folder"></i> Documents
        </a>
        
        <!-- ================================================================ -->
        <!-- 10. PROFILE -->
        <!-- ================================================================ -->
        <div class="nav-label mt-2">Account</div>
        
        <a href="../doctor/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- ================================================================ -->
        <!-- 11. LOGOUT -->
        <!-- ================================================================ -->
        <a href="../../../logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- Online Status -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE (Mobile)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (sidebar && sidebarToggle) {
                    if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                        sidebar.classList.remove('open');
                    }
                }
            }
        });
    });

    // ================================================================
    // UPDATE SIDEBAR BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        if (data.patientCount !== undefined) {
            var el = document.getElementById('patientCount');
            if (el) el.textContent = data.patientCount;
        }
        if (data.labCount !== undefined) {
            var el = document.getElementById('labCount');
            if (el) {
                el.textContent = data.labCount;
                el.className = data.labCount > 0 ? 'badge warning' : 'badge';
            }
        }
        if (data.referralCount !== undefined) {
            var el = document.getElementById('referralCount');
            if (el) {
                el.textContent = data.referralCount;
                el.className = data.referralCount > 0 ? 'badge warning' : 'badge';
            }
        }
        if (data.appointmentCount !== undefined) {
            var el = document.getElementById('appointmentCount');
            if (el) {
                el.textContent = data.appointmentCount;
                el.className = data.appointmentCount > 0 ? 'badge blue' : 'badge';
            }
        }
        if (data.pendingConsultations !== undefined) {
            var el = document.getElementById('pendingConsultBadge');
            if (el) {
                el.textContent = data.pendingConsultations;
                el.className = data.pendingConsultations > 0 ? 'badge danger' : 'badge';
            }
        }
        if (data.completedConsultations !== undefined) {
            var el = document.getElementById('completedConsultBadge');
            if (el) {
                el.textContent = data.completedConsultations;
                el.className = data.completedConsultations > 0 ? 'badge green' : 'badge';
            }
        }
        if (data.cancelledConsultations !== undefined) {
            var el = document.getElementById('cancelledConsultBadge');
            if (el) {
                el.textContent = data.cancelledConsultations;
                el.className = data.cancelledConsultations > 0 ? 'badge gray' : 'badge';
            }
        }
        if (data.pendingPrescriptions !== undefined) {
            var el = document.getElementById('prescriptionBadge');
            if (el) {
                el.textContent = data.pendingPrescriptions;
                el.className = data.pendingPrescriptions > 0 ? 'badge warning' : 'badge';
            }
        }
    }

    console.log('%c👨‍⚕️ Doctor Sidebar - New Order', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Menu Order:', 'font-size:12px; color:#9EC5FE;');
    console.log('%c   1. Dashboard', 'font-size:12px; color:#9EC5FE;');
    console.log('%c   2. My Patients (%s)', 'font-size:12px; color:#059669;', '<?= $patient_count ?>');
    console.log('%c   3. Prescribe', 'font-size:12px; color:#9EC5FE;');
    console.log('%c   4. Prescriptions (%s)', 'font-size:12px; color:#D97706;', '<?= $pending_prescriptions ?>');
    console.log('%c   5. Lab Results (%s)', 'font-size:12px; color:#D97706;', '<?= $lab_count ?>');
    console.log('%c   6. Consultations', 'font-size:12px; color:#9EC5FE;');
    console.log('%c      ├── Pending (%s)', 'font-size:12px; color:#EF4444;', '<?= $pending_consultations ?>');
    console.log('%c      ├── Completed (%s)', 'font-size:12px; color:#059669;', '<?= $completed_consultations ?>');
    console.log('%c      └── Cancelled (%s)', 'font-size:12px; color:#64748B;', '<?= $cancelled_consultations ?>');
    console.log('%c   7. Referrals (%s)', 'font-size:12px; color:#D97706;', '<?= $referral_count ?>');
    console.log('%c   8. Appointments (%s)', 'font-size:12px; color:#0B5ED7;', '<?= $appointment_count ?>');
    console.log('%c   9. Documents', 'font-size:12px; color:#9EC5FE;');
    console.log('%c   10. Profile', 'font-size:12px; color:#9EC5FE;');
    console.log('%c   11. Logout', 'font-size:12px; color:#FCA5A5;');
</script>