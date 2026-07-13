<?php
// ================================================================
// FILE: frontend/pages/reception/view_appointment.php
// RECEPTION - VIEW APPOINTMENT DETAILS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header('Location: appointments.php');
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone, p.email, p.address,
               u.full_name as doctor_name, u.specialty, u.phone as doctor_phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.branch_id = ?
    ");
    $stmt->execute([$appointment_id, $_SESSION['branch_id']]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        header('Location: appointments.php');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: appointments.php');
    exit;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .detail-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    .detail-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .detail-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .status-badge-display {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    .status-badge-display.scheduled { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-display.confirmed { background: #D1FAE5; color: #059669; }
    .status-badge-display.in-progress { background: #FEF3C7; color: #D97706; }
    .status-badge-display.completed { background: #D1FAE5; color: #059669; }
    .status-badge-display.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge-display.scheduled { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge-display.confirmed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-display.in-progress { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-display.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-display.cancelled { background: #3A1A1A; color: #F87171; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-red {
        background: #DC2626;
        color: white;
    }
    .btn-red:hover {
        background: #B91C1C;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .role-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($_SESSION['branch_name'] ?? 'Dodoma') ?>
        </span>
        
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
                <i class="fas fa-calendar-check mr-2" style="color: var(--primary);"></i> Appointment Details
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View appointment information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> ID: #<?= $appointment['id'] ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="appointment_status.php?id=<?= $appointment['id'] ?>&status=confirmed&redirect=view_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-green btn-sm">
                <i class="fas fa-check"></i> Confirm
            </a>
            <a href="appointment_status.php?id=<?= $appointment['id'] ?>&status=cancelled&redirect=view_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-red btn-sm">
                <i class="fas fa-times"></i> Cancel
            </a>
            <a href="appointments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENT DETAILS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Appointment Info -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Appointment Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Appointment ID</p>
                    <p class="detail-value">#<?= $appointment['id'] ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-display <?= $appointment['status'] ?>">
                            <?= ucfirst($appointment['status']) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Date & Time</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['appointment_date'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Purpose</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="detail-label">Created At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['created_at'])) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Patient Info -->
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value">
                        <a href="view_patient.php?id=<?= $appointment['patient_id'] ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($appointment['patient_name']) ?>
                        </a>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['address'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Doctor Info -->
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user-md text-primary mr-2"></i> Doctor
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Specialty</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['specialty'] ?? 'General Practitioner') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['doctor_phone'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <a href="view_patient.php?id=<?= $appointment['patient_id'] ?>" class="card text-center hover:border-primary transition">
            <i class="fas fa-user text-primary text-2xl block mb-2"></i>
            <span class="text-sm font-medium text-gray-700">View Patient Profile</span>
        </a>
        <a href="new_appointment.php?patient_id=<?= $appointment['patient_id'] ?>" class="card text-center hover:border-primary transition">
            <i class="fas fa-calendar-plus text-green-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium text-gray-700">New Appointment</span>
        </a>
        <a href="assign_doctor.php?patient_id=<?= $appointment['patient_id'] ?>" class="card text-center hover:border-primary transition">
            <i class="fas fa-user-md text-purple-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium text-gray-700">Assign Doctor</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Appointment Details
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
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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

    console.log('%c📅 Braick - View Appointment', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Appointment ID: <?= $appointment['id'] ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($appointment['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($appointment['doctor_name']) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>