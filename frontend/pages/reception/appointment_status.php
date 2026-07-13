<?php
// ================================================================
// FILE: frontend/pages/reception/appointment_status.php
// RECEPTION - UPDATE APPOINTMENT STATUS
// WITH AJAX REAL-TIME UPDATE
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
$new_status = isset($_GET['status']) ? $_GET['status'] : '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'appointments.php';
$message = '';
$message_type = '';

if ($appointment_id <= 0) {
    header('Location: ' . $redirect);
    exit;
}

try {
    $db = getDB();
    
    // Get appointment details first
    $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        header('Location: ' . $redirect);
        exit;
    }
    
    // Get patient and doctor names
    $stmt = $db->prepare("SELECT full_name FROM patients WHERE id = ?");
    $stmt->execute([$appointment['patient_id']]);
    $patient = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$appointment['doctor_id']]);
    $doctor = $stmt->fetch();
    
    // Validate status
    $valid_statuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        header('Location: ' . $redirect);
        exit;
    }
    
    // Update appointment status
    $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    
    if ($stmt->execute([$new_status, $appointment_id])) {
        $message = "Appointment status updated to: " . ucfirst($new_status);
        $message_type = 'success';
        
        // If status is completed, also update visit status if exists
        if ($new_status === 'completed') {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'completed', updated_at = NOW() 
                WHERE patient_id = ? AND doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor')
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$appointment['patient_id'], $appointment['doctor_id']]);
        }
        
        // Log activity
        try {
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'appointment_status_updated', ?)");
            $stmt->execute([$_SESSION['user_id'], "Appointment ID: $appointment_id status changed to $new_status"]);
        } catch (Exception $e) {}
        
    } else {
        $message = "Failed to update appointment status!";
        $message_type = 'error';
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .status-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 32px;
        border: 2px solid var(--border-color);
        text-align: center;
        max-width: 600px;
        margin: 0 auto;
    }
    .status-card .status-icon {
        font-size: 4rem;
        margin-bottom: 16px;
    }
    .status-card .status-icon.success { color: #059669; }
    .status-card .status-icon.error { color: #DC2626; }
    .status-card .status-icon.info { color: #0B5ED7; }
    
    .status-card .status-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    .status-card .status-message {
        font-size: 0.95rem;
        color: var(--text-secondary);
        margin: 8px 0 16px;
    }
    .status-card .status-details {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px;
        text-align: left;
        margin: 16px 0;
    }
    .status-card .status-details .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }
    .status-card .status-details .detail-row:last-child {
        border-bottom: none;
    }
    .status-card .status-details .detail-label {
        color: var(--text-secondary);
        font-weight: 500;
    }
    .status-card .status-details .detail-value {
        color: var(--text-primary);
        font-weight: 600;
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
        <span class="branch-badge-display" style="display:inline-block;font-size:0.6rem;font-weight:600;padding:2px 10px;border-radius:20px;background:var(--success-bg);color:var(--success);">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name ?? 'Dodoma') ?>
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
                <i class="fas fa-calendar-check mr-2" style="color: var(--primary);"></i> Appointment Status
                <span class="role-badge-display ml-2" style="display:inline-block;font-size:0.6rem;font-weight:600;padding:2px 10px;border-radius:20px;background:var(--primary-bg);color:var(--primary);text-transform:uppercase;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Update appointment status
            </p>
        </div>
        <div>
            <a href="<?= $redirect ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATUS CARD -->
    <!-- ================================================================ -->
    <div class="status-card">
        <div class="status-icon <?= $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'error' : 'info') ?>">
            <?php if ($message_type === 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php elseif ($message_type === 'error'): ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php else: ?>
                <i class="fas fa-calendar-check"></i>
            <?php endif; ?>
        </div>
        
        <h2 class="status-title">
            <?php if ($message_type === 'success'): ?>
                Status Updated Successfully!
            <?php elseif ($message_type === 'error'): ?>
                Update Failed
            <?php else: ?>
                Appointment Details
            <?php endif; ?>
        </h2>
        
        <p class="status-message"><?= htmlspecialchars($message) ?></p>
        
        <!-- Appointment Details -->
        <?php if (isset($appointment)): ?>
        <div class="status-details">
            <div class="detail-row">
                <span class="detail-label">Appointment ID</span>
                <span class="detail-value">#<?= $appointment['id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Patient</span>
                <span class="detail-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Doctor</span>
                <span class="detail-value">Dr. <?= htmlspecialchars($doctor['full_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date & Time</span>
                <span class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['appointment_date'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Purpose</span>
                <span class="detail-value"><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge-display <?= $new_status ?? $appointment['status'] ?>">
                        <?= ucfirst($new_status ?? $appointment['status']) ?>
                    </span>
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-2 justify-center mt-4">
            <a href="<?= $redirect ?>" class="btn btn-blue">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <a href="appointments.php" class="btn btn-outline">
                <i class="fas fa-calendar-check"></i> View All Appointments
            </a>
            <?php if ($message_type === 'success'): ?>
                <a href="view_patient.php?id=<?= $appointment['patient_id'] ?? 0 ?>" class="btn btn-green">
                    <i class="fas fa-user"></i> View Patient
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Appointment Status
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

    console.log('%c📅 Braick - Appointment Status', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Appointment ID: <?= $appointment_id ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 New Status: <?= ucfirst($new_status ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>