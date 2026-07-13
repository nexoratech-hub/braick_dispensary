<?php
// ================================================================
// FILE: frontend/components/reception_sidebar.php
// RECEPTION - SHARED SIDEBAR (BLUE BACKGROUND)
// HAKUNA JINA LA RECEPTIONIST, HAKUNA BRANCH
// WITH REAL DATA FOR BADGES + AJAX AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES (Only if database is available)
// ================================================================
$patient_count = 0;
$appointment_count = 0;
$pending_appointments = 0;
$today_visits = 0;
$pending_patients = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // 1. Total Patients (for this branch)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([$user_branch_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Today's Appointments (for this branch)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
        $stmt->execute([$user_branch_id]);
        $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 3. Pending Appointments (for this branch)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
        $stmt->execute([$user_branch_id]);
        $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 4. Today's Visits (for this branch)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$user_branch_id]);
        $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 5. Pending Patients (waiting for doctor)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND status IN ('pending', 'assigned')");
        $stmt->execute([$user_branch_id]);
        $pending_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
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
    
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-link .badge.green {
        background: #059669;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
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
    
    .sidebar-status .status-time {
        font-size: 0.6rem;
        color: #9EC5FE;
        margin-left: auto;
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
<!-- SIDEBAR - HAKUNA JINA LA RECEPTIONIST, HAKUNA BRANCH -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Reception Panel</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ===== RECEPTION MENU ===== -->
        <div class="nav-label">Reception</div>
        
        <!-- 1. Dashboard -->
        <a href="../reception/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- 2. Register Patient -->
        <a href="../reception/new_patient.php" class="sidebar-link <?= isActive('new_patient.php') ?>">
            <i class="fas fa-user-plus"></i> Register Patient
        </a>
        
        <!-- 3. Patients -->
        <a href="../reception/patients.php" class="sidebar-link <?= isActive('patients.php') ?>">
            <i class="fas fa-users"></i> Patients
            <span class="badge" id="receptionPatientCount"><?= $patient_count ?></span>
        </a>
        
        <!-- ===== APPOINTMENTS ===== -->
        <div class="nav-label mt-2">Appointments</div>
        
        <!-- 4. Appointments -->
        <a href="../reception/appointments.php" class="sidebar-link <?= isActive('appointments.php') ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <span class="badge <?= $pending_appointments > 0 ? 'danger' : '' ?>" id="receptionAppointmentCount"><?= $appointment_count ?></span>
        </a>
        
        <!-- ===== VISITS ===== -->
        <div class="nav-label mt-2">Visits</div>
        
        <!-- 5. Visit (Today's Visits) -->
        <a href="../reception/visits.php?filter=today" class="sidebar-link <?= isActive('visits.php') ?>">
            <i class="fas fa-clinic-medical"></i> Visit
            <span class="badge" id="receptionTodayVisits"><?= $today_visits ?></span>
        </a>
        
        <!-- 6. Assign Doctor -->
        <a href="../reception/assign_doctor.php" class="sidebar-link <?= isActive('assign_doctor.php') ?>">
            <i class="fas fa-user-md"></i> Assign Doctor
            <span class="badge <?= $pending_patients > 0 ? 'danger' : '' ?>" id="receptionPendingPatients"><?= $pending_patients ?></span>
        </a>
        
        <!-- ===== CASHIER ===== -->
        <div class="nav-label mt-2">Finance</div>
        
        <!-- 7. Cashier -->
        <a href="../cashier/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') && strpos($_SERVER['REQUEST_URI'], 'cashier') !== false ? 'active' : '' ?>">
            <i class="fas fa-cash-register"></i> Cashier
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- Profile -->
        <a href="../reception/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
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
        <span class="status-time" id="sidebarStatusTime"><?= date('H:i:s') ?></span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT - WITH AJAX AUTO-UPDATE (EVERY 3 SECONDS) -->
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
    function updateSidebarBadges(patientCount, appointmentCount, todayVisits, pendingPatients) {
        if (patientCount !== undefined) {
            var el = document.getElementById('receptionPatientCount');
            if (el) el.textContent = patientCount;
        }
        if (appointmentCount !== undefined) {
            var el = document.getElementById('receptionAppointmentCount');
            if (el) {
                el.textContent = appointmentCount;
                // If there are pending appointments, show danger badge
                // This requires additional data from server
            }
        }
        if (todayVisits !== undefined) {
            var el = document.getElementById('receptionTodayVisits');
            if (el) el.textContent = todayVisits;
        }
        if (pendingPatients !== undefined) {
            var el = document.getElementById('receptionPendingPatients');
            if (el) {
                el.textContent = pendingPatients;
                if (pendingPatients > 0) {
                    el.className = 'badge danger';
                } else {
                    el.className = 'badge';
                }
            }
        }
        
        // Update status time
        var timeEl = document.getElementById('sidebarStatusTime');
        if (timeEl) {
            var now = new Date();
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }

    // ================================================================
    // AJAX AUTO-UPDATE - FETCH SIDEBAR DATA EVERY 3 SECONDS
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;
    var sidebarLastHash = null;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        fetch('../reception/get_sidebar_stats.php')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    // Check if data has changed
                    if (sidebarLastHash !== data.hash) {
                        sidebarLastHash = data.hash;
                        updateSidebarBadges(
                            data.patients,
                            data.appointments,
                            data.today_visits,
                            data.pending_patients
                        );
                    }
                    
                    // Update status time
                    var timeEl = document.getElementById('sidebarStatusTime');
                    if (timeEl) {
                        var now = new Date();
                        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    }
                }
                sidebarIsUpdating = false;
            })
            .catch(function(error) {
                console.error('Sidebar update error:', error);
                sidebarIsUpdating = false;
            });
    }

    // ================================================================
    // START SIDEBAR AUTO-UPDATE
    // ================================================================
    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // Update every 3 seconds
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
        // Fetch immediately
        fetchSidebarData();
    }

    // ================================================================
    // STOP SIDEBAR AUTO-UPDATE
    // ================================================================
    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZE SIDEBAR AUTO-UPDATE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Start after 2 seconds
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    console.log('%c🏥 Reception Sidebar - v2', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Menu: Dashboard, Register Patient, Patients, Appointments, Visit, Assign Doctor, Cashier', 'font-size:12px; color:#9EC5FE;');
    console.log('%c👥 Patients: <?= $patient_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c📅 Appointments: <?= $appointment_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending Patients: <?= $pending_patients ?>', 'font-size:12px; color:#D97706;');
    console.log('%c🔄 Auto-update: Every 3 seconds', 'font-size:12px; color:#34D399;');
</script>