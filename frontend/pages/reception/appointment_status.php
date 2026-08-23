<?php
// ================================================================
// FILE: frontend/pages/reception/appointment_status.php
// RECEPTION - UPDATE APPOINTMENT STATUS
// USING NEW DATABASE: dispensary_db
// WITH AJAX REAL-TIME UPDATE
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT RECEPTION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Receptionist';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'reception';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

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
    // ================================================================
    // GET APPOINTMENT DETAILS - NEW DATABASE
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
    // VALIDATE STATUS - NEW DATABASE
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
        
        // ================================================================
        // IF STATUS IS COMPLETED, UPDATE VISIT STATUS
        // ================================================================
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
        
        // ================================================================
        // IF STATUS IS CONFIRMED, UPDATE VISIT STATUS TO ASSIGNED
        // ================================================================
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
        
        // ================================================================
        // IF STATUS IS CANCELLED, UPDATE VISIT STATUS
        // ================================================================
        if ($new_status === 'cancelled' && !empty($appointment['visit_id'])) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'cancelled', 
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$appointment['visit_id'], $branch_id]);
        }
        
        // ================================================================
        // LOG ACTIVITY - NEW DATABASE
        // ================================================================
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
// GET UNREAD NOTIFICATIONS COUNT
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
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
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
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Status - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .page-header .new-db-tag {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.7);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.08);
            letter-spacing: 0.03em;
        }
        
        /* ================================================================
           STATUS CARD
           ================================================================ */
        .status-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px;
            border: 2px solid var(--border-color);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            transition: var(--transition);
        }
        
        .status-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .status-card .status-icon {
            font-size: 4rem;
            margin-bottom: 16px;
        }
        
        .status-card .status-icon.success { color: var(--success); }
        .status-card .status-icon.error { color: var(--danger); }
        .status-card .status-icon.info { color: var(--primary); }
        .status-card .status-icon.warning { color: var(--warning); }
        
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
            border-radius: var(--radius);
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
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge-display {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 16px;
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
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.78rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 3px 10px;
            font-size: 0.7rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
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
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        .footer .new-db-footer {
            color: var(--success);
            font-weight: 600;
            font-size: 0.65rem;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .status-card { padding: 20px; }
            .status-card .status-icon { font-size: 3rem; }
            .status-card .status-title { font-size: 1.2rem; }
            .status-card .status-details .detail-row { flex-direction: column; gap: 2px; }
            .btn { width: 100%; justify-content: center; }
            .flex-wrap { flex-direction: column; align-items: stretch; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
            .status-card { padding: 14px; }
            .status-card .status-icon { font-size: 2.5rem; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
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
            <a href="<?= $redirect ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATUS CARD -->
    <!-- ================================================================ -->
    <div class="status-card animate-fade-in-up">
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
                Status Updated Successfully! ✅
            <?php elseif ($message_type === 'error'): ?>
                Update Failed ❌
            <?php else: ?>
                Appointment Details
            <?php endif; ?>
        </h2>
        
        <p class="status-message"><?= htmlspecialchars($message) ?></p>
        
        <!-- ================================================================ -->
        <!-- APPOINTMENT DETAILS - NEW DATABASE -->
        <!-- ================================================================ -->
        <?php if (isset($appointment)): ?>
        <div class="status-details">
            <div class="detail-row">
                <span class="detail-label">Appointment ID</span>
                <span class="detail-value">#<?= $appointment['id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Patient</span>
                <span class="detail-value"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Doctor</span>
                <span class="detail-value">Dr. <?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($appointment['doctor_specialty'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Specialty</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['doctor_specialty']) ?></span>
                </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Date & Time</span>
                <span class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['appointment_date'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Purpose</span>
                <span class="detail-value"><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit Type</span>
                <span class="detail-value"><?= ucfirst(htmlspecialchars($appointment['visit_type'] ?? 'N/A')) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Branch</span>
                <span class="detail-value"><?= htmlspecialchars($branch_name) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge-display <?= $new_status ?? $appointment['status'] ?>">
                        <?= ucfirst($new_status ?? $appointment['status']) ?>
                    </span>
                </span>
            </div>
            <?php if (!empty($appointment['notes'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Notes</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['notes']) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS -->
        <!-- ================================================================ -->
        <div class="flex flex-wrap gap-2 justify-center mt-4">
            <a href="<?= $redirect ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <a href="appointments.php" class="btn btn-outline">
                <i class="fas fa-calendar-check"></i> View All Appointments
            </a>
            <?php if ($message_type === 'success' && isset($appointment['patient_id'])): ?>
                <a href="view_patient.php?id=<?= $appointment['patient_id'] ?>" class="btn btn-success">
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
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            <span class="new-db-footer"><i class="fas fa-database"></i> New DB</span>
            <span class="text-gray-300 mx-2">|</span>
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
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
        var currentDateTime = document.getElementById('currentDateTime');
        if (currentDateTime) {
            currentDateTime.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
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
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) return;
        
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

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'Escape') {
            window.location.href = '<?= $redirect ?>';
        }
    });

    console.log('%c📅 Braick - Appointment Status (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Tables: appointments, patients, users, visits, activity_logs', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Appointment ID: <?= $appointment_id ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 New Status: <?= ucfirst($new_status ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Status: scheduled → confirmed → completed | cancelled', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>