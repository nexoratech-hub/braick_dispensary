<?php
// ================================================================
// FILE: frontend/pages/reception/new_appointment.php
// RECEPTION - NEW APPOINTMENT (NO VISIT CREATION)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
$_SESSION['user_id'] = 6;
$_SESSION['full_name'] = 'Rose Mwangi';
$_SESSION['role'] = 'reception';
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Dodoma';
$_SESSION['username'] = 'reception.rose';
$_SESSION['is_admin'] = false;

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$message = '';
$message_type = '';

try {
    $db = getDB();
    
    // Get patients in this branch
    $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE branch_id = ? ORDER BY full_name");
    $stmt->execute([$selected_branch_id]);
    $patients = $stmt->fetchAll();
    
    // Get doctors in this branch
    $stmt = $db->prepare("SELECT id, full_name, specialty FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY full_name");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
    // ================================================================
    // HANDLE FORM SUBMISSION - ONLY APPOINTMENT, NO VISIT
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $purpose = trim($_POST['purpose'] ?? '');
        $status = $_POST['status'] ?? 'scheduled';
        
        // Validation
        $errors = [];
        if ($patient_id <= 0) $errors[] = 'Please select a patient';
        if ($doctor_id <= 0) $errors[] = 'Please select a doctor';
        if (empty($appointment_date)) $errors[] = 'Please select a date';
        if (empty($appointment_time)) $errors[] = 'Please select a time';
        
        if (empty($errors)) {
            $datetime = $appointment_date . ' ' . $appointment_time . ':00';
            
            // ================================================================
            // ONLY INSERT APPOINTMENT - NO VISIT
            // ================================================================
            $stmt = $db->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, purpose, status, branch_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$patient_id, $doctor_id, $datetime, $purpose, $status, $selected_branch_id, $_SESSION['user_id']])) {
                $appt_id = $db->lastInsertId();
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'appointment_created', ?)");
                    $stmt->execute([$_SESSION['user_id'], "New appointment created for patient ID: $patient_id with doctor ID: $doctor_id"]);
                } catch (Exception $e) {}
                
                $message = "Appointment scheduled successfully!";
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "appointments.php?date=' . $appointment_date . '"; 
                    }, 1500);
                </script>';
                
            } else {
                $message = "Failed to schedule appointment!";
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patients = [];
    $doctors = [];
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!-- Rest of HTML stays the same -->

<style>
    .form-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .form-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    .form-label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    .form-row {
        margin-bottom: 14px;
    }
    .form-row:last-child {
        margin-bottom: 0;
    }
    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
        flex-wrap: wrap;
    }
    select.form-control {
        appearance: auto;
        cursor: pointer;
    }
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
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
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
                <i class="fas fa-calendar-plus mr-2" style="color: var(--primary);"></i> New Appointment
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Schedule a new appointment in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user-md mr-1"></i> <?= count($doctors) ?> doctors available
                </span>
            </p>
        </div>
        <div>
            <a href="appointments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200' : 'bg-red-100 text-red-700 border border-red-200') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- APPOINTMENT FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Patient -->
                <div class="form-row">
                    <label class="form-label">Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>" <?= $patient_id == $patient['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($patients)): ?>
                        <p class="text-xs text-gray-400 mt-1">No patients registered. <a href="new_patient.php" class="text-primary">Register a patient</a></p>
                    <?php endif; ?>
                </div>
                
                <!-- Doctor -->
                <div class="form-row">
                    <label class="form-label">Doctor <span class="required">*</span></label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= $doctor['id'] ?>">
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?> 
                                <?php if (!empty($doctor['specialty'])): ?>
                                    (<?= htmlspecialchars($doctor['specialty']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($doctors)): ?>
                        <p class="text-xs text-gray-400 mt-1">No doctors available.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Date -->
                <div class="form-row">
                    <label class="form-label">Date <span class="required">*</span></label>
                    <input type="date" name="appointment_date" class="form-control" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <!-- Time -->
                <div class="form-row">
                    <label class="form-label">Time <span class="required">*</span></label>
                    <input type="time" name="appointment_time" class="form-control" 
                           value="09:00" required>
                </div>
                
                <!-- Visit Type -->
                <div class="form-row">
                    <label class="form-label">Visit Type</label>
                    <select name="visit_type" class="form-control">
                        <option value="new">New Patient</option>
                        <option value="follow-up">Follow-up</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                
                <!-- Status -->
                <div class="form-row">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                
                <!-- Purpose -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose" class="form-control" placeholder="Reason for appointment..."></textarea>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-green">
                    <i class="fas fa-save"></i> Schedule Appointment
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="appointments.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK INFO -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <div class="card text-center">
            <p class="text-2xl font-bold text-primary"><?= count($patients) ?></p>
            <p class="text-sm text-gray-500">Patients Available</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-green-600"><?= count($doctors) ?></p>
            <p class="text-sm text-gray-500">Doctors Available</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-purple-600"><?= date('M d, Y') ?></p>
            <p class="text-sm text-gray-500">Today's Date</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            New Appointment
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

    console.log('%c📅 Braick - New Appointment (Fixed - Creates Visit)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Patients: <?= count($patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctors: <?= count($doctors) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Appointment + Visit created together', 'font-size:13px; color:#059669;');
</script>

</body>
</html>