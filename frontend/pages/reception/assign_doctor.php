<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN DOCTOR TO PATIENT (BRANCH FILTERED)
// WITH AJAX REAL-TIME UPDATE - FIXED
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

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$message = '';
$message_type = '';

// Initialize variables as empty arrays to avoid count() errors
$patients = [];
$doctors = [];
$pending_patients = [];

try {
    $db = getDB();
    
    // Get patients in this branch without assigned doctor (or with pending visits)
    $stmt = $db->prepare("
        SELECT p.* 
        FROM patients p
        WHERE p.branch_id = ? 
        AND p.id NOT IN (
            SELECT patient_id FROM visits 
            WHERE status IN ('pending', 'assigned', 'with_doctor') 
            AND branch_id = ?
        )
        ORDER BY p.full_name
    ");
    $stmt->execute([$selected_branch_id, $selected_branch_id]);
    $patients = $stmt->fetchAll();
    
    // Get doctors in this branch
    $stmt = $db->prepare("
        SELECT id, full_name, specialty 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
    // Get pending patients (already assigned but not completed)
    $stmt = $db->prepare("
        SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone, u.full_name as doctor_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.branch_id = ? AND v.status IN ('pending', 'assigned')
        ORDER BY v.created_at ASC
        LIMIT 20
    ");
    $stmt->execute([$selected_branch_id]);
    $pending_patients = $stmt->fetchAll();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $visit_type = $_POST['visit_type'] ?? 'new';
        $symptoms = trim($_POST['symptoms'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if ($patient_id <= 0) $errors[] = 'Please select a patient';
        if ($doctor_id <= 0) $errors[] = 'Please select a doctor';
        
        if (empty($errors)) {
            // Generate visit number
            $visit_number = 'V-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, symptoms, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, NOW(), NOW())
            ");
            
            if ($stmt->execute([$visit_number, $patient_id, $doctor_id, $selected_branch_id, $visit_type, $symptoms, $notes])) {
                $message = "Doctor assigned successfully! Visit #$visit_number";
                $message_type = 'success';
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'doctor_assigned', ?)");
                    $stmt->execute([$_SESSION['user_id'], "Doctor assigned to patient ID: $patient_id in $branch_name"]);
                } catch (Exception $e) {}
                
                // Refresh lists
                $stmt = $db->prepare("
                    SELECT p.* 
                    FROM patients p
                    WHERE p.branch_id = ? 
                    AND p.id NOT IN (
                        SELECT patient_id FROM visits 
                        WHERE status IN ('pending', 'assigned', 'with_doctor') 
                        AND branch_id = ?
                    )
                    ORDER BY p.full_name
                ");
                $stmt->execute([$selected_branch_id, $selected_branch_id]);
                $patients = $stmt->fetchAll();
                
                $stmt = $db->prepare("
                    SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone, u.full_name as doctor_name
                    FROM visits v
                    JOIN patients p ON v.patient_id = p.id
                    LEFT JOIN users u ON v.doctor_id = u.id
                    WHERE v.branch_id = ? AND v.status IN ('pending', 'assigned')
                    ORDER BY v.created_at ASC
                    LIMIT 20
                ");
                $stmt->execute([$selected_branch_id]);
                $pending_patients = $stmt->fetchAll();
                
                echo '<script>setTimeout(function(){ window.location.href = "assign_doctor.php?success=1"; }, 1500);</script>';
            } else {
                $message = "Failed to assign doctor!";
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
    // Keep arrays as empty arrays
    $patients = [];
    $doctors = [];
    $pending_patients = [];
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    /* ================================================================
       ASSIGN DOCTOR STYLES
       ================================================================ */
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
    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }
    
    .pending-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .pending-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .pending-card .name {
        font-weight: 500;
        font-size: 0.9rem;
        color: var(--text-primary);
    }
    .pending-card .id {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .pending-card .doctor {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .pending-card .status-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .pending-card .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .pending-card .status-badge.assigned { background: #E8F0FE; color: #0B5ED7; }
    
    [data-theme="dark"] .pending-card .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .pending-card .status-badge.assigned { background: #1E3A5F; color: #6EA8FE; }
    
    .pending-list {
        max-height: 300px;
        overflow-y: auto;
    }
    .pending-list::-webkit-scrollbar {
        width: 4px;
    }
    .pending-list::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    .pending-list::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
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
    
    /* FIX: Safe count display */
    .badge-count {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
    }
    .badge-count.green {
        background: var(--success-bg);
        color: var(--success);
    }
    .badge-count.orange {
        background: var(--warning-bg);
        color: var(--warning);
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
                <i class="fas fa-user-md mr-2" style="color: var(--primary);"></i> Assign Doctor
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Assign a doctor to a patient in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user-md mr-1"></i> <?= is_array($doctors) ? count($doctors) : 0 ?> doctors available
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= is_array($patients) ? count($patients) : 0 ?> patients waiting
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
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
    <!-- PENDING PATIENTS - FIXED count() -->
    <!-- ================================================================ -->
    <?php if (!empty($pending_patients) && is_array($pending_patients) && count($pending_patients) > 0): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock title-orange mr-2"></i> Currently Assigned / Pending
                <span class="text-sm font-normal text-gray-400">(<?= count($pending_patients) ?> patients)</span>
            </h3>
        </div>
        <div class="pending-list">
            <?php foreach ($pending_patients as $patient): ?>
                <div class="pending-card">
                    <div>
                        <p class="name"><?= htmlspecialchars($patient['patient_name']) ?></p>
                        <p class="id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?> • <?= htmlspecialchars($patient['phone'] ?? 'No phone') ?></p>
                        <p class="doctor">Dr. <?= htmlspecialchars($patient['doctor_name'] ?? 'Not assigned') ?></p>
                    </div>
                    <div class="text-right">
                        <span class="status-badge <?= $patient['status'] ?>"><?= ucfirst($patient['status']) ?></span>
                        <div class="mt-1">
                            <a href="visit_details.php?id=<?= $patient['id'] ?>" class="text-primary text-xs hover:underline">View</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ASSIGN FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <div class="flex items-center gap-3 mb-4">
            <i class="fas fa-stethoscope text-2xl text-primary"></i>
            <h3 class="text-lg font-semibold text-gray-800">Assign Doctor to Patient</h3>
        </div>
        
        <form method="POST" action="" id="assignForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Patient -->
                <div class="form-row">
                    <label class="form-label">Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required id="patientSelect">
                        <option value="">Select Patient</option>
                        <?php if (!empty($patients) && is_array($patients)): ?>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= $patient['id'] ?>" <?= ($_GET['patient_id'] ?? 0) == $patient['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($patients) || !is_array($patients) || count($patients) == 0): ?>
                        <p class="text-xs text-gray-400 mt-1">All patients have been assigned a doctor.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Doctor -->
                <div class="form-row">
                    <label class="form-label">Doctor <span class="required">*</span></label>
                    <select name="doctor_id" class="form-control" required id="doctorSelect">
                        <option value="">Select Doctor</option>
                        <?php if (!empty($doctors) && is_array($doctors)): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>">
                                    Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                    <?php if (!empty($doctor['specialty'])): ?>
                                        (<?= htmlspecialchars($doctor['specialty']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($doctors) || !is_array($doctors) || count($doctors) == 0): ?>
                        <p class="text-xs text-gray-400 mt-1">No doctors available.</p>
                    <?php endif; ?>
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
                
                <!-- Symptoms -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" placeholder="Describe patient symptoms..." id="symptomsInput"></textarea>
                </div>
                
                <!-- Notes -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Any additional notes..." id="notesInput"></textarea>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-blue" <?= (empty($patients) || !is_array($patients) || count($patients) == 0 || empty($doctors) || !is_array($doctors) || count($doctors) == 0) ? 'disabled' : '' ?> id="assignBtn">
                    <i class="fas fa-user-md"></i> Assign Doctor
                </button>
                <button type="reset" class="btn btn-outline" id="resetBtn">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS - FIXED count() -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <div class="card text-center">
            <p class="text-2xl font-bold text-primary"><?= (is_array($patients) ? count($patients) : 0) ?></p>
            <p class="text-sm text-gray-500">Patients Waiting</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-green-600"><?= (is_array($doctors) ? count($doctors) : 0) ?></p>
            <p class="text-sm text-gray-500">Doctors Available</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-orange-500"><?= (is_array($pending_patients) ? count($pending_patients) : 0) ?></p>
            <p class="text-sm text-gray-500">In Progress</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Assign Doctor
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

    console.log('%c👨‍⚕️ Braick - Assign Doctor (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Patients Waiting: <?= (is_array($patients) ? count($patients) : 0) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctors Available: <?= (is_array($doctors) ? count($doctors) : 0) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c⏳ In Progress: <?= (is_array($pending_patients) ? count($pending_patients) : 0) ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>