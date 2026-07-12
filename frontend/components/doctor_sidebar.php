<?php
// ================================================================
// FILE: frontend/components/doctor_sidebar.php
// DOCTOR - SHARED SIDEBAR (BLUE BACKGROUND)
// HAKUNA JINA LA DOCTOR, HAKUNA BRANCH
// WITH REAL DATA FOR BADGES
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES (Only if database is available)
// ================================================================
$patient_count = 0;
$lab_count = 0;
$referral_count = 0;
$appointment_count = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $doctor_id = $_SESSION['user_id'];
    $doctor_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // 1. Total Patients (distinct patients)
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Pending Lab Tests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'");
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
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
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
<!-- SIDEBAR - HAKUNA JINA LA DOCTOR, HAKUNA BRANCH -->
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
        
        <!-- ===== MAIN MENU ===== -->
        <div class="nav-label">Main Menu</div>
        
        <!-- Dashboard -->
        <a href="../doctor/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- My Patients -->
        <a href="../doctor/my_patients.php" class="sidebar-link <?= isActive('my_patients.php') ?>">
            <i class="fas fa-users"></i> My Patients
            <span class="badge" id="patientCount"><?= $patient_count ?></span>
        </a>
        
        <!-- Prescribe -->
        <a href="../doctor/prescribe.php" class="sidebar-link <?= isActive('prescribe.php') ?>">
            <i class="fas fa-prescription"></i> Prescribe
        </a>
        
        <!-- ===== CLINICAL ===== -->
        <div class="nav-label mt-2">Clinical</div>
        
        <!-- View Prescriptions -->
        <a href="../doctor/view_prescriptions.php" class="sidebar-link <?= isActive('view_prescriptions.php') ?>">
            <i class="fas fa-file-prescription"></i> Prescriptions
        </a>
        
        <!-- Lab Results -->
        <a href="../doctor/lab_results.php" class="sidebar-link <?= isActive('lab_results.php') ?>">
            <i class="fas fa-flask"></i> Lab Results
            <span class="badge" id="labCount"><?= $lab_count ?></span>
        </a>
        
        <!-- Referrals -->
        <a href="../doctor/referrals.php" class="sidebar-link <?= isActive('referrals.php') ?>">
            <i class="fas fa-share-alt"></i> Referrals
            <span class="badge" id="referralCount"><?= $referral_count ?></span>
        </a>
        
        <!-- ===== SCHEDULE ===== -->
        <div class="nav-label mt-2">Schedule</div>
        
        <!-- Appointments -->
        <a href="../doctor/appointments.php" class="sidebar-link <?= isActive('appointments.php') ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <span class="badge" id="appointmentCount"><?= $appointment_count ?></span>
        </a>
        
        <!-- Documents -->
        <a href="../doctor/documents.php" class="sidebar-link <?= isActive('documents.php') ?>">
            <i class="fas fa-folder"></i> Documents
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- Profile -->
        <a href="../doctor/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- Logout -->
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
    // UPDATE SIDEBAR BADGES (Can be called from pages)
    // ================================================================
    function updateSidebarBadges(patientCount, labCount, referralCount, appointmentCount) {
        if (patientCount !== undefined) {
            var el = document.getElementById('patientCount');
            if (el) el.textContent = patientCount;
        }
        if (labCount !== undefined) {
            var el = document.getElementById('labCount');
            if (el) el.textContent = labCount;
        }
        if (referralCount !== undefined) {
            var el = document.getElementById('referralCount');
            if (el) el.textContent = referralCount;
        }
        if (appointmentCount !== undefined) {
            var el = document.getElementById('appointmentCount');
            if (el) el.textContent = appointmentCount;
        }
    }

    console.log('%c👨‍⚕️ Doctor Sidebar - With Real Data', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👥 Patients: <?= $patient_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c🧪 Lab Tests: <?= $lab_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔄 Referrals: <?= $referral_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c📅 Appointments: <?= $appointment_count ?>', 'font-size:12px; color:#059669;');
</script>