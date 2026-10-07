<?php
// ================================================================
// FILE: frontend/pages/admin/add_daily_activity.php
// ADMIN - ADD DAILY ACTIVITY
// ✅ Shared header + admin sidebar
// ✅ Admin anaweza ku-add kwa yeyote
// ✅ Employee anaweza ku-add yake mwenyewe
// ✅ AM/PM time picker (Start & End)
// ✅ Auto-calculate duration
// ✅ Optional: Patient & Visit linking
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

// ================================================================
// USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

// Pre-selected employee (from URL)
$preselected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_activity'])) {
    try {
        $activity_user_id = (int)($_POST['user_id'] ?? 0);
        $activity_date = trim($_POST['activity_date'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        $status = trim($_POST['status'] ?? 'completed');
        
        // Time From (AM/PM -> 24h)
        $time_from_h = $_POST['time_from_h'] ?? '12';
        $time_from_m = $_POST['time_from_m'] ?? '00';
        $time_from_ampm = $_POST['time_from_ampm'] ?? 'AM';
        $start_time = convertTo24h($time_from_h, $time_from_m, $time_from_ampm);
        
        // Time To (AM/PM -> 24h)
        $time_to_h = $_POST['time_to_h'] ?? '11';
        $time_to_m = $_POST['time_to_m'] ?? '59';
        $time_to_ampm = $_POST['time_to_ampm'] ?? 'PM';
        $end_time = convertTo24h($time_to_h, $time_to_m, $time_to_ampm);
        
        $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
        $visit_id = !empty($_POST['visit_id']) ? (int)$_POST['visit_id'] : null;
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        $errors = [];
        if ($activity_user_id <= 0) $errors[] = "Please select an employee";
        if (empty($activity_date)) $errors[] = "Activity date is required";
        if (empty($title)) $errors[] = "Activity title is required";
        
        if (empty($errors)) {
            // Get employee branch
            $stmt = $db->prepare("SELECT branch_id FROM users WHERE id = ?");
            $stmt->execute([$activity_user_id]);
            $emp_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $activity_branch_id = $emp_data['branch_id'] ?? $user_branch_id;
            
            // Calculate duration
            $duration_minutes = null;
            if ($start_time && $end_time) {
                $start_ts = strtotime($start_time);
                $end_ts = strtotime($end_time);
                if ($end_ts > $start_ts) {
                    $duration_minutes = round(($end_ts - $start_ts) / 60);
                }
            }
            
            // Insert
            $stmt = $db->prepare("
                INSERT INTO daily_activities (
                    user_id, branch_id, activity_date, title, description,
                    category, start_time, end_time, duration_minutes,
                    status, priority, patient_id, visit_id, notes,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $activity_user_id, $activity_branch_id, $activity_date, $title, $description,
                $category ?: null, $start_time, $end_time, $duration_minutes,
                $status, $priority, $patient_id, $visit_id, $notes ?: null
            ]);
            
            $new_id = $db->lastInsertId();
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'daily_activity_added', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $activity_branch_id,
                    "Daily activity #$new_id added for employee ID: $activity_user_id | Title: $title"
                ]);
            } catch (Exception $e) {}
            
            $message = "✅ Activity added successfully!";
            $message_type = 'success';
            
            // Redirect after success
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "daily_activities.php?branch=' . $selected_branch_id . '&added=1"; 
                }, 1500);
            </script>';
        } else {
            $message = "❌ " . implode('<br>', $errors);
            $message_type = 'error';
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Add daily activity error: " . $e->getMessage());
    }
}

// ================================================================
// HELPER: Convert AM/PM to 24h
// ================================================================
function convertTo24h($h, $m, $ampm) {
    if ($h === '' || $m === '' || $ampm === '') return null;
    $h = (int)$h;
    $m = str_pad((int)$m, 2, '0', STR_PAD_LEFT);
    
    if ($ampm === 'AM') {
        $h24 = ($h === 12) ? 0 : $h;
    } else {
        $h24 = ($h === 12) ? 12 : $h + 12;
    }
    
    return str_pad($h24, 2, '0', STR_PAD_LEFT) . ':' . $m . ':00';
}

// ================================================================
// GET EMPLOYEES FOR DROPDOWN
// ================================================================
$employees = [];
try {
    $sql = "SELECT u.id, u.full_name, u.username, u.role, u.branch_id, b.name as branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.status = 'active' AND u.role != 'admin'";
    
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND u.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $sql .= " ORDER BY u.full_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// ================================================================
// GET PATIENTS FOR DROPDOWN
// ================================================================
$patients = [];
try {
    $sql = "SELECT id, patient_id, full_name, phone 
            FROM patients";
    $params = [];
    if ($selected_branch_id !== 'all') {
        $sql .= " WHERE branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $sql .= " ORDER BY full_name ASC LIMIT 500";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// ================================================================
// GET RECENT VISITS (for visit dropdown)
// ================================================================
$visits = [];
try {
    $sql = "SELECT v.id, v.visit_number, v.patient_id, p.full_name as patient_name
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            WHERE v.status NOT IN ('cancelled')
            ORDER BY v.created_at DESC
            LIMIT 100";
    $stmt = $db->query($sql);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $visits = [];
}

// ================================================================
// CATEGORIES
// ================================================================
$categories = [
    'Consultation',
    'Patient Care',
    'Laboratory',
    'Pharmacy',
    'Reception',
    'Cashier',
    'Administration',
    'Cleaning',
    'Meeting',
    'Training',
    'Report',
    'Inventory',
    'Documentation',
    'Other'
];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// Include header + sidebar
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Daily Activity - Braick Dispensary</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-bg: #E8F0FE;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3D2E0A;
    --purple-bg: #2D1B5F;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
    background: var(--bg-body);
    color: var(--text-primary);
    transition: background 0.3s ease, color 0.3s ease;
}

::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg-body); }
::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.main-content {
    margin-left: 270px;
    margin-top: 68px;
    padding: 24px 28px;
    min-height: calc(100vh - 68px);
    background: var(--bg-body);
}

/* ================================================================ */
/* PAGE HEADER */
/* ================================================================ */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 18px;
    padding: 24px 28px;
    margin-bottom: 20px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(11,94,215,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.page-title i { font-size: 1.8rem; opacity: 0.9; }

.page-subtitle {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-top: 6px;
    position: relative;
    z-index: 1;
}

/* Back button */
.btn-back {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.2);
    position: relative;
    z-index: 1;
}

.btn-back:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* Alert */
.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.9rem;
    font-weight: 500;
    border: 2px solid transparent;
}

.alert-success {
    background: var(--success-bg);
    color: var(--success);
    border-color: var(--success);
}

.alert-error {
    background: var(--danger-bg);
    color: var(--danger);
    border-color: var(--danger);
}

/* ================================================================ */
/* FORM CARD */
/* ================================================================ */
.form-card {
    background: var(--bg-card);
    border-radius: 18px;
    padding: 28px 32px;
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow-md);
    max-width: 1100px;
    margin: 0 auto;
}

.form-section {
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px dashed var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 20px;
}

.section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--primary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    font-size: 1rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.full-width { grid-column: 1 / -1; }

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 5px;
}

.form-label .required {
    color: var(--danger);
    font-weight: 700;
}

.form-control {
    width: 100%;
    padding: 11px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
}

.form-control::placeholder {
    color: var(--text-secondary);
    opacity: 0.6;
}

textarea.form-control {
    resize: vertical;
    min-height: 90px;
    line-height: 1.5;
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
}

.form-hint {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin-top: 2px;
}

/* Time picker AM/PM */
.time-picker {
    display: flex;
    align-items: center;
    gap: 6px;
}

.time-picker select {
    padding: 11px 10px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.time-picker select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
}

.time-picker .time-sep {
    color: var(--text-secondary);
    font-weight: 700;
    font-size: 1.1rem;
}

.time-picker .ampm-select {
    min-width: 70px;
    background: var(--primary-bg);
    color: var(--primary);
    font-weight: 700;
}

/* Duration display */
.duration-display {
    padding: 11px 14px;
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    background: var(--purple-bg);
    color: var(--purple);
    font-weight: 700;
    font-size: 0.85rem;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 45px;
}

/* ================================================================ */
/* FORM ACTIONS */
/* ================================================================ */
.form-actions {
    display: flex;
    gap: 12px;
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.3s ease;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 4px 12px rgba(11,94,215,0.25);
    flex: 1;
    min-width: 180px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(11,94,215,0.35);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
    flex: 0 0 auto;
    min-width: 120px;
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
    background: var(--primary-bg);
}

.btn-reset {
    background: var(--warning-bg);
    color: var(--warning);
    border: 2px solid var(--warning);
}

.btn-reset:hover {
    background: var(--warning);
    color: white;
    transform: translateY(-2px);
}

/* Toast */
.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 12px 18px;
    border-radius: 12px;
    z-index: 9999;
    max-width: 360px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}

.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: #059669; }
.toast-custom.error { background: #EF4444; }
.toast-custom.info { background: #0B5ED7; }

/* Footer */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* Responsive */
@media (max-width: 1024px) {
    .main-content { padding: 16px; }
}

@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 12px; }
    .form-card { padding: 18px 16px; }
    .form-grid, .form-grid-3 { grid-template-columns: 1fr; }
    .full-width { grid-column: 1; }
    .page-title { font-size: 1.15rem; }
    .form-actions { flex-direction: column; }
    .btn { width: 100%; }
}

@media print {
    .sidebar, .footer, .form-actions { display: none !important; }
    .main-content { margin: 0 !important; padding: 20px !important; }
}
</style>
</head>
<body>

<main class="main-content">

    <!-- ============================================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                Add Daily Activity
                <span style="background:rgba(255,255,255,0.2);padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-user-shield"></i> Admin
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                Rekodi activity mpya kwa mfanyakazi yeyote
            </p>
        </div>
        <a href="daily_activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Activities
        </a>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- FORM -->
    <!-- ============================================================ -->
    <div class="form-card">
        <form method="POST" action="" id="activityForm">
            <input type="hidden" name="save_activity" value="1">
            
            <!-- ==================================================== -->
            <!-- SECTION 1: WHO & WHEN -->
            <!-- ==================================================== -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-user-clock"></i> WHO & WHEN
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i>
                            Employee <span class="required">*</span>
                        </label>
                        <select name="user_id" class="form-control" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" 
                                    <?= ($preselected_user_id == $emp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['full_name']) ?> 
                                    (@<?= htmlspecialchars($emp['username']) ?>)
                                    <?= !empty($emp['branch_name']) ? ' • ' . htmlspecialchars($emp['branch_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">Chagua mfanyakazi unayemtengenezea activity</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i>
                            Activity Date <span class="required">*</span>
                        </label>
                        <input type="date" 
                               name="activity_date" 
                               class="form-control" 
                               value="<?= date('Y-m-d') ?>" 
                               max="<?= date('Y-m-d') ?>"
                               required>
                        <span class="form-hint">Tarehe ambayo activity ilifanyika</span>
                    </div>
                </div>
            </div>
            
            <!-- ==================================================== -->
            <!-- SECTION 2: TIME -->
            <!-- ==================================================== -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-clock"></i> TIME
                </div>
                
                <div class="form-grid-3">
                    <!-- Time From -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-play-circle"></i>
                            Start Time
                        </label>
                        <div class="time-picker">
                            <select name="time_from_h" id="time_from_h" style="flex:1;">
                                <?php for ($h = 1; $h <= 12; $h++): 
                                    $hv = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $hv ?>" <?= $h == 8 ? 'selected' : '' ?>><?= $hv ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-sep">:</span>
                            <select name="time_from_m" id="time_from_m" style="flex:1;">
                                <?php for ($m = 0; $m < 60; $m += 5): 
                                    $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $mv ?>" <?= $m == 0 ? 'selected' : '' ?>><?= $mv ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="time_from_ampm" id="time_from_ampm" class="ampm-select">
                                <option value="AM" selected>AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                        <span class="form-hint">Muda activity ilipoanza</span>
                    </div>
                    
                    <!-- Time To -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stop-circle"></i>
                            End Time
                        </label>
                        <div class="time-picker">
                            <select name="time_to_h" id="time_to_h" style="flex:1;">
                                <?php for ($h = 1; $h <= 12; $h++): 
                                    $hv = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $hv ?>" <?= $h == 5 ? 'selected' : '' ?>><?= $hv ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-sep">:</span>
                            <select name="time_to_m" id="time_to_m" style="flex:1;">
                                <?php for ($m = 0; $m < 60; $m += 5): 
                                    $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $mv ?>" <?= $m == 0 ? 'selected' : '' ?>><?= $mv ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="time_to_ampm" id="time_to_ampm" class="ampm-select">
                                <option value="AM">AM</option>
                                <option value="PM" selected>PM</option>
                            </select>
                        </div>
                        <span class="form-hint">Muda activity ilipoisha</span>
                    </div>
                    
                    <!-- Duration -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-hourglass-half"></i>
                            Duration (Auto)
                        </label>
                        <div class="duration-display" id="durationDisplay">
                            <i class="fas fa-clock"></i>
                            <span id="durationText">-- minutes</span>
                        </div>
                        <span class="form-hint">Inahesabiwa automatically</span>
                    </div>
                </div>
            </div>
            
            <!-- ==================================================== -->
            <!-- SECTION 3: ACTIVITY DETAILS -->
            <!-- ==================================================== -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-tasks"></i> ACTIVITY DETAILS
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">
                            <i class="fas fa-heading"></i>
                            Activity Title <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="title" 
                               class="form-control" 
                               placeholder="e.g. Patient Consultation, Lab Test, etc."
                               maxlength="255"
                               required>
                        <span class="form-hint">Kichwa kifupi cha activity</span>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i>
                            Description
                        </label>
                        <textarea name="description" 
                                  class="form-control" 
                                  placeholder="Maelezo ya kina ya activity iliyofanyika..."
                                  rows="3"></textarea>
                        <span class="form-hint">Maelezo ya kina (optional)</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Category
                        </label>
                        <select name="category" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-flag"></i>
                            Priority
                        </label>
                        <select name="priority" class="form-control">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🟠 High</option>
                            <option value="urgent">🔴 Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="completed" selected>✅ Completed</option>
                            <option value="in_progress">⏳ In Progress</option>
                            <option value="pending">⏸️ Pending</option>
                            <option value="cancelled">❌ Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-sticky-note"></i>
                            Notes
                        </label>
                        <input type="text" 
                                   name="notes" 
                                   class="form-control" 
                                   placeholder="Additional notes..."
                                   maxlength="255">
                    </div>
                </div>
            </div>
            
            <!-- ==================================================== -->
            <!-- SECTION 4: RELATED (OPTIONAL) -->
            <!-- ==================================================== -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-link"></i> RELATED (OPTIONAL)
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-injured"></i>
                            Related Patient
                        </label>
                        <select name="patient_id" class="form-control">
                            <option value="">-- No Patient --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['full_name']) ?> 
                                    (<?= htmlspecialchars($p['patient_id']) ?>)
                                    <?= !empty($p['phone']) ? ' • ' . htmlspecialchars($p['phone']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">Kama activity inahusiana na patient</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clinic-medical"></i>
                            Related Visit
                        </label>
                        <select name="visit_id" class="form-control">
                            <option value="">-- No Visit --</option>
                            <?php foreach ($visits as $v): ?>
                                <option value="<?= $v['id'] ?>">
                                    <?= htmlspecialchars($v['visit_number']) ?> 
                                    • <?= htmlspecialchars($v['patient_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">Kama activity inahusiana na visit</span>
                    </div>
                </div>
            </div>
            
            <!-- ==================================================== -->
            <!-- FORM ACTIONS -->
            <!-- ==================================================== -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Save Activity
                </button>
                <button type="reset" class="btn btn-outline btn-reset">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
                <a href="daily_activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--border-color);margin:0 6px;">|</span>
            Add Daily Activity
            <span style="color:var(--border-color);margin:0 6px;">|</span>
            <?= date('l, F d, Y') ?>
            <span style="color:var(--border-color);margin:0 6px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;font-size:0.85rem;">Notification</p>
        <p id="toastMessage" style="font-size:0.75rem;opacity:0.9;"></p>
    </div>
</div>

<script>
// ================================================================
// AUTO-CALCULATE DURATION (AM/PM -> 24h conversion)
// ================================================================
function updateDuration() {
    var fromH = document.getElementById('time_from_h').value;
    var fromM = document.getElementById('time_from_m').value;
    var fromAMPM = document.getElementById('time_from_ampm').value;
    
    var toH = document.getElementById('time_to_h').value;
    var toM = document.getElementById('time_to_m').value;
    var toAMPM = document.getElementById('time_to_ampm').value;
    
    // Convert to 24h
    function to24h(h, m, ampm) {
        h = parseInt(h);
        m = parseInt(m);
        if (ampm === 'AM') {
            if (h === 12) h = 0;
        } else {
            if (h !== 12) h = h + 12;
        }
        return h * 60 + m; // minutes from midnight
    }
    
    var startMin = to24h(fromH, fromM, fromAMPM);
    var endMin = to24h(toH, toM, toAMPM);
    
    var durationMin = endMin - startMin;
    
    var display = document.getElementById('durationDisplay');
    var text = document.getElementById('durationText');
    
    if (durationMin > 0) {
        var h = Math.floor(durationMin / 60);
        var m = durationMin % 60;
        var durationStr = '';
        if (h > 0) durationStr += h + 'h ';
        if (m > 0) durationStr += m + 'm';
        if (durationStr === '') durationStr = '0m';
        
        text.textContent = durationStr + ' (' + durationMin + ' min)';
        display.style.background = 'var(--success-bg)';
        display.style.color = 'var(--success)';
        display.style.borderColor = 'var(--success)';
    } else if (durationMin === 0) {
        text.textContent = 'Same time';
        display.style.background = 'var(--warning-bg)';
        display.style.color = 'var(--warning)';
        display.style.borderColor = 'var(--warning)';
    } else {
        text.textContent = 'Invalid: End < Start';
        display.style.background = 'var(--danger-bg)';
        display.style.color = 'var(--danger)';
        display.style.borderColor = 'var(--danger)';
    }
}

// Attach listeners
['time_from_h', 'time_from_m', 'time_from_ampm', 'time_to_h', 'time_to_m', 'time_to_ampm'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('change', updateDuration);
        el.addEventListener('input', updateDuration);
    }
});

// Initial calc
document.addEventListener('DOMContentLoaded', updateDuration);

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('activityForm').addEventListener('submit', function(e) {
    var user_id = document.querySelector('select[name="user_id"]').value;
    var title = document.querySelector('input[name="title"]').value.trim();
    var date = document.querySelector('input[name="activity_date"]').value;
    
    if (!user_id) {
        e.preventDefault();
        showToast('❌ Validation Error', 'Please select an employee', 'error');
        return;
    }
    if (!title) {
        e.preventDefault();
        showToast('❌ Validation Error', 'Activity title is required', 'error');
        return;
    }
    if (!date) {
        e.preventDefault();
        showToast('❌ Validation Error', 'Activity date is required', 'error');
        return;
    }
    
    // Check duration
    var durationText = document.getElementById('durationText').textContent;
    if (durationText.indexOf('Invalid') !== -1) {
        e.preventDefault();
        showToast('❌ Invalid Time', 'End time must be after start time', 'error');
        return;
    }
    
    // Disable submit button
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

// ================================================================
// TOAST
// ================================================================
function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMessage').textContent = message;
    toast.className = 'toast-custom ' + type;
    toast.style.display = 'flex';
    toast.classList.add('show');
    clearTimeout(toast.timeout);
    toast.timeout = setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, 4000);
}

// ================================================================
// DARK MODE SYNC
// ================================================================
if (localStorage.getItem('darkMode') === 'true') {
    document.documentElement.setAttribute('data-theme', 'dark');
}

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('%c➕ Add Daily Activity', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Total employees available: <?= count($employees) ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ Total patients available: <?= count($patients) ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ AM/PM time picker: ACTIVE', 'font-size:12px; color:#7C3AED;');
    console.log('%c✅ Auto-duration calculation: ACTIVE', 'font-size:12px; color:#7C3AED;');
});
</script>

</body>
</html>