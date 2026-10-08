<?php
// ================================================================
// FILE: frontend/pages/reception/appointment_status.php
// RECEPTION - UPDATE APPOINTMENT STATUS
// USING NEW DATABASE: dispensary_db
// WITH SHARED HEADER & SIDEBAR
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT RECEPTION/ADMIN
// ================================================================
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['reception', 'admin'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id'];
$full_name   = $_SESSION['full_name'] ?? 'Receptionist';
$branch_id   = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username'] ?? 'reception';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// REQUEST PARAMETERS
// ================================================================
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_status     = isset($_GET['status']) ? $_GET['status'] : '';
$redirect       = isset($_GET['redirect']) ? $_GET['redirect'] : 'appointments.php';
$message        = '';
$message_type   = '';

if ($appointment_id <= 0) {
    header('Location: ' . $redirect);
    exit;
}

try {
    // ================================================================
    // GET APPOINTMENT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT a.*, 
               p.full_name as patient_name,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.id = ? AND a.branch_id = ?
    ");
    $stmt->execute([$appointment_id, $branch_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        header('Location: ' . $redirect);
        exit;
    }

    // ================================================================
    // VALIDATE STATUS
    // ================================================================
    $valid_statuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        header('Location: ' . $redirect);
        exit;
    }

    // ================================================================
    // UPDATE APPOINTMENT STATUS
    // ================================================================
    $stmt = $db->prepare("
        UPDATE appointments 
        SET status = ?, 
            updated_at = NOW() 
        WHERE id = ? AND branch_id = ?
    ");

    if ($stmt->execute([$new_status, $appointment_id, $branch_id])) {
        $message = "Appointment status updated to: " . ucfirst($new_status);
        $message_type = 'success';

        // ---------- COMPLETED -> UPDATE VISIT ----------
        if ($new_status === 'completed' && !empty($appointment['visit_id'])) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'completed', 
                    is_completed = 1, 
                    completed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$appointment['visit_id'], $branch_id]);
        }

        // ---------- CONFIRMED -> UPDATE VISIT ----------
        if ($new_status === 'confirmed' && !empty($appointment['visit_id'])) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'assigned', 
                    assigned_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$appointment['visit_id'], $branch_id]);
        }

        // ---------- CANCELLED -> UPDATE VISIT ----------
        if ($new_status === 'cancelled' && !empty($appointment['visit_id'])) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'cancelled', 
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$appointment['visit_id'], $branch_id]);
        }

        // ---------- LOG ACTIVITY ----------
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'appointment_status_updated', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $branch_id,
                "Appointment ID: $appointment_id status changed to $new_status"
            ]);
        } catch (Exception $e) {
            // Silent fail
        }

    } else {
        $message = "Failed to update appointment status!";
        $message_type = 'error';
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// ASSETS
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Status - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           APPOINTMENT STATUS - PAGE-SPECIFIC STYLES ONLY
           (Shared styles are in reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, var(--primary, #0B5ED7) 0%, var(--primary-dark, #0A4CA8) 100%);
            border-radius: 20px;
            padding: 26px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }
        .page-header::before,
        .page-header::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header::before {
            width: 340px; height: 340px;
            top: -180px; right: -80px;
            background: rgba(255,255,255,0.06);
        }
        .page-header::after {
            width: 200px; height: 200px;
            bottom: -120px; left: -40px;
            background: rgba(255,255,255,0.04);
        }

        .page-header .page-title {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .page-header .page-title i { font-size: 1.9rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        .role-badge-display {
            background: rgba(255,255,255,0.22);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(6px);
        }

        .branch-tag {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .new-db-tag {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.85);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.1);
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 11px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            color: #fff;
        }

        /* ================================================================
           STATUS CARD
           ================================================================ */
        .status-card {
            background: var(--bg-card, #fff);
            border-radius: 18px;
            padding: 34px 32px;
            border: 2px solid var(--border-color, #E2E8F0);
            text-align: center;
            max-width: 620px;
            margin: 0 auto;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        }
        .status-card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.08));
        }

        .status-card .status-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--primary-bg, #E8F0FE);
            color: var(--primary, #0B5ED7);
            transition: all 0.3s ease;
        }
        .status-card .status-icon.success {
            background: #D1FAE5;
            color: #059669;
        }
        .status-card .status-icon.error {
            background: #FEE2E2;
            color: #DC2626;
        }
        .status-card .status-icon.info {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        .status-card .status-icon.warning {
            background: #FEF3C7;
            color: #D97706;
        }

        [data-theme="dark"] .status-card .status-icon.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-card .status-icon.error   { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-card .status-icon.info    { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-card .status-icon.warning { background: #3D2E0A; color: #FBBF24; }

        .status-card .status-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary, #1E293B);
            margin: 0 0 8px 0;
        }
        .status-card .status-message {
            font-size: 0.95rem;
            color: var(--text-secondary, #64748B);
            margin: 0 0 20px 0;
        }

        .status-card .status-details {
            background: var(--bg-body, #F1F5F9);
            border-radius: 12px;
            padding: 18px 20px;
            text-align: left;
            margin: 16px 0;
            border: 1px solid var(--border-color, #E2E8F0);
        }
        .status-card .status-details .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            font-size: 0.85rem;
            gap: 12px;
        }
        .status-card .status-details .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .status-card .status-details .detail-row:first-child {
            padding-top: 0;
        }
        .status-card .status-details .detail-label {
            color: var(--text-secondary, #64748B);
            font-weight: 500;
            flex-shrink: 0;
        }
        .status-card .status-details .detail-value {
            color: var(--text-primary, #1E293B);
            font-weight: 600;
            text-align: right;
        }

        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge-display {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
        }
        .status-badge-display.scheduled { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-display.confirmed { background: #D1FAE5; color: #059669; }
        .status-badge-display.completed { background: #D1FAE5; color: #059669; }
        .status-badge-display.cancelled { background: #FEE2E2; color: #DC2626; }

        [data-theme="dark"] .status-badge-display.scheduled { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-display.confirmed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.cancelled { background: #3A1A1A; color: #F87171; }

        /* ================================================================
           ALERT MESSAGE
           ================================================================ */
        .alert-box {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 620px;
            margin-left: auto;
            margin-right: auto;
        }
        .alert-box.success {
            background: #D1FAE5;
            color: #047857;
            border: 1px solid #6EE7B7;
        }
        .alert-box.error {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FCA5A5;
        }
        [data-theme="dark"] .alert-box.success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #1F5A3E;
        }
        [data-theme="dark"] .alert-box.error {
            background: #3A1A1A;
            color: #F87171;
            border-color: #5A1F1F;
        }

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 11px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary {
            background: var(--primary, #0B5ED7);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--primary-dark, #0A4CA8);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
            color: #fff;
        }
        .btn-success {
            background: var(--success, #059669);
            color: #fff;
        }
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
            color: #fff;
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary, #64748B);
            border: 2px solid var(--border-color, #E2E8F0);
        }
        .btn-outline:hover {
            background: var(--bg-body, #F1F5F9);
            border-color: var(--primary, #0B5ED7);
            color: var(--primary, #0B5ED7);
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; border-radius: 16px; }
            .page-header .page-title { font-size: 1.3rem; }
            .status-card { padding: 24px 18px; }
            .status-card .status-icon { width: 72px; height: 72px; font-size: 3rem; }
            .status-card .status-title { font-size: 1.2rem; }
            .status-card .status-details .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .status-card .status-details .detail-value { text-align: left; }
        }
        @media (max-width: 480px) {
            .page-header .page-title { font-size: 1.1rem; }
            .status-card { padding: 20px 14px; }
            .status-card .status-icon { width: 64px; height: 64px; font-size: 2.5rem; }
        }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ============================================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================================ -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i>
                Appointment Status
                <span class="role-badge-display">RECEPTION</span>
                <span class="new-db-tag">
                    <i class="fas fa-database"></i> New DB
                </span>
            </h1>
            <p class="page-subtitle">
                Update appointment status
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="<?= htmlspecialchars($redirect) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ALERT MESSAGE -->
    <!-- ============================================================ -->
    <?php if ($message): ?>
        <div class="alert-box <?= $message_type === 'success' ? 'success' : 'error' ?> animate-fade-in-up">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STATUS CARD -->
    <!-- ============================================================ -->
    <div class="status-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="status-icon <?= $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'error' : 'info') ?>">
            <?php if ($message_type === 'success'): ?>
                <i class="fas fa-check"></i>
            <?php elseif ($message_type === 'error'): ?>
                <i class="fas fa-exclamation"></i>
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

        <!-- ==================================================== -->
        <!-- APPOINTMENT DETAILS -->
        <!-- ==================================================== -->
        <?php if (isset($appointment)): ?>
        <div class="status-details">
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-hashtag"></i> Appointment ID</span>
                <span class="detail-value">#<?= (int)$appointment['id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-user"></i> Patient</span>
                <span class="detail-value"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-user-md"></i> Doctor</span>
                <span class="detail-value">Dr. <?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($appointment['doctor_specialty'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['doctor_specialty']) ?></span>
                </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-calendar-day"></i> Date & Time</span>
                <span class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['appointment_date'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-clipboard-list"></i> Purpose</span>
                <span class="detail-value"><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-notes-medical"></i> Visit Type</span>
                <span class="detail-value"><?= ucfirst(htmlspecialchars($appointment['visit_type'] ?? 'N/A')) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="detail-value"><?= htmlspecialchars($branch_name) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-info-circle"></i> Status</span>
                <span class="detail-value">
                    <span class="status-badge-display <?= htmlspecialchars($new_status ?: $appointment['status']) ?>">
                        <?= ucfirst(htmlspecialchars($new_status ?: $appointment['status'])) ?>
                    </span>
                </span>
            </div>
            <?php if (!empty($appointment['notes'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-sticky-note"></i> Notes</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['notes']) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ==================================================== -->
        <!-- ACTION BUTTONS -->
        <!-- ==================================================== -->
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:20px;">
            <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <a href="appointments.php" class="btn btn-outline">
                <i class="fas fa-calendar-check"></i> View All Appointments
            </a>
            <?php if ($message_type === 'success' && !empty($appointment['patient_id'])): ?>
                <a href="view_patient.php?id=<?= (int)$appointment['patient_id'] ?>" class="btn btn-success">
                    <i class="fas fa-user"></i> View Patient
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Appointment Status
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <span class="new-db-footer"><i class="fas fa-database"></i> New DB</span>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DATE & TIME UPDATER
    // ================================================================
    function updateDateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        const footer = document.getElementById('footerTimestamp');
        if (footer) footer.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast-custom ' + type;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // ESC -> Back to list
        if (e.key === 'Escape') {
            window.location.href = '<?= htmlspecialchars($redirect) ?>';
        }
    });

    console.log('%c📅 Braick - Appointment Status', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($full_name) ?> (ID: <?= (int)$user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Appointment ID: <?= (int)$appointment_id ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 New Status: <?= ucfirst(htmlspecialchars($new_status ?? 'N/A')) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared header & sidebar', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>