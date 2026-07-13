<?php
// ================================================================
// FILE: frontend/pages/reception/view_patient.php
// RECEPTION - VIEW PATIENT DETAILS (DATABASE CONNECTED)
// BRAICK DISPENSARY
// ================================================================

session_start();

require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Receptionist Mary';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
}

$selected_branch_id = $_GET['branch'] ?? 'all';
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . $selected_branch_id);
    exit;
}

try {
    $db = getDB();
    
    // Get patient details
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        header('Location: patients.php?branch=' . $selected_branch_id);
        exit;
    }
    
    // Get patient visits
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name 
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll();
    
    // Get patient appointments
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as doctor_name 
        FROM appointments a
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
    
    // Get total visits count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $total_visits = $stmt->fetch()['total'] ?? 0;
    
    // Get total prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $total_prescriptions = $stmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    $patient = null;
    $visits = [];
    $appointments = [];
    $total_visits = 0;
    $total_prescriptions = 0;
}

include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .patient-profile-avatar {
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
    .info-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .info-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .stat-box {
        text-align: center;
        padding: 12px;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        transition: all 0.3s ease;
    }
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .stat-box .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-box .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .visit-item, .appointment-item {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .visit-item:hover, .appointment-item:hover {
        background: var(--bg-body);
    }
    .visit-item:last-child, .appointment-item:last-child {
        border-bottom: none;
    }
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
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach (getBranches() as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
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
                <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i> Patient Details
            </h1>
            <p class="page-subtitle">
                View complete patient information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_appointment.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-calendar-plus"></i> New Appointment
            </a>
            <a href="patients.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($patient): ?>
    
    <!-- ================================================================ -->
    <!-- PATIENT PROFILE -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Profile Card -->
        <div class="card lg:col-span-1">
            <div class="text-center">
                <div class="patient-profile-avatar mx-auto" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                    <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                </div>
                <h2 class="text-xl font-bold mt-3 text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></h2>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'Not Assigned') ?>
                </p>
                <div class="mt-3 flex justify-center gap-2">
                    <span class="badge badge-blue"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                    <span class="badge badge-green"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="card lg:col-span-2">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i> Personal Information
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="info-label">Full Name</p>
                    <p class="info-value"><?= htmlspecialchars($patient['full_name']) ?></p>
                </div>
                <div>
                    <p class="info-label">Patient ID</p>
                    <p class="info-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="info-label">Date of Birth</p>
                    <p class="info-value"><?= !empty($patient['date_of_birth']) ? date('F d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="info-label">Gender</p>
                    <p class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="info-label">Phone</p>
                    <p class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="info-label">Email</p>
                    <p class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="info-label">Address</p>
                    <p class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="info-label">Emergency Contact</p>
                    <p class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="info-label">Blood Group</p>
                    <p class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="info-label">Allergies</p>
                    <p class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></p>
                </div>
                <div>
                    <p class="info-label">Registered</p>
                    <p class="info-value"><?= date('F d, Y h:i A', strtotime($patient['created_at'])) ?></p>
                </div>
                <div>
                    <p class="info-label">Branch</p>
                    <p class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'Not Assigned') ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS BOXES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="stat-box">
            <p class="stat-number"><?= $total_visits ?></p>
            <p class="stat-label">Total Visits</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= $total_prescriptions ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= count($appointments) ?></p>
            <p class="stat-label">Appointments</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= date('d/m/Y', strtotime($patient['created_at'])) ?></p>
            <p class="stat-label">Registered Date</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS & APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Recent Visits -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clinic-medical title-blue mr-2"></i> Recent Visits
                    <span class="text-sm font-normal text-gray-400">(<?= count($visits) ?>)</span>
                </h3>
            </div>
            <div class="max-h-60 overflow-y-auto">
                <?php if (count($visits) > 0): ?>
                    <?php foreach ($visits as $visit): ?>
                        <div class="visit-item">
                            <div>
                                <span class="font-medium text-sm"><?= date('M d, Y', strtotime($visit['created_at'])) ?></span>
                                <span class="text-xs text-gray-500 block">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></span>
                            </div>
                            <span class="badge <?= $visit['status'] === 'completed' ? 'badge-green' : 'badge-yellow' ?>">
                                <?= ucfirst($visit['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <p class="text-sm">No visits recorded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-green mr-2"></i> Appointments
                    <span class="text-sm font-normal text-gray-400">(<?= count($appointments) ?>)</span>
                </h3>
            </div>
            <div class="max-h-60 overflow-y-auto">
                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $appt): ?>
                        <div class="appointment-item">
                            <div>
                                <span class="font-medium text-sm"><?= date('M d, Y h:i A', strtotime($appt['appointment_date'])) ?></span>
                                <span class="text-xs text-gray-500 block">Dr. <?= htmlspecialchars($appt['doctor_name'] ?? 'Not assigned') ?></span>
                            </div>
                            <span class="badge <?= $appt['status'] === 'confirmed' || $appt['status'] === 'completed' ? 'badge-green' : 'badge-yellow' ?>">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <p class="text-sm">No appointments scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-user-circle text-4xl block mb-2"></i>
            <p>Patient not found</p>
            <a href="patients.php" class="text-primary hover:underline">Back to patients</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Details
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
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

    console.log('%c👤 Braick - View Patient', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Visits: <?= $total_visits ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>