<?php
// ================================================================
// FILE: frontend/pages/reception/view_appointment.php
// RECEPTION - VIEW APPOINTMENT DETAILS
// USING dispensary_db (new database structure)
// ✅ USING SHARED HEADER & SIDEBAR
// ✅ ADDED: Oxygen Saturation (SpO2) in vital signs - 7 vitals
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ACCESS CHECK (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor':     header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php'); break;
        default:           header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id'] ?? 0;
$full_name   = $_SESSION['full_name'] ?? 'User';
$role        = $_SESSION['role'] ?? 'reception';
$branch_id   = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// HELPER FUNCTIONS (guarded against redeclaration)
// ================================================================
if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        $map = [
            'scheduled'   => 'scheduled',
            'confirmed'   => 'confirmed',
            'in-progress' => 'in-progress',
            'completed'   => 'completed',
            'cancelled'   => 'cancelled'
        ];
        return $map[$status] ?? 'scheduled';
    }
}

if (!function_exists('formatDate')) {
    function formatDate($datetime) {
        if (empty($datetime)) return 'N/A';
        return date('F d, Y h:i A', strtotime($datetime));
    }
}

// ================================================================
// GET APPOINTMENT ID
// ================================================================
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header('Location: appointments.php');
    exit;
}

// ================================================================
// FETCH DATA
// ================================================================
$appointment         = null;
$vital_signs         = null;
$visit_count         = ['total_visits' => 0];
$patient_days        = 0;
$appointment_history = [];
$bills               = [];

try {
    $db = Database::getInstance()->getConnection();

    // ---------- APPOINTMENT DETAILS ----------
    $stmt = $db->prepare("
        SELECT 
            a.*,
            a.visit_type,
            a.visit_id,
            a.assigned_at,
            a.confirmed_at,
            a.completed_at,
            a.cancelled_at,
            a.created_by,
            p.id as patient_id,
            p.full_name as patient_name, 
            p.patient_id as patient_code, 
            p.phone, 
            p.email, 
            p.address,
            p.date_of_birth,
            p.gender,
            p.blood_group,
            p.allergies,
            p.created_at as patient_created_at,
            p.marital_status,
            p.emergency_contact,
            u.id as doctor_id,
            u.full_name as doctor_name, 
            u.specialty, 
            u.phone as doctor_phone,
            u.is_online as doctor_online,
            b.name as branch_name,
            creator.full_name as created_by_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN branches b ON a.branch_id = b.id
        LEFT JOIN users creator ON a.created_by = creator.id
        WHERE a.id = ? AND a.branch_id = ?
    ");
    $stmt->execute([$appointment_id, $branch_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        header('Location: appointments.php?error=not_found');
        exit;
    }

    // ---------- LATEST VITAL SIGNS ----------
    $stmt = $db->prepare("
        SELECT * FROM vital_signs 
        WHERE patient_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$appointment['patient_id']]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

    // ---------- VISIT COUNT ----------
    $stmt = $db->prepare("SELECT COUNT(*) as total_visits FROM visits WHERE patient_id = ?");
    $stmt->execute([$appointment['patient_id']]);
    $visit_count = $stmt->fetch(PDO::FETCH_ASSOC);

    // ---------- PATIENT DAYS ----------
    $stmt = $db->prepare("SELECT DATEDIFF(NOW(), created_at) as patient_days FROM patients WHERE id = ?");
    $stmt->execute([$appointment['patient_id']]);
    $patient_days_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_days = $patient_days_data['patient_days'] ?? 0;

    // ---------- APPOINTMENT HISTORY ----------
    $stmt = $db->prepare("
        SELECT id, appointment_date, status, purpose, visit_type
        FROM appointments 
        WHERE patient_id = ? AND id != ?
        ORDER BY appointment_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$appointment['patient_id'], $appointment_id]);
    $appointment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- BILLS ----------
    if (!empty($appointment['visit_id'])) {
        $stmt = $db->prepare("
            SELECT id, bill_number, total_amount, paid_amount, balance, status, created_at
            FROM bills 
            WHERE visit_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$appointment['visit_id']]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    header('Location: appointments.php?error=db_error');
    exit;
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_notifications = $result['total'] ?? 0;
    }
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// ASSETS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    <title>Appointment Details - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           VIEW APPOINTMENT - PAGE-SPECIFIC STYLES ONLY
           (Shared styles are in reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, var(--primary, #0B5ED7) 0%, var(--primary-dark, #0A4CA8) 100%);
            border-radius: 20px;
            padding: 26px 32px;
            margin-bottom: 24px;
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
            font-size: 1.7rem;
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
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

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

        .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
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
           DETAIL CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card, #fff);
            border-radius: 18px;
            padding: 24px 26px;
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            position: relative;
        }
        .detail-card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: 0 8px 24px rgba(11, 94, 215, 0.12);
        }

        .detail-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary, #1E293B);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color, #E2E8F0);
        }
        .detail-card-title i { color: var(--primary, #0B5ED7); }

        .detail-label {
            font-size: 0.68rem;
            color: var(--text-secondary, #64748B);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 3px;
        }
        .detail-value {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-primary, #1E293B);
            word-break: break-word;
        }
        .detail-value a {
            color: var(--primary, #0B5ED7);
            text-decoration: none;
        }
        .detail-value a:hover { text-decoration: underline; }

        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge-display {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
        }
        .status-badge-display.scheduled   { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-display.confirmed   { background: #D1FAE5; color: #059669; }
        .status-badge-display.in-progress { background: #FEF3C7; color: #D97706; }
        .status-badge-display.completed   { background: #D1FAE5; color: #059669; }
        .status-badge-display.cancelled   { background: #FEE2E2; color: #DC2626; }

        [data-theme="dark"] .status-badge-display.scheduled   { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-display.confirmed   { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.in-progress { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-display.completed   { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.cancelled   { background: #3A1A1A; color: #F87171; }

        /* ================================================================
           DAYS BADGE
           ================================================================ */
        .days-badge-blue {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary, #0B5ED7);
            color: #fff;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(11, 94, 215, 0.25);
            letter-spacing: 0.02em;
        }
        .days-badge-blue.new {
            background: #059669;
            box-shadow: 0 3px 8px rgba(5, 150, 105, 0.25);
        }

        /* ================================================================
           VITAL SIGNS GRID
           ================================================================ */
        .vital-grid-7 {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
        }
        @media (max-width: 1400px) { .vital-grid-7 { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1024px) { .vital-grid-7 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px)  { .vital-grid-7 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px)  { .vital-grid-7 { grid-template-columns: repeat(2, 1fr); gap: 8px; } }

        .vital-card-display {
            text-align: center;
            padding: 16px 10px;
            border-radius: 12px;
            border: 2px solid var(--border-color, #E2E8F0);
            transition: all 0.3s ease;
            background: var(--bg-body, #F1F5F9);
            min-height: 105px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .vital-card-display:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.08);
        }
        .vital-card-display .vital-icon {
            font-size: 1.4rem;
            display: block;
            margin-bottom: 4px;
        }
        .vital-card-display .vital-label {
            font-size: 0.62rem;
            color: var(--text-secondary, #64748B);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 4px;
        }
        .vital-card-display .vital-value {
            font-size: 1.15rem;
            font-weight: 700;
            display: block;
            line-height: 1.15;
        }
        .vital-card-display .vital-unit {
            font-size: 0.6rem;
            color: var(--text-secondary, #64748B);
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
            color: #fff;
        }
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
            color: #fff;
        }
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: #fff;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
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
           QUICK ACTION CARDS
           ================================================================ */
        .quick-action-card {
            background: var(--bg-card, #fff);
            border-radius: 14px;
            padding: 20px 22px;
            border: 1px solid var(--border-color, #E2E8F0);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        }
        .quick-action-card:hover {
            border-color: var(--primary, #0B5ED7);
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(11, 94, 215, 0.15);
        }
        .quick-action-card .icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
        }
        .quick-action-card .label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary, #1E293B);
        }

        /* ================================================================
           HISTORY ITEMS
           ================================================================ */
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border-radius: 8px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.2s ease;
            gap: 12px;
        }
        .history-item:hover {
            background: var(--primary-bg, #E8F0FE);
        }
        .history-item:last-child { border-bottom: none; }
        .history-item .history-date {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary, #1E293B);
        }

        /* ================================================================
           BILLS TABLE
           ================================================================ */
        .bills-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .bills-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fff;
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            white-space: nowrap;
        }
        .bills-table thead th:first-child {
            border-radius: 10px 0 0 0;
        }
        .bills-table thead th:last-child {
            border-radius: 0 10px 0 0;
        }
        .bills-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            color: var(--text-primary, #1E293B);
        }
        .bills-table tbody tr:hover td {
            background: var(--primary-bg, #E8F0FE);
        }
        .bills-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 24px 12px;
            color: var(--text-secondary, #64748B);
        }
        .empty-state i {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
            opacity: 0.4;
        }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; border-radius: 16px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-card { padding: 16px 18px; border-radius: 14px; }
            .detail-card-title { font-size: 0.9rem; }
            .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .page-header .page-title { font-size: 1.1rem; }
            .detail-card { padding: 14px 14px; }
        }

        /* Print */
        @media print {
            .top-nav, .sidebar, .no-print, footer { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT (Header & Sidebar already included above) -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ============================================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================================ -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i>
                Appointment Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                ID: <strong>#<?= (int)$appointment['id'] ?></strong>
                <span style="opacity:0.5;">|</span>
                <i class="fas fa-calendar-day"></i>
                <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?>
                <span style="opacity:0.5;">|</span>
                <i class="fas fa-clock"></i>
                <?= date('h:i A', strtotime($appointment['appointment_date'])) ?>

                <span class="header-badge">
                    <span class="status-badge-display <?= getStatusBadgeClass($appointment['status']) ?>">
                        <?= ucfirst(htmlspecialchars($appointment['status'])) ?>
                    </span>
                </span>

                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($appointment['patient_name']) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if (!in_array($appointment['status'], ['confirmed', 'completed', 'cancelled'])): ?>
                <a href="appointment_status.php?id=<?= (int)$appointment['id'] ?>&status=confirmed&redirect=view_appointment.php?id=<?= (int)$appointment['id'] ?>"
                   class="btn-outline-light no-print">
                    <i class="fas fa-check"></i> Confirm
                </a>
            <?php endif; ?>
            <?php if (!in_array($appointment['status'], ['cancelled', 'completed'])): ?>
                <a href="appointment_status.php?id=<?= (int)$appointment['id'] ?>&status=cancelled&redirect=view_appointment.php?id=<?= (int)$appointment['id'] ?>"
                   class="btn-outline-light no-print"
                   onclick="return confirm('Are you sure you want to cancel this appointment?');">
                    <i class="fas fa-times"></i> Cancel
                </a>
            <?php endif; ?>
            <a href="appointments.php" class="btn-outline-light no-print">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light no-print">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- APPOINTMENT + PATIENT + DOCTOR -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Appointment Info -->
        <div class="detail-card lg:col-span-2 animate-fade-in-up" style="animation-delay:0.05s;">
            <h3 class="detail-card-title">
                <i class="fas fa-info-circle"></i> Appointment Information
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Appointment ID</p>
                    <p class="detail-value">#<?= (int)$appointment['id'] ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-display <?= getStatusBadgeClass($appointment['status']) ?>">
                            <?= ucfirst(htmlspecialchars($appointment['status'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Date</p>
                    <p class="detail-value"><?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Time</p>
                    <p class="detail-value"><?= date('h:i A', strtotime($appointment['appointment_date'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Purpose</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value"><?= ucfirst(htmlspecialchars($appointment['visit_type'] ?? 'N/A')) ?></p>
                </div>
                <div>
                    <p class="detail-label">Branch</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['branch_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Created By</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['created_by_name'] ?? 'N/A') ?></p>
                </div>
                <div class="sm:col-span-2">
                    <p class="detail-label">Created At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['created_at'])) ?></p>
                </div>
                <?php if (!empty($appointment['confirmed_at'])): ?>
                <div class="sm:col-span-2">
                    <p class="detail-label">Confirmed At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['confirmed_at'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($appointment['completed_at'])): ?>
                <div class="sm:col-span-2">
                    <p class="detail-label">Completed At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['completed_at'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($appointment['cancelled_at'])): ?>
                <div class="sm:col-span-2">
                    <p class="detail-label">Cancelled At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['cancelled_at'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Patient Info -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
            <h3 class="detail-card-title">
                <i class="fas fa-user"></i> Patient
                <span class="days-badge-blue <?= $patient_days == 0 ? 'new' : '' ?>">
                    <i class="fas fa-calendar-day"></i>
                    <?= $patient_days > 0 ? $patient_days . ' days' : 'New' ?>
                </span>
            </h3>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value">
                        <a href="view_patient.php?id=<?= (int)$appointment['patient_id'] ?>">
                            <?= htmlspecialchars($appointment['patient_name']) ?>
                        </a>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value" style="font-family:monospace;"><?= htmlspecialchars($appointment['patient_code'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value">
                        <?php if (!empty($appointment['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($appointment['phone']) ?>"><?= htmlspecialchars($appointment['phone']) ?></a>
                        <?php else: ?>N/A<?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Date of Birth</p>
                    <p class="detail-value"><?= !empty($appointment['date_of_birth']) ? date('F d, Y', strtotime($appointment['date_of_birth'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="detail-label">Marital Status</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['marital_status'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Blood Group</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Allergies</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['allergies'] ?? 'None') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['address'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Total Visits</p>
                    <p class="detail-value">
                        <span style="display:inline-flex;align-items:center;gap:6px;">
                            <i class="fas fa-notes-medical" style="color:var(--primary);"></i>
                            <?= (int)($visit_count['total_visits'] ?? 0) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Doctor Info -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
            <h3 class="detail-card-title">
                <i class="fas fa-user-md"></i> Doctor
                <?php if ($appointment['doctor_online'] == 1): ?>
                    <span style="font-size:0.7rem;font-weight:600;color:#059669;margin-left:auto;">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i> Online
                    </span>
                <?php else: ?>
                    <span style="font-size:0.7rem;font-weight:600;color:#94A3B8;margin-left:auto;">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i> Offline
                    </span>
                <?php endif; ?>
            </h3>
            <div style="display:flex;flex-direction:column;gap:14px;">
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
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <?php if ($appointment['doctor_online'] == 1): ?>
                            <span style="color:#059669;font-weight:700;">
                                <i class="fas fa-circle" style="font-size:0.55rem;"></i> Online
                            </span>
                        <?php else: ?>
                            <span style="color:#94A3B8;font-weight:700;">
                                <i class="fas fa-circle" style="font-size:0.55rem;"></i> Offline
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- BILLS -->
    <!-- ============================================================ -->
    <?php if (!empty($bills)): ?>
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;animation-delay:0.2s;">
        <h3 class="detail-card-title">
            <i class="fas fa-file-invoice" style="color:#D97706;"></i> Bills
            <span style="font-size:0.75rem;font-weight:500;color:var(--text-secondary);">
                (<?= count($bills) ?> bill(s))
            </span>
        </h3>
        <div style="overflow-x:auto;">
            <table class="bills-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($bill['bill_number']) ?></td>
                        <td>TSh <?= number_format((float)($bill['total_amount'] ?? 0), 0) ?></td>
                        <td style="color:#059669;font-weight:600;">TSh <?= number_format((float)($bill['paid_amount'] ?? 0), 0) ?></td>
                        <td style="font-weight:600;color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
                            TSh <?= number_format((float)($bill['balance'] ?? 0), 0) ?>
                        </td>
                        <td>
                            <span class="status-badge-display <?= $bill['status'] === 'paid' ? 'confirmed' : ($bill['status'] === 'pending' ? 'scheduled' : 'cancelled') ?>">
                                <?= ucfirst(htmlspecialchars($bill['status'])) ?>
                            </span>
                        </td>
                        <td style="font-size:0.75rem;color:var(--text-secondary);">
                            <?= date('d M Y', strtotime($bill['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ============================================================ -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;animation-delay:0.25s;">
        <h3 class="detail-card-title">
            <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Latest Vital Signs
            <span style="font-size:0.72rem;font-weight:500;color:var(--text-secondary);">
                Recorded: <?= date('F d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
            </span>
        </h3>

        <div class="vital-grid-7">
            <?php if ($vital_signs['temperature']): ?>
            <div class="vital-card-display" style="border-color:#DC2626;background:rgba(220,38,38,0.05);">
                <span class="vital-icon">🌡️</span>
                <span class="vital-label">Temperature</span>
                <span class="vital-value" style="color:#DC2626;"><?= htmlspecialchars($vital_signs['temperature']) ?></span>
                <span class="vital-unit">°C</span>
            </div>
            <?php endif; ?>

            <?php if ($vital_signs['blood_pressure_systolic'] || $vital_signs['blood_pressure_diastolic']): ?>
            <div class="vital-card-display" style="border-color:#0B5ED7;background:rgba(11,94,215,0.05);">
                <span class="vital-icon">💓</span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-value" style="color:#0B5ED7;">
                    <?php
                        $sys = $vital_signs['blood_pressure_systolic'] ?? '';
                        $dia = $vital_signs['blood_pressure_diastolic'] ?? '';
                        if ($sys && $dia) echo htmlspecialchars($sys . '/' . $dia);
                        elseif ($sys) echo htmlspecialchars($sys);
                        else echo 'N/A';
                    ?>
                </span>
                <span class="vital-unit">mmHg</span>
            </div>
            <?php endif; ?>

            <?php if ($vital_signs['pulse_rate']): ?>
            <div class="vital-card-display" style="border-color:#059669;background:rgba(5,150,105,0.05);">
                <span class="vital-icon">❤️</span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-value" style="color:#059669;"><?= htmlspecialchars($vital_signs['pulse_rate']) ?></span>
                <span class="vital-unit">bpm</span>
            </div>
            <?php endif; ?>

            <?php if ($vital_signs['weight']): ?>
            <div class="vital-card-display" style="border-color:#D97706;background:rgba(217,119,6,0.05);">
                <span class="vital-icon">⚖️</span>
                <span class="vital-label">Weight</span>
                <span class="vital-value" style="color:#D97706;"><?= htmlspecialchars($vital_signs['weight']) ?></span>
                <span class="vital-unit">kg</span>
            </div>
            <?php endif; ?>

            <?php if ($vital_signs['height']): ?>
            <div class="vital-card-display" style="border-color:#7C3AED;background:rgba(124,58,237,0.05);">
                <span class="vital-icon">📏</span>
                <span class="vital-label">Height</span>
                <span class="vital-value" style="color:#7C3AED;"><?= htmlspecialchars($vital_signs['height']) ?></span>
                <span class="vital-unit">cm</span>
            </div>
            <?php endif; ?>

            <?php if ($vital_signs['bmi']): ?>
            <div class="vital-card-display" style="border-color:#0D9488;background:rgba(13,148,136,0.05);">
                <span class="vital-icon">📊</span>
                <span class="vital-label">BMI</span>
                <span class="vital-value" style="color:#0D9488;"><?= htmlspecialchars($vital_signs['bmi']) ?></span>
                <span class="vital-unit">kg/m²</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($vital_signs['oxygen_saturation'])):
                $spo2 = (int)$vital_signs['oxygen_saturation'];
                $spo2_color = $spo2 >= 95 ? '#0891B2' : ($spo2 >= 90 ? '#D97706' : '#DC2626');
                $spo2_bg = $spo2 >= 95 ? 'rgba(8,145,178,0.05)' : ($spo2 >= 90 ? 'rgba(217,119,6,0.05)' : 'rgba(220,38,38,0.05)');
                $spo2_label = $spo2 >= 95 ? 'Normal' : ($spo2 >= 90 ? 'Low' : 'Critical');
            ?>
            <div class="vital-card-display" style="border-color:<?= $spo2_color ?>;background:<?= $spo2_bg ?>;">
                <span class="vital-icon">🫁</span>
                <span class="vital-label">Oxygen Saturation</span>
                <span class="vital-value" style="color:<?= $spo2_color ?>;"><?= $spo2 ?>%</span>
                <span class="vital-unit" style="color:<?= $spo2_color ?>;font-weight:700;"><?= $spo2_label ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($vital_signs['notes'])): ?>
            <div style="margin-top:14px;padding:10px 14px;background:var(--bg-body);border-radius:10px;border:1px solid var(--border-color);font-size:0.8rem;color:var(--text-secondary);">
                <i class="fas fa-sticky-note" style="color:var(--primary);"></i>
                <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- APPOINTMENT HISTORY -->
    <!-- ============================================================ -->
    <?php if (!empty($appointment_history)): ?>
    <div class="detail-card animate-fade-in-up" style="margin-top:20px;animation-delay:0.3s;">
        <h3 class="detail-card-title">
            <i class="fas fa-history" style="color:#7C3AED;"></i> Appointment History
            <span style="font-size:0.72rem;font-weight:500;color:var(--text-secondary);">
                (Last 5 appointments)
            </span>
        </h3>
        <div>
            <?php foreach ($appointment_history as $history): ?>
                <div class="history-item">
                    <div>
                        <span class="history-date">
                            <i class="fas fa-calendar-day" style="color:var(--primary);opacity:0.7;"></i>
                            <?= date('F d, Y h:i A', strtotime($history['appointment_date'])) ?>
                        </span>
                        <?php if (!empty($history['purpose'])): ?>
                            <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;">
                                — <?= htmlspecialchars($history['purpose']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($history['visit_type'])): ?>
                            <span style="font-size:0.72rem;color:var(--text-secondary);margin-left:4px;">
                                (<?= ucfirst(htmlspecialchars($history['visit_type'])) ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge-display <?= getStatusBadgeClass($history['status']) ?>">
                        <?= ucfirst(htmlspecialchars($history['status'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" style="margin-top:20px;">
        <a href="view_patient.php?id=<?= (int)$appointment['patient_id'] ?>"
           class="quick-action-card animate-fade-in-up" style="animation-delay:0.35s;">
            <span class="icon"><i class="fas fa-user" style="color:var(--primary, #0B5ED7);"></i></span>
            <span class="label">View Patient Profile</span>
        </a>
        <a href="new_appointment.php?patient_id=<?= (int)$appointment['patient_id'] ?>"
           class="quick-action-card animate-fade-in-up" style="animation-delay:0.4s;">
            <span class="icon"><i class="fas fa-calendar-plus" style="color:#059669;"></i></span>
            <span class="label">New Appointment</span>
        </a>
        <a href="assign_doctor.php?patient_id=<?= (int)$appointment['patient_id'] ?>"
           class="quick-action-card animate-fade-in-up" style="animation-delay:0.45s;">
            <span class="icon"><i class="fas fa-user-md" style="color:#7C3AED;"></i></span>
            <span class="label">Assign Doctor</span>
        </a>
    </div>

    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Appointment Details
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
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
        const ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = timeStr;
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
    // STATUS CHANGE MESSAGES
    // ================================================================
    <?php if (isset($_GET['status_changed']) && $_GET['status_changed'] === 'confirmed'): ?>
        showToast('✅ Confirmed', 'Appointment confirmed successfully!', 'success');
    <?php endif; ?>
    <?php if (isset($_GET['status_changed']) && $_GET['status_changed'] === 'cancelled'): ?>
        showToast('❌ Cancelled', 'Appointment has been cancelled.', 'warning');
    <?php endif; ?>

    console.log('%c📅 Braick - View Appointment', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Appointment ID: <?= (int)$appointment['id'] ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($appointment['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Patient Days: <?= (int)$patient_days ?> days', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($appointment['doctor_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Total Visits: <?= (int)($visit_count['total_visits'] ?? 0) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c❤️ VITAL SIGNS: 7 CARDS (with SpO2)', 'font-size:13px; color:#DC2626;');
    console.log('%c🫁 SpO2 with category (Normal/Low/Critical)', 'font-size:13px; color:#0891B2;');
    console.log('%c📋 Appointment History shown', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Using shared header & sidebar', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>