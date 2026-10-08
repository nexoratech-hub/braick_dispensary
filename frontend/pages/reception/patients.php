<?php
// ================================================================
// FILE: frontend/pages/reception/patients.php
// RECEPTION - PATIENT MANAGEMENT (V5 - SHARED HEADER/SIDEBAR)
// ✅ Using shared reception_header.php & reception_sidebar.php
// ✅ 3 BUTTONS TU: View, Edit, Assign/Reassign
// ✅ Assign inaunda NEW VISIT kila click
// ✅ AUTO-FILTER: Search & Filter work automatically
// ✅ Doctor status = VISIT ACTIVE
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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

$user_id     = $_SESSION['user_id']     ?? 0;
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$search              = isset($_GET['search'])     ? trim($_GET['search']) : '';
$filter              = isset($_GET['filter'])     ? $_GET['filter']       : 'all';
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$error               = $_GET['error']   ?? '';
$success             = $_GET['success'] ?? '';

$patients       = [];
$total_patients = 0;
$stats          = ['total' => 0, 'with_doctor' => 0, 'without_doctor' => 0, 'new_patients' => 0];

try {
    $db = Database::getInstance()->getConnection();

    // Ensure registered_by columns exist
    try {
        $stmt = $db->query("SHOW COLUMNS FROM patients LIKE 'registered_by'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE `patients` ADD COLUMN `registered_by` INT NULL AFTER `assigned_doctor_id`");
            $db->exec("ALTER TABLE `patients` ADD COLUMN `registered_by_name` VARCHAR(150) NULL AFTER `registered_by`");
        }
    } catch (Exception $e) {}

    // CLEANUP: Safisha patients.assigned_doctor_id kama hakuna visit active
    try {
        $db->exec("
            UPDATE patients p
            SET p.assigned_doctor_id = NULL
            WHERE p.assigned_doctor_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM visits v
                  WHERE v.patient_id = p.id
                    AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed')
              )
        ");
    } catch (Exception $e) {}

    // MAIN QUERY
    $sql = "
        SELECT DISTINCT p.*,
               u.full_name as assigned_doctor_name,
               COALESCE(p.registered_by_name, ru.full_name, 'System') as registered_by_display,
               v.id as active_visit_id,
               v.status as active_visit_status,
               v.visit_number as active_visit_number,
               v.doctor_id as active_visit_doctor_id,
               vu.full_name as active_visit_doctor_name
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        LEFT JOIN users ru ON p.registered_by = ru.id
        LEFT JOIN visits v ON v.patient_id = p.id
            AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed')
            AND v.branch_id = p.branch_id
        LEFT JOIN users vu ON v.doctor_id = vu.id
        WHERE p.branch_id = ?
    ";
    $params = [$branch_id];

    if (!empty($search)) {
        $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($filter === 'with_doctor') {
        $sql .= " AND v.doctor_id IS NOT NULL";
    } elseif ($filter === 'without_doctor') {
        $sql .= " AND (v.doctor_id IS NULL OR v.id IS NULL)";
    } elseif ($filter === 'new') {
        $sql .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter === 'no_visit') {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    }

    $sql .= " ORDER BY p.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove duplicates
    $unique_patients = [];
    $seen_ids = [];
    foreach ($patients_raw as $p) {
        if (!in_array($p['id'], $seen_ids)) {
            $seen_ids[] = $p['id'];
            $unique_patients[] = $p;
        }
    }
    $patients = $unique_patients;

    // ENRICH
    foreach ($patients as $key => $patient) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE patient_id = ?");
        $stmt->execute([$patient['id']]);
        $patients[$key]['total_visits'] = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('scheduled', 'confirmed')");
        $stmt->execute([$patient['id']]);
        $patients[$key]['active_appointments'] = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT DATEDIFF(NOW(), created_at) FROM patients WHERE id = ?");
        $stmt->execute([$patient['id']]);
        $patients[$key]['patient_days'] = (int)$stmt->fetchColumn();

        $patients[$key]['patient_status'] = ($patients[$key]['patient_days'] <= 7) ? 'new' : 'existing';

        $stmt = $db->prepare("SELECT id, visit_number, status, created_at FROM visits WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$patient['id']]);
        $patients[$key]['latest_visit'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $has_active_visit   = !empty($patient['active_visit_id']);
        $active_doctor_name = $patient['active_visit_doctor_name'] ?? null;

        $patients[$key]['has_active_visit']    = $has_active_visit;
        $patients[$key]['display_doctor_name'] = $active_doctor_name;
        $patients[$key]['is_with_doctor']      = !empty($active_doctor_name);

        if (!$has_active_visit && !empty($patient['assigned_doctor_id'])) {
            try {
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient['id']]);
            } catch (Exception $e) {}
            $patients[$key]['assigned_doctor_id']   = null;
            $patients[$key]['assigned_doctor_name'] = null;
        }
    }

    $total_patients = count($patients);

    // STATS
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT p.id) as total,
            SUM(CASE
                WHEN EXISTS (
                    SELECT 1 FROM visits v
                    WHERE v.patient_id = p.id
                      AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed')
                      AND v.doctor_id IS NOT NULL
                ) THEN 1 ELSE 0 END
            ) as with_doctor,
            SUM(CASE
                WHEN NOT EXISTS (
                    SELECT 1 FROM visits v
                    WHERE v.patient_id = p.id
                      AND v.status IN ('new', 'pending', 'assigned', 'with_doctor', 'lab_test', 'waiting', 'prescribed')
                      AND v.doctor_id IS NOT NULL
                ) THEN 1 ELSE 0 END
            ) as without_doctor,
            SUM(CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_patients
        FROM patients p
        WHERE p.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $stats_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stats_row) $stats = $stats_row;

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $patients = [];
    $stats = ['total' => 0, 'with_doctor' => 0, 'without_doctor' => 0, 'new_patients' => 0];
    $total_patients = 0;
}

// Unread notifications
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id']) && isset($db)) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = (int)($stmt->fetch()['total'] ?? 0);
    }
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// NOTE: time_ago() is provided by reception_header.php
// DO NOT redeclare here.
// ================================================================

include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           PATIENTS PAGE - SPECIFIC STYLES ONLY
           (Base styles, variables, .card, .footer, .toast-custom,
            .main-content are provided by reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 50%, #1E40AF 100%);
            border-radius: 20px;
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .page-header .page-title i { font-size: 1.9rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }

        .page-header .page-subtitle strong { color: white; font-weight: 700; }

        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
            transition: all 0.3s ease;
        }

        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-1px);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .update-badge-light {
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(8px);
            font-weight: 600;
        }

        .live-indicator-modern {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            margin-right: 4px;
        }

        /* ---------- STATS CARDS ---------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card-modern {
            border-radius: 16px;
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 110px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .stat-card-modern::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            pointer-events: none;
            transition: all 0.4s ease;
        }

        .stat-card-modern:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-card-modern:hover::before {
            transform: scale(1.25);
            background: rgba(255,255,255,0.15);
        }

        .stat-card-modern.blue   { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; }
        .stat-card-modern.green  { background: linear-gradient(135deg, #059669, #047857); color: white; }
        .stat-card-modern.orange { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
        .stat-card-modern.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; }

        .stat-card-modern .stat-icon {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .stat-card-modern .stat-number {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }

        .stat-card-modern .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        /* ---------- TABLE CARD ---------- */
        .table-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        .table-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-card .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-card .card-title i { color: var(--primary); }

        /* ---------- SCROLL BUTTONS ---------- */
        .table-scroll-controls {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .scroll-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 2px solid var(--primary);
            background: var(--bg-card);
            color: var(--primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            transition: all 0.3s;
            font-weight: 700;
        }

        .scroll-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* ---------- PATIENT TABLE ---------- */
        .patient-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1500px;
        }

        .patient-table thead {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
        }

        .patient-table thead th {
            padding: 12px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            white-space: nowrap;
        }

        .patient-table thead th:first-child { border-radius: 10px 0 0 0; }
        .patient-table thead th:last-child  { border-radius: 0 10px 0 0; }

        .patient-table .col-sno {
            width: 50px;
            text-align: center;
            font-weight: 700;
            color: var(--primary);
            font-size: 0.8rem;
        }

        .patient-table .col-actions {
            width: 150px;
            text-align: center;
        }

        .patient-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary) !important;
        }

        .patient-table tbody td a.patient-name-link {
            color: var(--text-primary) !important;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }

        .patient-table tbody td a.patient-name-link:hover {
            color: var(--primary) !important;
            text-decoration: underline;
        }

        .patient-table tbody tr:hover td {
            background: var(--primary-bg);
        }

        .patient-table tbody tr:last-child td {
            border-bottom: none;
        }

        .patient-table .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .patient-table .status-badge.new            { background: #D1FAE5; color: #059669; }
        .patient-table .status-badge.existing       { background: #E8F0FE; color: #0B5ED7; }
        .patient-table .status-badge.with_doctor    { background: #D1FAE5; color: #059669; }
        .patient-table .status-badge.without_doctor { background: #FEF3C7; color: #D97706; }

        [data-theme="dark"] .patient-table .status-badge.new            { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .patient-table .status-badge.existing       { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .patient-table .status-badge.with_doctor    { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .patient-table .status-badge.without_doctor { background: #3D2E0A; color: #FBBF24; }

        /* Days Badge */
        .days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 3px 12px !important;
            border-radius: 12px !important;
            font-size: 0.62rem !important;
            font-weight: 700 !important;
            border: none !important;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        .days-badge-blue.new {
            background: var(--success) !important;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
        }

        /* Registered By Badge */
        .registered-by-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #EDE9FE;
            color: #7C3AED;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.62rem;
            font-weight: 700;
            white-space: nowrap;
        }

        [data-theme="dark"] .registered-by-badge {
            background: #2D1B5F;
            color: #C4B5FD;
        }

        /* ---------- ACTION BUTTONS ---------- */
        .action-buttons-group {
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
        }

        .action-btn:hover { transform: translateY(-2px) scale(1.08); }

        .action-btn.view {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }
        .action-btn.view:hover { box-shadow: 0 4px 14px rgba(37, 99, 235, 0.5); }

        .action-btn.edit {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            color: white;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
        }
        .action-btn.edit:hover { box-shadow: 0 4px 14px rgba(124, 58, 237, 0.5); }

        .action-btn.assign {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        .action-btn.assign:hover { box-shadow: 0 4px 14px rgba(5, 150, 105, 0.5); }

        .action-btn.reassign {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
        }
        .action-btn.reassign:hover { box-shadow: 0 4px 14px rgba(217, 119, 6, 0.5); }

        .action-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: -28px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 100;
        }

        .action-btn:hover::after {
            opacity: 1;
            bottom: -25px;
        }

        /* ---------- SEARCH & FILTER ---------- */
        .search-filter-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            padding: 10px 14px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .card-header-left  { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .card-header-right { display: flex; align-items: center; gap: 8px;  flex-wrap: wrap; }

        .filter-input {
            padding: 8px 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            font-size: 0.75rem;
            background: rgba(255,255,255,0.95);
            color: #1E293B;
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
            font-weight: 500;
        }

        .filter-input:focus {
            border-color: white;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }

        .filter-input::placeholder {
            color: #64748B;
            opacity: 0.7;
            font-weight: 400;
            font-size: 0.72rem;
        }

        .filter-input option {
            background: white;
            color: #1E293B;
            padding: 8px;
        }

        .auto-filter-loading {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 0.65rem;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .auto-filter-loading.show { display: inline-flex; }

        .auto-filter-loading .spinner-small {
            width: 10px;
            height: 10px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .clear-filter-btn {
            display: none;
            align-items: center;
            gap: 4px;
            padding: 7px 14px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(255,255,255,0.95);
            color: var(--danger);
            border: 2px solid white;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .clear-filter-btn.show { display: inline-flex; }

        .clear-filter-btn:hover {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input-wrapper i {
            position: absolute;
            left: 12px;
            color: var(--primary);
            font-size: 0.75rem;
            pointer-events: none;
            z-index: 1;
        }

        .search-input-wrapper input { padding-left: 34px; min-width: 220px; }

        .filter-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.62rem;
            color: white;
            padding: 5px 12px;
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            font-weight: 600;
        }

        /* ---------- TABLE SCROLL ---------- */
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: visible;
            position: relative;
            border-radius: 8px;
            padding-bottom: 6px;
        }

        .table-scroll-container::-webkit-scrollbar { height: 8px; }
        .table-scroll-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        .table-scroll-container::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

        /* ---------- BUTTONS ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 768px) {
            .page-header { padding: 20px 22px; }
            .page-header .page-title { font-size: 1.3rem; }
            .table-card { padding: 16px; }
            .patient-table { font-size: 0.75rem; }
            .patient-table thead th,
            .patient-table tbody td { padding: 8px; }
            .card-header { flex-direction: column; align-items: stretch; }
            .search-filter-wrapper {
                flex-direction: column;
                width: 100%;
                align-items: stretch;
            }
            .search-filter-wrapper .filter-input { width: 100%; min-width: unset; }
            .search-input-wrapper input { min-width: unset; width: 100%; }
            .card-header-left, .card-header-right { width: 100%; }
            .action-btn { width: 30px; height: 30px; font-size: 0.7rem; }
        }

        @media (max-width: 640px) {
            .page-header { padding: 16px 18px; border-radius: 16px; }
            .page-header .page-title { font-size: 1.15rem; }
            .stat-card-modern .stat-number { font-size: 1.5rem; }
        }

        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED HEADER & SIDEBAR (INCLUDED ABOVE) -->
<!-- ================================================================ -->

<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Patients
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Manage patients in <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <span id="totalPatients"><?= $stats['total'] ?? 0 ?></span> Total
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span style="color:#34D399;font-weight:700;"><?= $stats['with_doctor'] ?? 0 ?></span> With Doctor
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <span style="color:#FBBF24;font-weight:700;"><?= $stats['without_doctor'] ?? 0 ?></span> No Doctor
                </span>
                <span class="header-badge">
                    <i class="fas fa-bolt"></i>
                    <span style="color:#A78BFA;font-weight:700;"><?= $stats['new_patients'] ?? 0 ?></span> New (7d)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="new_patient.php" class="btn-outline-light">
                <i class="fas fa-plus"></i> New Patient
            </a>
            <a href="appointments.php" class="btn-outline-light">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card-modern blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-label">Total Patients</div>
        </div>
        <div class="stat-card-modern green">
            <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            <div class="stat-number"><?= $stats['with_doctor'] ?? 0 ?></div>
            <div class="stat-label">With Doctor</div>
        </div>
        <div class="stat-card-modern orange">
            <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            <div class="stat-number"><?= $stats['without_doctor'] ?? 0 ?></div>
            <div class="stat-label">No Doctor</div>
        </div>
        <div class="stat-card-modern purple">
            <div class="stat-icon"><i class="fas fa-bolt"></i></div>
            <div class="stat-number"><?= $stats['new_patients'] ?? 0 ?></div>
            <div class="stat-label">New (7 days)</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE CARD -->
    <!-- ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <div class="card-header-left">
                <div class="search-filter-wrapper">
                    <div class="auto-filter-loading" id="filterLoading">
                        <div class="spinner-small"></div>
                        <span>Filtering...</span>
                    </div>

                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text"
                               id="searchFilter"
                               class="filter-input"
                               placeholder="Search name, ID, phone..."
                               value="<?= htmlspecialchars($search) ?>"
                               oninput="autoFilter()">
                    </div>

                    <select id="filterSelect" class="filter-input" style="min-width:150px;" onchange="autoFilter()">
                        <option value="all"            <?= $filter === 'all'            ? 'selected' : '' ?>>👥 All</option>
                        <option value="new"            <?= $filter === 'new'            ? 'selected' : '' ?>>🆕 New (7d)</option>
                        <option value="with_doctor"    <?= $filter === 'with_doctor'    ? 'selected' : '' ?>>✅ With Doctor</option>
                        <option value="without_doctor" <?= $filter === 'without_doctor' ? 'selected' : '' ?>>⚠️ No Doctor</option>
                        <option value="no_visit"       <?= $filter === 'no_visit'       ? 'selected' : '' ?>>📋 No Visit</option>
                    </select>

                    <a href="patients.php"
                       class="clear-filter-btn <?= (!empty($search) || $filter !== 'all') ? 'show' : '' ?>"
                       id="clearFilterBtn">
                        <i class="fas fa-times"></i> Clear
                    </a>

                    <span class="filter-status" id="filterStatus">
                        <i class="fas fa-bolt"></i>
                        <span id="filterStatusText">Auto-filter</span>
                    </span>
                </div>
            </div>

            <div class="card-header-right">
                <div class="card-title" style="margin:0;">
                    <i class="fas fa-list"></i> Patient List
                    <span id="patientCountBadge"
                          style="background:var(--primary-bg);color:var(--primary);padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:700;">
                        <?= $total_patients ?> patients
                    </span>
                </div>

                <div class="table-scroll-controls">
                    <button type="button" class="scroll-btn" onclick="scrollTableLeft()" title="Scroll Left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn" onclick="scrollTableRight()" title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- PATIENT TABLE -->
        <div id="tableContainer" class="table-scroll-container">
            <?php if (!empty($patients) && count($patients) > 0): ?>
            <table class="patient-table">
                <thead>
                    <tr>
                        <th class="col-sno">#</th>
                        <th><i class="fas fa-user mr-1"></i> Patient</th>
                        <th><i class="fas fa-id-card mr-1"></i> Patient ID</th>
                        <th><i class="fas fa-phone mr-1"></i> Contact</th>
                        <th><i class="fas fa-calendar mr-1"></i> Days</th>
                        <th><i class="fas fa-user-md mr-1"></i> Doctor</th>
                        <th><i class="fas fa-notes-medical mr-1"></i> Visits</th>
                        <th><i class="fas fa-user-plus mr-1"></i> Registered By</th>
                        <th><i class="fas fa-circle mr-1"></i> Status</th>
                        <th class="col-actions"><i class="fas fa-cog mr-1"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="patientTableBody">
                    <?php $counter = 1; ?>
                    <?php foreach ($patients as $patient):
                        $patient_days = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
                        $days_text = $patient_days > 0
                            ? '<span class="days-badge-blue">📅 ' . $patient_days . ' days</span>'
                            : '<span class="days-badge-blue new">📅 New</span>';

                        $status_class = $patient['patient_status'] ?? 'existing';
                        $status_text  = $status_class === 'new' ? 'New' : 'Existing';

                        if (!empty($patient['display_doctor_name'])) {
                            $doctor_status = '<span class="status-badge with_doctor">✅ Dr. ' . htmlspecialchars($patient['display_doctor_name']) . '</span>';
                            $is_assigned = true;
                        } else {
                            $doctor_status = '<span class="status-badge without_doctor">⚠️ No Doctor</span>';
                            $is_assigned = false;
                        }

                        $appointment_count = $patient['active_appointments'] ?? 0;
                        $registered_by     = $patient['registered_by_display'] ?? 'System';
                    ?>
                        <tr class="patient-row"
                            data-name="<?= strtolower(htmlspecialchars($patient['full_name'] ?? '')) ?>"
                            data-id="<?= strtolower(htmlspecialchars($patient['patient_id'] ?? '')) ?>"
                            data-phone="<?= strtolower(htmlspecialchars($patient['phone'] ?? '')) ?>"
                            data-days="<?= $patient_days ?>"
                            data-has-doctor="<?= $is_assigned ? '1' : '0' ?>"
                            data-visits="<?= $patient['total_visits'] ?? 0 ?>"
                            data-status="<?= $status_class ?>">
                            <td class="col-sno"><?= $counter++ ?></td>
                            <td>
                                <a href="view_patient.php?id=<?= (int)$patient['id'] ?>" class="patient-name-link">
                                    <?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?>
                                </a>
                            </td>
                            <td style="font-family:monospace;font-size:0.8rem;">
                                <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <?php if (!empty($patient['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($patient['phone']) ?>" style="color:var(--text-primary);text-decoration:none;">
                                        <?= htmlspecialchars($patient['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);opacity:0.6;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $days_text ?></td>
                            <td><?= $doctor_status ?></td>
                            <td>
                                <span style="font-weight:700;color:var(--text-primary);"><?= $patient['total_visits'] ?? 0 ?></span>
                                <?php if (!empty($patient['latest_visit']['status'])): ?>
                                    <span style="font-size:0.65rem;color:var(--text-secondary);display:block;">
                                        <?= ucfirst($patient['latest_visit']['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="registered-by-badge">
                                    <i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($registered_by) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                <?php if ($appointment_count > 0): ?>
                                    <span style="font-size:0.65rem;color:#7C3AED;display:block;font-weight:600;">
                                        📅 <?= $appointment_count ?> appt(s)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <!-- 3 BUTTONS: View, Edit, Assign/Reassign -->
                            <td class="col-actions">
                                <div class="action-buttons-group">
                                    <!-- 1. VIEW -->
                                    <a href="view_patient.php?id=<?= (int)$patient['id'] ?>"
                                       class="action-btn view"
                                       data-tooltip="View Patient"
                                       title="View Patient">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <!-- 2. EDIT -->
                                    <a href="edit_patient.php?id=<?= (int)$patient['id'] ?>"
                                       class="action-btn edit"
                                       data-tooltip="Edit Patient"
                                       title="Edit Patient">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <!-- 3. ASSIGN / REASSIGN -->
                                    <?php if ($is_assigned): ?>
                                        <a href="assign_doctor.php?patient_id=<?= (int)$patient['id'] ?>&reassign=1"
                                           class="action-btn reassign"
                                           data-tooltip="Reassign Doctor (New Visit)"
                                           title="Reassign Doctor - Itaunda Visit Mpya">
                                            <i class="fas fa-user-plus"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="assign_doctor.php?patient_id=<?= (int)$patient['id'] ?>"
                                           class="action-btn assign"
                                           data-tooltip="Assign Doctor (New Visit)"
                                           title="Assign Doctor - Itaunda Visit Mpya">
                                            <i class="fas fa-user-md"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="noResultsMessage" style="display:none;text-align:center;padding:40px 20px;">
                <i class="fas fa-search text-4xl text-gray-300 block mb-3"></i>
                <p class="text-gray-500" style="font-size:0.9rem;font-weight:600;">No patients match your search</p>
                <p class="text-gray-400" style="font-size:0.75rem;">Try adjusting your filter criteria</p>
                <button onclick="clearFilter()" class="btn btn-primary mt-3">
                    <i class="fas fa-times"></i> Clear Filter
                </button>
            </div>

            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-users text-4xl text-gray-300 block mb-2"></i>
                <p class="text-gray-500" style="font-weight:600;">No patients found</p>
                <?php if (!empty($search)): ?>
                    <p class="text-sm text-gray-400">Try adjusting your search criteria</p>
                <?php endif; ?>
                <a href="patients.php" class="btn btn-primary mt-3">
                    <i class="fas fa-sync-alt"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Patients
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
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
    // AUTO-FILTER
    // ================================================================
    var filterTimeout = null;

    function autoFilter() {
        var searchValue = document.getElementById('searchFilter').value;
        var filterValue = document.getElementById('filterSelect').value;

        var loading = document.getElementById('filterLoading');
        if (loading) loading.classList.add('show');

        var clearBtn = document.getElementById('clearFilterBtn');
        if (clearBtn) {
            if (searchValue.trim() !== '' || filterValue !== 'all') {
                clearBtn.classList.add('show');
            } else {
                clearBtn.classList.remove('show');
            }
        }

        if (filterTimeout) clearTimeout(filterTimeout);

        filterTimeout = setTimeout(function() {
            filterTableRows(searchValue, filterValue);
            setTimeout(function() {
                if (loading) loading.classList.remove('show');
            }, 300);
        }, 300);
    }

    function filterTableRows(searchValue, filterValue) {
        var rows = document.querySelectorAll('.patient-row');
        var visibleCount = 0;
        var searchLower = searchValue.toLowerCase().trim();

        rows.forEach(function(row) {
            var name      = row.getAttribute('data-name') || '';
            var id        = row.getAttribute('data-id') || '';
            var phone     = row.getAttribute('data-phone') || '';
            var days      = parseInt(row.getAttribute('data-days')) || 0;
            var hasDoctor = row.getAttribute('data-has-doctor') === '1';
            var visits    = parseInt(row.getAttribute('data-visits')) || 0;

            var show = true;

            if (searchLower !== '') {
                var matchesSearch =
                    name.includes(searchLower) ||
                    id.includes(searchLower) ||
                    phone.includes(searchLower);
                if (!matchesSearch) show = false;
            }

            if (filterValue === 'new'            && days > 7)     show = false;
            if (filterValue === 'with_doctor'    && !hasDoctor)   show = false;
            if (filterValue === 'without_doctor' && hasDoctor)    show = false;
            if (filterValue === 'no_visit'       && visits > 0)   show = false;

            if (show) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        updateRowNumbers();

        var countBadge = document.getElementById('patientCountBadge');
        if (countBadge) {
            countBadge.textContent = visibleCount + ' patient' + (visibleCount !== 1 ? 's' : '');
        }

        var statusText = document.getElementById('filterStatusText');
        if (statusText) {
            if (searchLower !== '' || filterValue !== 'all') {
                statusText.textContent = 'Filtered: ' + visibleCount + ' result' + (visibleCount !== 1 ? 's' : '');
            } else {
                statusText.textContent = 'Auto-filter';
            }
        }

        var noResults = document.getElementById('noResultsMessage');
        var table = document.querySelector('.patient-table');

        if (visibleCount === 0 && rows.length > 0) {
            if (noResults) noResults.style.display = 'block';
            if (table) table.style.display = 'none';
        } else {
            if (noResults) noResults.style.display = 'none';
            if (table) table.style.display = 'table';
        }
    }

    function updateRowNumbers() {
        var rows = document.querySelectorAll('.patient-row');
        var counter = 1;
        rows.forEach(function(row) {
            if (row.style.display !== 'none') {
                var snoCell = row.querySelector('.col-sno');
                if (snoCell) snoCell.textContent = counter;
                counter++;
            }
        });
    }

    function scrollTableLeft() {
        var container = document.getElementById('tableContainer');
        if (container) container.scrollBy({ left: -300, behavior: 'smooth' });
    }

    function scrollTableRight() {
        var container = document.getElementById('tableContainer');
        if (container) container.scrollBy({ left: 300, behavior: 'smooth' });
    }

    function clearFilter() {
        document.getElementById('searchFilter').value = '';
        document.getElementById('filterSelect').value = 'all';
        autoFilter();

        var url = new URL(window.location.href);
        url.searchParams.delete('search');
        url.searchParams.delete('filter');
        window.history.replaceState({}, '', url.toString());

        showToast('🔄 Cleared', 'Filters have been cleared', 'info');
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var searchInput = document.getElementById('searchFilter');
            if (searchInput && (searchInput.value !== '' || document.getElementById('filterSelect').value !== 'all')) {
                clearFilter();
            }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchFilter').focus();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var searchValue = document.getElementById('searchFilter').value;
        var filterValue = document.getElementById('filterSelect').value;

        if (searchValue !== '' || filterValue !== 'all') {
            filterTableRows(searchValue, filterValue);
        }
        if (searchValue !== '') {
            document.getElementById('searchFilter').focus();
        }
    });

    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // TOAST (fallback if not defined by header)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            var toastTitle = document.getElementById('toastTitle');
            var toastMessage = document.getElementById('toastMessage');
            toast.className = 'toast-custom ' + (type || 'info');
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.style.display = 'none'; }, 400);
            }, 3500);
        };
    }

    console.log('%c👤 Braick - Patients (Shared Header/Sidebar)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ 3 BUTTONS: View, Edit, Assign/Reassign', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Assign inaunda NEW VISIT kila click', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Doctor status = VISIT ACTIVE', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>