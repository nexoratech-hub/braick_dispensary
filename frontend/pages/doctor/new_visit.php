<?php
// ================================================================
// FILE: frontend/pages/doctor/new_visit.php
// DOCTOR - NEW VISIT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("new_visit verification error: " . $e->getMessage());
}

// ================================================================
// GET PATIENT LIST FOR SELECTION
// ================================================================
$patients = [];
try {
    $stmt = $db->prepare("
        SELECT p.* FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        GROUP BY p.id
        ORDER BY p.full_name
    ");
    $stmt->execute([$doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_visit'])) {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $visit_type = trim($_POST['visit_type'] ?? 'follow-up');
    $symptoms = trim($_POST['symptoms'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    
    if ($patient_id <= 0) {
        $errors[] = "Please select a patient";
    }
    if (empty($visit_type)) {
        $errors[] = "Please select visit type";
    }
    
    // Verify patient belongs to this doctor
    if ($patient_id > 0) {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE patient_id = ? AND doctor_id = ?
            ");
            $stmt->execute([$patient_id, $doctor_id]);
            $check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If patient not assigned to this doctor, try to assign them
            if (($check['count'] ?? 0) == 0) {
                // Check if patient exists
                $stmt = $db->prepare("SELECT id FROM patients WHERE id = ?");
                $stmt->execute([$patient_id]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = "Patient not found";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Error verifying patient: " . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        try {
            // Generate visit number
            $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insert visit
            $stmt = $db->prepare("
                INSERT INTO visits (
                    visit_number, visit_date, patient_id, doctor_id, receptionist_id,
                    branch_id, visit_type, status, symptoms, complaint,
                    diagnosis, treatment, notes, created_at
                ) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
            ");
            
            $receptionist_id = null; // Doctor creating visit directly
            
            $stmt->execute([
                $visit_number,
                $patient_id,
                $doctor_id,
                $receptionist_id,
                $doctor_branch_id,
                $visit_type,
                $symptoms,
                $symptoms, // complaint (same as symptoms for now)
                $diagnosis,
                $diagnosis, // treatment (same as diagnosis for now)
                $notes
            ]);
            
            $visit_id = $db->lastInsertId();
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at) 
                    VALUES (?, 'visit_created', ?, NOW())
                ");
                $stmt->execute([
                    $doctor_id,
                    "New visit created: $visit_number for patient ID: $patient_id"
                ]);
            } catch (Exception $e) {}
            
            // Redirect to consultation
            $message = "✅ Visit created successfully!";
            $message_type = 'success';
            
            echo '<script>
                setTimeout(function() {
                    window.location.href = "consultation.php?visit_id=' . $visit_id . '";
                }, 1000);
            </script>';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2" style="color: #0B5ED7;"></i> New Visit
            </h1>
            <p class="page-subtitle">
                Start a new patient visit
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
            </p>
        </div>
        <a href="my_patients.php" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Patients
        </a>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card max-w-2xl mx-auto">
        <form method="POST" action="" id="visitForm">
            <input type="hidden" name="save_visit" value="1">
            
            <div class="space-y-4">
                <!-- Patient Selection -->
                <div>
                    <label class="form-label">Select Patient <span class="text-red-500">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>">
                                <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        <a href="new_patient.php" class="text-blue-600 hover:underline">+ Add New Patient</a>
                    </p>
                    <?php if (empty($patients)): ?>
                        <p class="text-xs text-yellow-600 mt-1">
                            <i class="fas fa-info-circle"></i> No patients found. Please add a new patient first.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Visit Type -->
                <div>
                    <label class="form-label">Visit Type <span class="text-red-500">*</span></label>
                    <select name="visit_type" class="form-control" required>
                        <option value="new">New Patient</option>
                        <option value="follow-up" selected>Follow-up</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>

                <!-- Symptoms -->
                <div>
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="3" placeholder="Describe the patient's symptoms..."></textarea>
                </div>

                <!-- Diagnosis -->
                <div>
                    <label class="form-label">Diagnosis (Optional)</label>
                    <textarea name="diagnosis" class="form-control" rows="3" placeholder="Enter diagnosis..."></textarea>
                </div>

                <!-- Notes -->
                <div>
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="btn btn-blue flex-1" <?= empty($patients) ? 'disabled' : '' ?>>
                        <i class="fas fa-save"></i> Create Visit
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <a href="my_patients.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            New Visit
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ROOT VARIABLES
       ================================================================ */
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #6EA8FE;
        --primary-bg: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #34D399;
        --success-bg: #D1FAE5;
        --danger: #DC2626;
        --danger-dark: #B91C1C;
        --danger-light: #F87171;
        --danger-bg: #FEE2E2;
        --warning: #D97706;
        --warning-bg: #FEF3C7;
        --white: #FFFFFF;
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }
    
    /* ================================================================
       BASE
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    
    .page-title {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-title {
        color: var(--primary-light);
    }
    
    .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* ================================================================
       CARD
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .max-w-2xl { max-width: 42rem; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    
    /* ================================================================
       FORM
       ================================================================ */
    .space-y-4 > * + * { margin-top: 16px; }
    
    .form-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    select.form-control {
        appearance: auto;
        cursor: pointer;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }
    
    .text-red-500 { color: #EF4444; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-secondary); }
    .text-yellow-600 { color: #D97706; }
    .text-blue-600 { color: var(--primary); }
    .mt-1 { margin-top: 4px; }
    
    /* ================================================================
       ALERT
       ================================================================ */
    .alert {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }
    .alert-success { background: #D1FAE5; color: #059669; border-color: #059669; }
    .alert-error { background: #FEE2E2; color: #DC2626; border-color: #DC2626; }
    .alert-warning { background: #FEF3C7; color: #D97706; border-color: #D97706; }
    .alert-info { background: #E8F0FE; color: #0B5ED7; border-color: #0B5ED7; }
    
    [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
    [data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: transparent;
        min-height: 44px;
    }
    
    .btn-blue {
        background: var(--primary);
        color: white;
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-blue:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.75rem;
        border-radius: 8px;
        min-height: 32px;
    }
    
    .flex { display: flex; }
    .flex-1 { flex: 1; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-3 { gap: 12px; }
    .gap-2 { gap: 8px; }
    .pt-4 { padding-top: 16px; }
    .border-t { border-top: 2px solid var(--border-color); }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    .text-gray-300 { color: #D1D5DB; }
    .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .card:hover {
        border-color: #6EA8FE;
    }
    [data-theme="dark"] .form-control {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    [data-theme="dark"] .form-control:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15);
    }
    [data-theme="dark"] .page-subtitle {
        color: #94A3B8;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #94A3B8;
    }
    [data-theme="dark"] .text-yellow-600 {
        color: #FBBF24;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 12px;
        }
        .page-title {
            font-size: 1.3rem;
        }
        .card {
            padding: 16px 18px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .flex.gap-3.pt-4 {
            flex-direction: column;
        }
        .flex.gap-3.pt-4 .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 8px; }
        .page-title { font-size: 1.1rem; }
        .card { padding: 12px 14px; }
        .form-control {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        .btn {
            padding: 8px 16px;
            font-size: 0.8rem;
            min-height: 38px;
        }
        .page-subtitle {
            font-size: 0.8rem;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 20px !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
        .page-header {
            border-bottom: 2px solid #0B5ED7 !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('visitForm')?.addEventListener('submit', function(e) {
        var patientSelect = document.querySelector('select[name="patient_id"]');
        if (!patientSelect || patientSelect.value === '') {
            e.preventDefault();
            alert('Please select a patient');
            patientSelect.focus();
            return false;
        }
        
        var submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c👨‍⚕️ New Visit - Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Patients available: <?= count($patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💡 New visit will redirect to consultation page', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>