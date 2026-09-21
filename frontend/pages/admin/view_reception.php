<?php
// ================================================================
// FILE: frontend/pages/admin/view_reception.php
// VIEW RECEPTION DETAILS - BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';

require_once '../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$reception_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reception_id <= 0) {
    header('Location: receptions.php');
    exit;
}

// FETCH RECEPTION
$reception = null;
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception' AND status = 'active'), 0) as active_receptionists,
            COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception'), 0) as total_receptionists,
            COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'doctor' AND status = 'active'), 0) as total_doctors,
            COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active'), 0) as total_pharmacy,
            COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active'), 0) as total_lab,
            COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active'), 0) as total_cashier,
            COALESCE((SELECT COUNT(*) FROM patients WHERE branch_id = b.id), 0) as total_patients,
            COALESCE((SELECT COUNT(*) FROM patients WHERE branch_id = b.id AND assigned_doctor_id IS NOT NULL), 0) as assigned_patients,
            COALESCE((SELECT COUNT(*) FROM visits WHERE branch_id = b.id), 0) as total_visits,
            COALESCE((SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'pending'), 0) as pending_visits,
            COALESCE((SELECT COUNT(*) FROM appointments WHERE branch_id = b.id), 0) as total_appointments,
            COALESCE((SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status IN ('paid', 'partial')), 0) as total_bills
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$reception_id]);
    $reception = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

if (!$reception) {
    header('Location: receptions.php');
    exit;
}

// RECEPTIONISTS
$receptionists = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, status, is_online, last_online, profile_pic, created_at
        FROM users
        WHERE branch_id = ? AND role = 'reception'
        ORDER BY full_name ASC
    ");
    $stmt->execute([$reception_id]);
    $receptionists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// RECENT PATIENTS
$recent_patients = [];
try {
    $stmt = $db->prepare("
        SELECT p.id, p.patient_id, p.full_name, p.phone, p.gender, p.created_at
        FROM patients p
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// RECENT VISITS
$recent_visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.visit_date, v.status, v.visit_type,
               p.full_name as patient_name, p.patient_id as patient_code
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        WHERE v.branch_id = ?
        ORDER BY v.visit_date DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// RECENT APPOINTMENTS
$recent_appointments = [];
try {
    $stmt = $db->prepare("
        SELECT a.id, a.appointment_date, a.status, a.visit_type,
               p.full_name as patient_name, p.patient_id as patient_code,
               u.full_name as doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ?
        ORDER BY a.appointment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reception - <?= htmlspecialchars($reception['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --vr-primary: #0B5ED7;
            --vr-primary-dark: #0A4CA8;
            --vr-bg: #F0F4F8;
            --vr-card: #FFFFFF;
            --vr-text: #1E293B;
            --vr-text-secondary: #64748B;
            --vr-border: #E2E8F0;
            --vr-success: #059669;
            --vr-warning: #D97706;
            --vr-danger: #DC2626;
            --vr-purple: #7C3AED;
            --vr-teal: #0D9488;
        }
        [data-theme="dark"] {
            --vr-bg: #0F172A;
            --vr-card: #1E293B;
            --vr-text: #F1F5F9;
            --vr-text-secondary: #94A3B8;
            --vr-border: #334155;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, sans-serif; }
        .main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); background: var(--vr-bg); }
        @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 16px; } }

        .page-header {
            background: linear-gradient(135deg, #0A4CA8, #073B8A);
            border-radius: 18px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%; right: -10%;
            width: 400px; height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .page-header .title {
            color: white;
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        .page-header .subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        .btn-back {
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
            color: #78350F;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            position: relative;
            z-index: 1;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .btn-back:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(252, 211, 77, 0.4); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: var(--vr-card);
            border-radius: 14px;
            padding: 20px 22px;
            border: 2px solid var(--vr-border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 6px; height: 100%;
        }
        .stat-box:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(11, 94, 215, 0.15); border-color: #0B5ED7; }
        .stat-box .stat-icon {
            width: 54px; height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .stat-box .stat-content { flex: 1; min-width: 0; }
        .stat-box .stat-label {
            font-size: 0.65rem;
            color: var(--vr-text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        .stat-box .stat-value {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--vr-text);
            font-family: 'JetBrains Mono', monospace;
            line-height: 1.1;
        }

        /* COLORS */
        .stat-box.blue::before { background: linear-gradient(180deg, #0B5ED7, #3B82F6); }
        .stat-box.blue .stat-icon { background: #EFF6FF; color: #0B5ED7; }
        .stat-box.blue .stat-value { color: #0B5ED7; }

        .stat-box.green::before { background: linear-gradient(180deg, #059669, #34D399); }
        .stat-box.green .stat-icon { background: #ECFDF5; color: #059669; }
        .stat-box.green .stat-value { color: #059669; }

        .stat-box.teal::before { background: linear-gradient(180deg, #0D9488, #14B8A6); }
        .stat-box.teal .stat-icon { background: #F0FDFA; color: #0D9488; }
        .stat-box.teal .stat-value { color: #0D9488; }

        .stat-box.purple::before { background: linear-gradient(180deg, #7C3AED, #A78BFA); }
        .stat-box.purple .stat-icon { background: #F5F3FF; color: #7C3AED; }
        .stat-box.purple .stat-value { color: #7C3AED; }

        .stat-box.orange::before { background: linear-gradient(180deg, #D97706, #F59E0B); }
        .stat-box.orange .stat-icon { background: #FFFBEB; color: #D97706; }
        .stat-box.orange .stat-value { color: #D97706; }

        .stat-box.indigo::before { background: linear-gradient(180deg, #4F46E5, #818CF8); }
        .stat-box.indigo .stat-icon { background: #EEF2FF; color: #4F46E5; }
        .stat-box.indigo .stat-value { color: #4F46E5; }

        [data-theme="dark"] .stat-box.blue .stat-icon { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-box.green .stat-icon { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-box.teal .stat-icon { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-box.purple .stat-icon { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-box.orange .stat-icon { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-box.indigo .stat-icon { background: #1E1B4B; color: #818CF8; }

        .section {
            background: var(--vr-card);
            border-radius: 14px;
            padding: 20px 24px;
            border: 2px solid var(--vr-border);
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px dashed var(--vr-border);
        }
        .section-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--vr-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i { color: #0B5ED7; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .data-table thead th {
            text-align: left;
            padding: 10px 12px;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: white;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            letter-spacing: 0.05em;
        }
        .data-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--vr-border);
            color: var(--vr-text);
        }
        .data-table tbody tr:hover td { background: #EFF6FF; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .badge.active, .badge.online, .badge.completed, .badge.confirmed { background: #D1FAE5; color: #059669; }
        .badge.inactive, .badge.cancelled { background: #FEE2E2; color: #DC2626; }
        .badge.offline { background: #F1F5F9; color: #64748B; }
        .badge.pending { background: #FEF3C7; color: #D97706; }
        .badge.scheduled { background: #DBEAFE; color: #0B5ED7; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }
        .info-item {
            padding: 12px 16px;
            background: var(--vr-bg);
            border-radius: 10px;
            border-left: 4px solid #0B5ED7;
        }
        .info-item .info-label {
            font-size: 0.6rem;
            color: var(--vr-text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        .info-item .info-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--vr-text);
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--vr-text-secondary);
            font-size: 0.85rem;
        }
        .empty-state i { font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 8px; }

        @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="title">
                <i class="fas fa-headset"></i>
                <?= htmlspecialchars($reception['name']) ?>
                <span style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:16px;font-size:0.65rem;font-weight:700;">
                    <?= strtoupper($reception['status']) ?>
                </span>
            </h1>
            <p class="subtitle">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($reception['location']) ?>
                <span style="margin:0 8px;">|</span>
                <i class="fas fa-phone"></i>
                <?= htmlspecialchars($reception['phone'] ?? 'N/A') ?>
                <span style="margin:0 8px;">|</span>
                <i class="fas fa-envelope"></i>
                <?= htmlspecialchars($reception['email'] ?? 'N/A') ?>
            </p>
        </div>
        <a href="receptions.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Receptions
        </a>
    </div>

    <!-- STATS GRID - 3 JUU, 3 CHINI -->
    <div class="stats-grid">
        <div class="stat-box blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Patients</div>
                <div class="stat-value"><?= number_format($reception['total_patients']) ?></div>
            </div>
        </div>
        <div class="stat-box teal">
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="stat-content">
                <div class="stat-label">Assigned Patients</div>
                <div class="stat-value"><?= number_format($reception['assigned_patients']) ?></div>
            </div>
        </div>
        <div class="stat-box purple">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Visits</div>
                <div class="stat-value"><?= number_format($reception['total_visits']) ?></div>
            </div>
        </div>
        <div class="stat-box orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <div class="stat-label">Pending Visits</div>
                <div class="stat-value"><?= number_format($reception['pending_visits']) ?></div>
            </div>
        </div>
        <div class="stat-box indigo">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-content">
                <div class="stat-label">Appointments</div>
                <div class="stat-value"><?= number_format($reception['total_appointments']) ?></div>
            </div>
        </div>
        <div class="stat-box green">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Bills</div>
                <div class="stat-value"><?= number_format($reception['total_bills']) ?></div>
            </div>
        </div>
    </div>

    <!-- STAFF OVERVIEW -->
    <div class="section">
        <div class="section-header">
            <div class="section-title"><i class="fas fa-user-tie"></i> Staff Overview</div>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><i class="fas fa-headset"></i> Receptionists</div>
                <div class="info-value"><?= $reception['active_receptionists'] ?> Active / <?= $reception['total_receptionists'] ?> Total</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-user-md"></i> Doctors</div>
                <div class="info-value"><?= $reception['total_doctors'] ?> Active</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-pills"></i> Pharmacy</div>
                <div class="info-value"><?= $reception['total_pharmacy'] ?> Active</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-flask"></i> Laboratory</div>
                <div class="info-value"><?= $reception['total_lab'] ?> Active</div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-cash-register"></i> Cashiers</div>
                <div class="info-value"><?= $reception['total_cashier'] ?> Active</div>
            </div>
        </div>
    </div>

    <!-- RECEPTIONISTS LIST -->
    <div class="section">
        <div class="section-header">
            <div class="section-title"><i class="fas fa-headset"></i> Receptionists (<?= count($receptionists) ?>)</div>
        </div>
        <?php if (count($receptionists) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Online</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receptionists as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><?= htmlspecialchars($r['phone'] ?? 'N/A') ?></td>
                                <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td>
                                    <span class="badge <?= $r['is_online'] ? 'online' : 'offline' ?>">
                                        <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                        <?= $r['is_online'] ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-headset"></i>No receptionists found</div>
        <?php endif; ?>
    </div>

    <!-- RECENT PATIENTS -->
    <div class="section">
        <div class="section-header">
            <div class="section-title"><i class="fas fa-users"></i> Recent Patients (10)</div>
        </div>
        <?php if (count($recent_patients) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Patient ID</th><th>Name</th><th>Phone</th><th>Gender</th><th>Registered</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_patients as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['patient_id']) ?></strong></td>
                                <td><?= htmlspecialchars($p['full_name']) ?></td>
                                <td><?= htmlspecialchars($p['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($p['gender'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-users"></i>No patients found</div>
        <?php endif; ?>
    </div>

    <!-- RECENT VISITS -->
    <div class="section">
        <div class="section-header">
            <div class="section-title"><i class="fas fa-clipboard-list"></i> Recent Visits (10)</div>
        </div>
        <?php if (count($recent_visits) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Visit #</th><th>Patient</th><th>Type</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_visits as $v): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($v['visit_number']) ?></strong></td>
                                <td><?= htmlspecialchars($v['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($v['visit_type'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y H:i', strtotime($v['visit_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $v['status'] === 'completed' ? 'completed' : ($v['status'] === 'pending' ? 'pending' : 'scheduled') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $v['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-clipboard-list"></i>No visits found</div>
        <?php endif; ?>
    </div>

    <!-- RECENT APPOINTMENTS -->
    <div class="section">
        <div class="section-header">
            <div class="section-title"><i class="fas fa-calendar-check"></i> Recent Appointments (10)</div>
        </div>
        <?php if (count($recent_appointments) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_appointments as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['patient_name'] ?? 'N/A') ?></td>
                                <td>Dr. <?= htmlspecialchars($a['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y H:i', strtotime($a['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($a['visit_type'] ?? 'new') ?></td>
                                <td><span class="badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-calendar-check"></i>No appointments found</div>
        <?php endif; ?>
    </div>

</main>

</body>
</html>