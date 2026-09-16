<?php
// ================================================================
// FILE: frontend/pages/admin/add_appointment.php
// SUPER ADMIN - ADD NEW APPOINTMENT
// ✅ BLUE THEME ONLY
// ✅ Select patient, doctor, date, time
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH FROM URL
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $current_branch_name_display = $branch_data['name'];
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PATIENTS
// ================================================================
$patients = [];
$patient_sql = "
    SELECT p.id, p.full_name, p.patient_id, p.phone, p.gender, p.date_of_birth, p.branch_id
    FROM patients p
    WHERE 1=1
";
$patient_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $patient_sql .= " AND p.branch_id = ?";
    $patient_params[] = (int)$selected_branch_id;
}
$patient_sql .= " ORDER BY p.full_name ASC LIMIT 500";

$stmt = $db->prepare($patient_sql);
$stmt->execute($patient_params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DOCTORS
// ================================================================
$doctors = [];
$doctor_sql = "
    SELECT u.id, u.full_name, u.specialty, u.phone, u.email, u.branch_id, u.is_online
    FROM users u
    WHERE u.role = 'doctor' AND u.status = 'active'
";
$doctor_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $doctor_sql .= " AND u.branch_id = ?";
    $doctor_params[] = (int)$selected_branch_id;
}
$doctor_sql .= " ORDER BY u.is_online DESC, u.full_name ASC";

$stmt = $db->prepare($doctor_sql);
$stmt->execute($doctor_params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $duration = (int)($_POST['duration'] ?? 30);
    $purpose = trim($_POST['purpose'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'scheduled';
    $branch_id_post = (int)($_POST['branch_id'] ?? $user_branch_id);
    $appointment_type = $_POST['appointment_type'] ?? 'consultation';
    
    $errors = [];
    
    if ($patient_id <= 0) $errors[] = "Please select a patient";
    if ($doctor_id <= 0) $errors[] = "Please select a doctor";
    if (empty($appointment_date)) $errors[] = "Please select a date";
    if (empty($appointment_time)) $errors[] = "Please select a time";
    
    // Check if date is in the past
    $datetime = $appointment_date . ' ' . $appointment_time . ':00';
    if (strtotime($datetime) < time()) {
        $errors[] = "Appointment date/time cannot be in the past";
    }
    
    if (empty($errors)) {
        try {
            // Check for conflicting appointments for the doctor
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM appointments 
                WHERE doctor_id = ? 
                AND appointment_date = ? 
                AND status NOT IN ('cancelled', 'completed')
            ");
            $stmt->execute([$doctor_id, $datetime]);
            $conflict = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conflict['count'] > 0) {
                $errors[] = "⚠️ Doctor already has an appointment at this time. Please choose a different time.";
            }
        } catch (Exception $e) {
            // Continue - table might not have all columns
        }
    }
    
    if (empty($errors)) {
        try {
            // Check which columns exist in the table
            $columns_check = $db->query("SHOW COLUMNS FROM appointments");
            $existing_columns = [];
            while ($col = $columns_check->fetch(PDO::FETCH_ASSOC)) {
                $existing_columns[] = $col['Field'];
            }
            
            // Build dynamic INSERT based on existing columns
            $insert_columns = ['patient_id', 'doctor_id', 'appointment_date', 'status', 'branch_id', 'created_at'];
            $insert_values = [$patient_id, $doctor_id, $datetime, $status, $branch_id_post, date('Y-m-d H:i:s')];
            
            // Add optional columns if they exist
            $optional_columns = [
                'purpose' => $purpose,
                'notes' => $notes,
                'duration_minutes' => $duration,
                'duration' => $duration,
                'appointment_type' => $appointment_type,
                'visit_type' => $appointment_type,
                'created_by' => $user_id,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            foreach ($optional_columns as $col_name => $col_value) {
                if (in_array($col_name, $existing_columns) && !in_array($col_name, $insert_columns)) {
                    $insert_columns[] = $col_name;
                    $insert_values[] = $col_value;
                }
            }
            
            $placeholders = implode(', ', array_fill(0, count($insert_columns), '?'));
            $columns_str = implode(', ', $insert_columns);
            
            $sql = "INSERT INTO appointments ($columns_str) VALUES ($placeholders)";
            $stmt = $db->prepare($sql);
            $stmt->execute($insert_values);
            
            $appointment_id = $db->lastInsertId();
            
            // Log activity
            try {
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'appointment_created', ?, NOW())
                ");
                $log_stmt->execute([$user_id, $branch_id_post, "Created appointment #$appointment_id"]);
            } catch (Exception $e) {}
            
            // Create notification for the doctor
            try {
                $notif_stmt = $db->prepare("
                    INSERT INTO notifications (user_id, branch_id, title, message, type, created_at) 
                    VALUES (?, ?, ?, ?, 'info', NOW())
                ");
                $patient_name = '';
                foreach ($patients as $p) {
                    if ($p['id'] == $patient_id) {
                        $patient_name = $p['full_name'];
                        break;
                    }
                }
                $notif_stmt->execute([
                    $doctor_id,
                    $branch_id_post,
                    "📅 New Appointment Scheduled",
                    "New appointment for patient: $patient_name on " . date('M d, Y h:i A', strtotime($datetime))
                ]);
            } catch (Exception $e) {}
            
            $message = "✅ Appointment created successfully!<br>";
            $message .= "📅 " . date('l, F d, Y \a\t h:i A', strtotime($datetime)) . "<br>";
            $message .= "👤 Patient: " . htmlspecialchars($patient_name) . "<br>";
            $message_type = 'success';
            
            // Redirect after 3 seconds
            echo '<script>setTimeout(function(){ window.location.href = "appointments.php?branch=' . $selected_branch_id . '"; }, 2500);</script>';
            
        } catch (Exception $e) {
            $message = "❌ Failed to create appointment: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>❌ ', $errors);
        $message_type = 'error';
    }
}

// Set default datetime
$default_date = date('Y-m-d');
$default_time = date('H:i', strtotime('+1 hour'));

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================
   BLUE THEME ONLY
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
}

/* PAGE HEADER - BLUE GRADIENT */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
}

.page-header-custom .page-title i {
    color: rgba(255,255,255,0.85);
}

.page-header-custom .badge-branch {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.page-header-custom .btn-back {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

.page-header-custom .btn-back:hover {
    background: rgba(255,255,255,0.25);
    transform: translateX(-3px);
}

/* FORM CARD */
.form-card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 28px 32px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.form-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 24px rgba(11, 94, 215, 0.08);
}

.form-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 2px dashed var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 16px;
}

.section-title i {
    width: 32px;
    height: 32px;
    background: var(--primary-bg);
    color: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* FORM ROWS */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.form-label .required {
    color: #DC2626;
    margin-left: 2px;
}

.form-label .optional {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    font-family: inherit;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
}

.form-control:hover {
    border-color: var(--primary-light);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

select.form-control {
    cursor: pointer;
    appearance: auto;
}

/* PATIENT/DOCTOR INFO PREVIEW */
.info-preview {
    padding: 12px 16px;
    background: var(--primary-bg);
    border-radius: 10px;
    border-left: 4px solid var(--primary);
    margin-top: 10px;
    font-size: 0.8rem;
    display: none;
}

.info-preview.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.info-preview .info-row {
    display: flex;
    gap: 8px;
    padding: 2px 0;
}

.info-preview .info-label {
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 80px;
}

.info-preview .info-value {
    color: var(--text-primary);
    font-weight: 500;
}

/* FORM ACTIONS */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    min-height: 42px;
}

.btn-primary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* MESSAGE BOX */
.message-box {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-weight: 500;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.message-box.success {
    background: #D1FAE5;
    color: #065F46;
    border: 2px solid #6EE7B7;
}

.message-box.error {
    background: #FEE2E2;
    color: #991B1B;
    border: 2px solid #FCA5A5;
}

[data-theme="dark"] .message-box.success {
    background: #1A3A2A;
    color: #34D399;
    border-color: #34D399;
}

[data-theme="dark"] .message-box.error {
    background: #3A1A1A;
    color: #F87171;
    border-color: #F87171;
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 768px) {
    .form-grid-2,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }
    .form-card {
        padding: 18px 20px;
    }
    .form-actions {
        flex-direction: column-reverse;
    }
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
    .page-header-custom .page-title {
        font-size: 1.2rem;
    }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-plus"></i> Add New Appointment
                <span class="badge-branch"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?></span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                Schedule a new appointment for a patient
            </p>
        </div>
        <a href="appointments.php?branch=<?= $selected_branch_id ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Appointments
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="form-card">
        <form method="POST" action="" id="appointmentForm">
            
            <!-- SECTION 1: PATIENT & DOCTOR -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-user-md"></i>
                    <span>Patient & Doctor Selection</span>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">
                            Patient <span class="required">*</span>
                        </label>
                        <select name="patient_id" class="form-control" id="patientSelect" required>
                            <option value="">-- Select Patient --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>" 
                                        data-phone="<?= htmlspecialchars($p['phone'] ?? '') ?>"
                                        data-code="<?= htmlspecialchars($p['patient_id'] ?? '') ?>"
                                        data-gender="<?= htmlspecialchars($p['gender'] ?? '') ?>"
                                        data-dob="<?= htmlspecialchars($p['date_of_birth'] ?? '') ?>">
                                    <?= htmlspecialchars($p['full_name']) ?> 
                                    (<?= htmlspecialchars($p['patient_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="info-preview" id="patientInfo">
                            <div class="info-row"><span class="info-label">Patient ID:</span><span class="info-value" id="pInfoCode">-</span></div>
                            <div class="info-row"><span class="info-label">Phone:</span><span class="info-value" id="pInfoPhone">-</span></div>
                            <div class="info-row"><span class="info-label">Gender:</span><span class="info-value" id="pInfoGender">-</span></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Doctor <span class="required">*</span>
                        </label>
                        <select name="doctor_id" class="form-control" id="doctorSelect" required>
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['id'] ?>" 
                                        data-specialty="<?= htmlspecialchars($d['specialty'] ?? '') ?>"
                                        data-online="<?= $d['is_online'] ?? 0 ?>">
                                    <?= $d['is_online'] ? '🟢' : '⚪' ?> Dr. <?= htmlspecialchars($d['full_name']) ?> 
                                    <?= !empty($d['specialty']) ? '(' . htmlspecialchars($d['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="info-preview" id="doctorInfo">
                            <div class="info-row"><span class="info-label">Specialty:</span><span class="info-value" id="dInfoSpecialty">-</span></div>
                            <div class="info-row"><span class="info-label">Status:</span><span class="info-value" id="dInfoStatus">-</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: DATE & TIME -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-clock"></i>
                    <span>Date & Time</span>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">
                            Appointment Date <span class="required">*</span>
                        </label>
                        <input type="date" name="appointment_date" class="form-control" 
                               id="apptDate" value="<?= $default_date ?>" 
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Appointment Time <span class="required">*</span>
                        </label>
                        <input type="time" name="appointment_time" class="form-control" 
                               id="apptTime" value="<?= $default_time ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Duration <span class="optional">(minutes)</span>
                        </label>
                        <select name="duration" class="form-control">
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1 hour</option>
                            <option value="90">1.5 hours</option>
                            <option value="120">2 hours</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: APPOINTMENT DETAILS -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Appointment Details</span>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">
                            Appointment Type
                        </label>
                        <select name="appointment_type" class="form-control">
                            <option value="consultation">🩺 Consultation</option>
                            <option value="follow_up">🔄 Follow-up</option>
                            <option value="checkup">✅ Check-up</option>
                            <option value="emergency">🚨 Emergency</option>
                            <option value="procedure">💉 Procedure</option>
                            <option value="lab_review">🧪 Lab Review</option>
                            <option value="vaccination">💊 Vaccination</option>
                            <option value="other">📋 Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="scheduled" selected>📅 Scheduled</option>
                            <option value="confirmed">✅ Confirmed</option>
                            <option value="pending">⏳ Pending</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">
                            Purpose / Reason <span class="optional">(optional)</span>
                        </label>
                        <input type="text" name="purpose" class="form-control" 
                               placeholder="e.g., Regular checkup, Follow-up for malaria treatment..." maxlength="200">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">
                            Notes / Instructions <span class="optional">(optional)</span>
                        </label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Any additional notes or instructions for the patient..."></textarea>
                    </div>
                </div>
            </div>

            <!-- HIDDEN BRANCH -->
            <?php if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)): ?>
                <input type="hidden" name="branch_id" value="<?= (int)$selected_branch_id ?>">
            <?php else: ?>
                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">
                            Branch <span class="required">*</span>
                        </label>
                        <select name="branch_id" class="form-control" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $b['id'] == $user_branch_id ? 'selected' : '' ?>>
                                    🏥 <?= htmlspecialchars($b['name']) ?>
                                    <?= !empty($b['location']) ? ' - ' . htmlspecialchars($b['location']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <a href="appointments.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-calendar-plus"></i> Create Appointment
                </button>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Add Appointment
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// PATIENT INFO PREVIEW
// ================================================================
document.getElementById('patientSelect')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var preview = document.getElementById('patientInfo');
    
    if (this.value) {
        document.getElementById('pInfoCode').textContent = opt.dataset.code || '-';
        document.getElementById('pInfoPhone').textContent = opt.dataset.phone || '-';
        document.getElementById('pInfoGender').textContent = opt.dataset.gender || '-';
        preview.classList.add('show');
    } else {
        preview.classList.remove('show');
    }
});

// ================================================================
// DOCTOR INFO PREVIEW
// ================================================================
document.getElementById('doctorSelect')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var preview = document.getElementById('doctorInfo');
    
    if (this.value) {
        document.getElementById('dInfoSpecialty').textContent = opt.dataset.specialty || 'General Medicine';
        var isOnline = opt.dataset.online == '1';
        document.getElementById('dInfoStatus').innerHTML = isOnline 
            ? '<span style="color:#059669;font-weight:600;">🟢 Online</span>' 
            : '<span style="color:#94A3B8;font-weight:600;">⚪ Offline</span>';
        preview.classList.add('show');
    } else {
        preview.classList.remove('show');
    }
});

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('appointmentForm')?.addEventListener('submit', function(e) {
    var patient = document.getElementById('patientSelect').value;
    var doctor = document.getElementById('doctorSelect').value;
    var date = document.getElementById('apptDate').value;
    var time = document.getElementById('apptTime').value;
    
    if (!patient) {
        e.preventDefault();
        alert('⚠️ Please select a patient');
        return false;
    }
    if (!doctor) {
        e.preventDefault();
        alert('⚠️ Please select a doctor');
        return false;
    }
    if (!date) {
        e.preventDefault();
        alert('⚠️ Please select a date');
        return false;
    }
    if (!time) {
        e.preventDefault();
        alert('⚠️ Please select a time');
        return false;
    }
    
    // Check if date is in the past
    var selected = new Date(date + 'T' + time);
    if (selected < new Date()) {
        e.preventDefault();
        alert('⚠️ Appointment date/time cannot be in the past');
        return false;
    }
    
    // Show loading state
    var btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    btn.disabled = true;
    
    return true;
});

// ================================================================
// UPDATE FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c📅 Admin - Add Appointment (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Blue theme applied everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ Patient, Doctor, Date, Time selectors included', 'font-size:12px;color:#34D399;');
console.log('%c✅ Conflict detection for doctor appointments', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>