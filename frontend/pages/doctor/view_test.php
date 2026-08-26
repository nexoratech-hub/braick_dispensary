<?php
// ================================================================
// FILE: frontend/pages/doctor/view_test.php
// DOCTOR - VIEW SINGLE LAB TEST (ALIAS)
// REDIRECT TO view_lab_test.php WITH PATIENT INFO
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

// ================================================================
// INCLUDE DATABASE - USING dispensary_db
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    // If database fails, redirect anyway
    if ($test_id > 0) {
        header('Location: view_lab_test.php?id=' . $test_id);
    } else {
        header('Location: lab_results.php');
    }
    exit;
}

// ================================================================
// GET TEST DETAILS FOR REDIRECT
// ================================================================
$test_info = null;
$patient_name = 'Unknown';
$test_name = 'Lab Test';

if ($test_id > 0) {
    try {
        // Get test details to display in loading page
        if ($is_admin) {
            $stmt = $db->prepare("
                SELECT lt.id, lt.test_name, lt.status, lt.created_at,
                       p.full_name as patient_name,
                       v.visit_number,
                       u.full_name as doctor_name
                FROM lab_tests lt
                JOIN visits v ON lt.visit_id = v.id
                JOIN patients p ON v.patient_id = p.id
                LEFT JOIN users u ON lt.doctor_id = u.id
                WHERE lt.id = ?
            ");
            $stmt->execute([$test_id]);
        } else {
            $stmt = $db->prepare("
                SELECT lt.id, lt.test_name, lt.status, lt.created_at,
                       p.full_name as patient_name,
                       v.visit_number,
                       u.full_name as doctor_name
                FROM lab_tests lt
                JOIN visits v ON lt.visit_id = v.id
                JOIN patients p ON v.patient_id = p.id
                LEFT JOIN users u ON lt.doctor_id = u.id
                WHERE lt.id = ? AND lt.doctor_id = ?
            ");
            $stmt->execute([$test_id, $doctor_id]);
        }
        $test_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_info) {
            $patient_name = $test_info['patient_name'] ?? 'Unknown';
            $test_name = $test_info['test_name'] ?? 'Lab Test';
        }
    } catch (Exception $e) {
        // If query fails, just redirect
        if ($test_id > 0) {
            header('Location: view_lab_test.php?id=' . $test_id);
        } else {
            header('Location: lab_results.php');
        }
        exit;
    }
}

// ================================================================
// LOG ACTIVITY
// ================================================================
try {
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
        VALUES (?, ?, ?, 'view_test_redirect', ?, NOW())
    ");
    $patient_id = $test_info['patient_id'] ?? null;
    $stmt->execute([
        $doctor_id,
        $doctor_branch_id,
        $patient_id,
        "Redirected to view lab test ID: $test_id - " . ($is_admin ? "(Admin Mode)" : "")
    ]);
} catch (Exception $e) {
    // Silent fail - don't break redirect
}

// ================================================================
// GET BRANCH NAME
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
// REDIRECT WITH JAVASCRIPT (with loading screen)
// ================================================================
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting - Lab Test Details - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES - LIGHT & DARK MODE */
        /* ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --warning: #D97706;
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        /* ================================================================ */
        /* LOADING CONTAINER */
        /* ================================================================ */
        .redirect-container {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px 50px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            border: 2px solid var(--border-color);
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .redirect-container:hover {
            border-color: var(--primary);
            box-shadow: 0 12px 48px rgba(11, 94, 215, 0.12);
        }
        
        /* ================================================================ */
        /* LOGO */
        /* ================================================================ */
        .logo-container {
            margin-bottom: 24px;
        }
        
        .logo-container img {
            height: 60px;
            width: auto;
            max-height: 60px;
            object-fit: contain;
        }
        
        .logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            font-size: 2rem;
            font-weight: 800;
            font-family: 'Arial', sans-serif;
        }
        
        /* ================================================================ */
        /* LOADING ANIMATION */
        /* ================================================================ */
        .spinner-container {
            margin: 24px 0;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 5px solid var(--border-color);
            border-top-color: var(--primary);
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ================================================================ */
        /* ICON WITH PULSE */
        /* ================================================================ */
        .redirect-icon {
            font-size: 3rem;
            color: var(--primary);
            margin: 16px 0;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }
        
        /* ================================================================ */
        /* TYPOGRAPHY */
        /* ================================================================ */
        .redirect-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .redirect-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }
        
        .test-info {
            background: var(--primary-bg);
            border-radius: 12px;
            padding: 16px 20px;
            margin: 16px 0;
            border: 1px solid var(--primary-light);
        }
        
        .test-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid rgba(11, 94, 215, 0.1);
        }
        
        .test-info .info-row:last-child {
            border-bottom: none;
        }
        
        .test-info .info-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .test-info .info-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .test-info .info-value .badge {
            font-size: 0.6rem;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: #FEF3C7; color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: #FEE2E2; color: var(--danger); }
        
        .test-name-highlight {
            font-weight: 700;
            color: var(--primary);
        }
        
        .patient-name-highlight {
            font-weight: 600;
            color: var(--success);
        }
        
        /* ================================================================ */
        /* PROGRESS BAR */
        /* ================================================================ */
        .progress-bar {
            width: 100%;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            margin: 20px 0 12px 0;
            overflow: hidden;
        }
        
        .progress-bar .progress-fill {
            height: 100%;
            width: 0%;
            background: var(--primary-gradient);
            border-radius: 2px;
            animation: progress 2s ease-in-out forwards;
        }
        
        @keyframes progress {
            0% { width: 0%; }
            30% { width: 30%; }
            60% { width: 65%; }
            85% { width: 85%; }
            100% { width: 100%; }
        }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            margin-top: 8px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 14px rgba(11, 94, 215, 0.25);
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
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer-text {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .footer-text .brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* ================================================================ */
        /* DARK MODE */
        /* ================================================================ */
        [data-theme="dark"] .test-info {
            background: #1E3A5F;
            border-color: #1E3A5F;
        }
        [data-theme="dark"] .test-info .info-row {
            border-color: rgba(110, 168, 254, 0.1);
        }
        [data-theme="dark"] .test-info .info-value {
            color: #F1F5F9;
        }
        [data-theme="dark"] .badge-success {
            background: #1A3A2A;
            color: #34D399;
        }
        [data-theme="dark"] .badge-warning {
            background: #3D2E0A;
            color: #FBBF24;
        }
        [data-theme="dark"] .badge-info {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        [data-theme="dark"] .badge-danger {
            background: #3A1A1A;
            color: #F87171;
        }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 480px) {
            .redirect-container {
                padding: 24px 20px;
            }
            .redirect-title {
                font-size: 1.1rem;
            }
            .redirect-icon {
                font-size: 2.5rem;
            }
            .test-info .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }
            .test-info .info-value {
                text-align: left;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .logo-container img {
                height: 45px;
            }
        }
        
        @media print {
            body { background: white; }
            .redirect-container { border: 1px solid #ddd; box-shadow: none; }
        }
    </style>
</head>
<body>

    <!-- ================================================================ -->
    <!-- REDIRECT CONTAINER -->
    <!-- ================================================================ -->
    <div class="redirect-container">
        
        <!-- Logo -->
        <div class="logo-container">
            <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" 
                 alt="Braick Dispensary" 
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
            <div class="logo-placeholder" style="display:none;">B</div>
        </div>
        
        <!-- Icon -->
        <div class="redirect-icon">
            <i class="fas fa-flask"></i>
        </div>
        
        <!-- Title -->
        <h1 class="redirect-title">Redirecting to Lab Test Details</h1>
        <p class="redirect-subtitle">Please wait while we load the complete test information...</p>
        
        <!-- Test Info -->
        <?php if ($test_info): ?>
            <div class="test-info">
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-flask"></i> Test</span>
                    <span class="info-value test-name-highlight"><?= htmlspecialchars($test_info['test_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user"></i> Patient</span>
                    <span class="info-value patient-name-highlight"><?= htmlspecialchars($test_info['patient_name'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($test_info['visit_number'])): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-clinic-medical"></i> Visit</span>
                        <span class="info-value"><?= htmlspecialchars($test_info['visit_number']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($test_info['doctor_name'])): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user-md"></i> Doctor</span>
                        <span class="info-value">Dr. <?= htmlspecialchars($test_info['doctor_name']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-clock"></i> Status</span>
                    <span class="info-value">
                        <span class="badge <?= 
                            ($test_info['status'] ?? '') === 'completed' ? 'badge-success' : 
                            (($test_info['status'] ?? '') === 'in_progress' ? 'badge-info' : 
                            (($test_info['status'] ?? '') === 'cancelled' ? 'badge-danger' : 'badge-warning')) 
                        ?>">
                            <i class="fas <?= 
                                ($test_info['status'] ?? '') === 'completed' ? 'fa-check-circle' : 
                                (($test_info['status'] ?? '') === 'in_progress' ? 'fa-spinner fa-spin' : 
                                (($test_info['status'] ?? '') === 'cancelled' ? 'fa-times-circle' : 'fa-clock')) 
                            ?>"></i>
                            <?= ucfirst(str_replace('_', ' ', $test_info['status'] ?? 'Pending')) ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Created</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($test_info['created_at'] ?? 'now')) ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="test-info" style="background:var(--gray-50);border-color:var(--border-color);">
                <p style="color:var(--text-secondary);font-size:0.85rem;">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                    Loading test information...
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Spinner -->
        <div class="spinner-container">
            <div class="spinner"></div>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        
        <!-- Manual Redirect Button (if auto-redirect fails) -->
        <div style="margin-top:8px;">
            <p style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:8px;">
                <i class="fas fa-sync-alt fa-spin"></i> Redirecting automatically...
            </p>
            <a href="view_lab_test.php?id=<?= $test_id ?>" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Go to Lab Test Now
            </a>
            <a href="lab_results.php" class="btn btn-outline" style="margin-left:8px;">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
        
        <!-- Footer -->
        <div class="footer-text">
            <span class="brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;color:var(--border-color);">|</span>
            Redirecting to Lab Test Details
            <span style="margin:0 6px;color:var(--border-color);">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- JAVASCRIPT - AUTO REDIRECT -->
    <!-- ================================================================ -->
    <script>
        // ================================================================
        // AUTO REDIRECT AFTER DELAY
        // ================================================================
        var redirectUrl = 'view_lab_test.php?id=<?= $test_id ?>';
        var redirectDelay = 1500; // 1.5 seconds
        
        // Set timeout to redirect
        var redirectTimeout = setTimeout(function() {
            window.location.href = redirectUrl;
        }, redirectDelay);
        
        // ================================================================
        // KEYBOARD SHORTCUTS
        // ================================================================
        document.addEventListener('keydown', function(e) {
            // Escape - cancel redirect and go back
            if (e.key === 'Escape') {
                clearTimeout(redirectTimeout);
                window.location.href = 'lab_results.php';
            }
            // Enter - go now
            if (e.key === 'Enter') {
                clearTimeout(redirectTimeout);
                window.location.href = redirectUrl;
            }
        });
        
        // ================================================================
        // DARK MODE - SYNC WITH HEADER
        // ================================================================
        var savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        
        // ================================================================
        // CONSOLE LOG
        // ================================================================
        console.log('%c🧪 Redirecting to Lab Test #<?= $test_id ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
        console.log('%c👤 Patient: <?= htmlspecialchars($patient_name) ?>', 'font-size:12px; color:#059669;');
        console.log('%c📋 Test: <?= htmlspecialchars($test_name) ?>', 'font-size:12px; color:#0B5ED7;');
        console.log('%c🔄 Redirecting in ' + (redirectDelay/1000) + ' seconds...', 'font-size:12px; color:#64748B;');
        console.log('%c⌨️  Press ESC to cancel, ENTER to go now', 'font-size:12px; color:#D97706;');
    </script>

</body>
</html>