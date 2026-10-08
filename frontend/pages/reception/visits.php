<?php
// ================================================================
// FILE: frontend/pages/reception/visits.php
// RECEPTION - VISITS LIST (BRANCH FILTERED)
// ✅ USING SHARED HEADER & SIDEBAR
// WITH GLOBAL STATS AUTO-UPDATE (3 SECONDS)
// FIXED: Using NEW DATABASE (dispensary_db)
// BRAICK DISPENSARY
// UPDATED: Modern header + footer (V2)
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

$user_branch_id     = $branch_id;
$selected_branch_id = $branch_id;
$status_filter      = $_GET['status'] ?? '';
$date_filter        = $_GET['date'] ?? '';
$period_filter      = $_GET['period'] ?? '';
$search             = $_GET['search'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// DATE FILTER PERIODS
// ================================================================
if (!empty($period_filter)) {
    switch ($period_filter) {
        case 'today':    $date_filter = date('Y-m-d'); break;
        case '7days':    $date_filter = date('Y-m-d', strtotime('-7 days')); break;
        case '1month':   $date_filter = date('Y-m-d', strtotime('-1 month')); break;
        case '3months':  $date_filter = date('Y-m-d', strtotime('-3 months')); break;
        case '6months':  $date_filter = date('Y-m-d', strtotime('-6 months')); break;
        case 'yearly':   $date_filter = date('Y-m-d', strtotime('-1 year')); break;
        case 'all':
        default:
            $date_filter = '';
            $period_filter = 'all';
            break;
    }
}

// ================================================================
// FETCH DATA
// ================================================================
$visits               = [];
$status_counts        = [];
$online_doctors       = 0;
$total_doctors        = 0;
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();

    // ---------- UNREAD NOTIFICATIONS ----------
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_notifications = $result['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }

    // ---------- VISITS ----------
    $query = "
        SELECT v.*, 
               p.full_name as patient_name, 
               p.patient_id, 
               p.phone,
               u.full_name as doctor_name, 
               u.specialty
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.branch_id = ?
    ";
    $params = [$selected_branch_id];

    if (!empty($status_filter)) {
        $query .= " AND v.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($date_filter) && $period_filter !== 'all') {
        $query .= " AND DATE(v.created_at) >= ?";
        $params[] = $date_filter;
    }

    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $query .= " ORDER BY v.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- STATUS COUNTS ----------
    $statuses = ['pending', 'assigned', 'with_doctor', 'completed', 'cancelled'];
    foreach ($statuses as $status) {
        $sql = "SELECT COUNT(*) as count FROM visits WHERE status = ? AND branch_id = ?";
        $params_status = [$status, $selected_branch_id];
        if (!empty($date_filter) && $period_filter !== 'all') {
            $sql .= " AND DATE(created_at) >= ?";
            $params_status[] = $date_filter;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params_status);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status_counts[$status] = $result['count'] ?? 0;
    }

    // ---------- ONLINE DOCTORS ----------
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $online_doctors = $result['total'] ?? 0;

    // ---------- TOTAL DOCTORS ----------
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_doctors = $result['total'] ?? 0;

} catch (Exception $e) {
    // Keep defaults
}

// ================================================================
// ASSETS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PERIOD LABELS
// ================================================================
$period_labels = [
    'today'   => 'Today',
    '7days'   => 'Last 7 Days',
    '1month'  => '1 Month',
    '3months' => '3 Months',
    '6months' => '6 Months',
    'yearly'  => 'Yearly',
    'all'     => 'All'
];

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
    <title>Visits - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           VISITS PAGE - SPECIFIC STYLES
           (Shared styles from reception_header.php)
           ================================================================ */

        :root {
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        }

        /* ================================================================
           MODERN PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D87 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            box-shadow: 
                0 10px 30px rgba(11, 94, 215, 0.28),
                0 1px 0 rgba(255,255,255,0.1) inset;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .page-header::before {
            content: '';
            position: absolute;
            width: 380px;
            height: 380px;
            top: -200px;
            right: -100px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
            z-index: 0;
        }
        .page-header::after {
            content: '';
            position: absolute;
            width: 240px;
            height: 240px;
            bottom: -140px;
            left: -60px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        .page-header-left {
            flex: 1;
            min-width: 280px;
            position: relative;
            z-index: 1;
        }

        .page-header-title-row {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .page-header-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .page-header-title-text h1 {
            font-size: 1.55rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        .page-header-title-text .sub {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.75);
            margin: 3px 0 0 0;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 400;
        }

        .page-header-title-text .sub strong {
            color: #fff;
            font-weight: 600;
        }

        .header-pills {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
            line-height: 1;
            border: 1px solid transparent;
        }

        .pill-role {
            background: rgba(255,255,255,0.22);
            color: #fff;
            border-color: rgba(255,255,255,0.25);
            backdrop-filter: blur(6px);
        }

        .pill-live {
            background: rgba(52, 211, 153, 0.18);
            color: #6EE7B7;
            border-color: rgba(52, 211, 153, 0.35);
        }

        .pill-live .live-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.25);
            animation: pulse-dot 1.8s infinite;
        }

        .page-header-badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 13px;
            border-radius: 10px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            color: rgba(255,255,255,0.95);
            font-size: 0.72rem;
            font-weight: 500;
            transition: all 0.25s ease;
            line-height: 1;
        }

        .info-badge:hover {
            background: rgba(255,255,255,0.18);
            border-color: rgba(255,255,255,0.28);
            transform: translateY(-1px);
        }

        .info-badge i {
            font-size: 0.72rem;
            opacity: 0.9;
        }

        .info-badge .badge-value {
            color: #fff;
            font-weight: 700;
        }

        .info-badge .badge-value.online {
            color: #6EE7B7;
        }

        .info-badge .badge-value.gold {
            color: #FCD34D;
        }

        .info-badge .badge-value.purple {
            color: #C4B5FD;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .action-btn-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 11px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            line-height: 1;
            white-space: nowrap;
        }

        .action-btn-header i {
            font-size: 0.85rem;
        }

        .action-btn-header.primary {
            background: #fff;
            color: #0A4CA8;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        }
        .action-btn-header.primary:hover {
            background: #F8FAFC;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0,0,0,0.18);
            color: #083D87;
        }

        .action-btn-header.glass {
            background: rgba(255,255,255,0.13);
            color: #fff;
            border-color: rgba(255,255,255,0.22);
            backdrop-filter: blur(8px);
        }
        .action-btn-header.glass:hover {
            background: rgba(255,255,255,0.24);
            border-color: rgba(255,255,255,0.35);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: #fff;
        }

        /* ================================================================
           FILTER CARD
           ================================================================ */
        .filter-card {
            background: var(--bg-card, #fff);
            border-radius: 16px;
            padding: 18px 22px;
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            margin-bottom: 20px;
        }
        .filter-card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        }

        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            border: 2px solid var(--border-color, #E2E8F0);
            background: transparent;
            color: var(--text-secondary, #64748B);
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .filter-btn:hover {
            border-color: var(--primary, #0B5ED7);
            color: var(--primary, #0B5ED7);
            transform: translateY(-1px);
        }
        .filter-btn.active {
            background: var(--primary, #0B5ED7);
            color: #fff;
            border-color: var(--primary, #0B5ED7);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }
        .filter-group-item {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary, #64748B);
            margin-right: 4px;
        }

        .form-control {
            padding: 6px 14px;
            border: 2px solid var(--border-color, #E2E8F0);
            border-radius: 10px;
            font-size: 0.8rem;
            background: var(--bg-card, #fff);
            color: var(--text-primary, #1E293B);
            outline: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        .form-control:focus {
            border-color: var(--primary, #0B5ED7);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }

        /* ================================================================
           TABLE CARD
           ================================================================ */
        .table-card {
            background: var(--bg-card, #fff);
            border-radius: 16px;
            border: 1px solid var(--border-color, #E2E8F0);
            overflow: hidden;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            transition: all 0.3s;
        }
        .table-card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        }

        .table-card .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            flex-wrap: wrap;
            gap: 10px;
            background: linear-gradient(180deg, rgba(11, 94, 215, 0.03), transparent);
        }
        .table-card .table-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary, #1E293B);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .table-card .table-title .title-blue { color: var(--primary, #0B5ED7); }

        .table-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 580px;
        }
        .table-wrap::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--bg-body, #F1F5F9); }
        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--primary, #0B5ED7);
            border-radius: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 900px;
        }
        .data-table thead th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #fff;
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            border-bottom: 2px solid var(--primary-dark, #0A4CA8);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            color: var(--text-primary, #1E293B);
            vertical-align: middle;
        }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody tr:hover td { background: var(--primary-bg, #E8F0FE); }
        .data-table tbody tr:last-child td { border-bottom: none; }

        /* ================================================================
           VISIT NUMBER BADGE
           ================================================================ */
        .visit-number-badge {
            display: inline-block;
            font-family: var(--font-mono);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 8px;
            background: var(--bg-body, #F1F5F9);
            color: var(--text-primary, #1E293B);
            border: 1px solid var(--border-color, #E2E8F0);
            letter-spacing: 0.4px;
        }

        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge-visit {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            white-space: nowrap;
        }
        .status-badge-visit.pending      { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.assigned     { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.with_doctor  { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed    { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled    { background: #FEE2E2; color: #DC2626; }

        [data-theme="dark"] .status-badge-visit.pending     { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.assigned    { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-visit.with_doctor { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.completed   { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-visit.cancelled   { background: #3A1A1A; color: #F87171; }

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-blue {
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            color: #fff;
        }
        .btn-blue:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
            color: #fff;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.72rem;
            border-radius: 8px;
        }

        /* ================================================================
           QUICK STAT CARDS - MODERN
           ================================================================ */
        .quick-stat-card-modern {
            border-radius: 16px;
            padding: 20px 18px;
            text-align: center;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            color: #fff;
            isolation: isolate;
            border: 1px solid rgba(255,255,255,0.1);
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .quick-stat-card-modern::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255,255,255,0.10);
            pointer-events: none;
            transition: all 0.4s ease;
        }
        .quick-stat-card-modern:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.18);
        }
        .quick-stat-card-modern:hover::before {
            transform: scale(1.25);
            background: rgba(255,255,255,0.15);
        }

        .quick-stat-card-modern.pending      { background: linear-gradient(135deg, #D97706, #B45309); }
        .quick-stat-card-modern.assigned     { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .quick-stat-card-modern.with_doctor  { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .quick-stat-card-modern.completed    { background: linear-gradient(135deg, #059669, #047857); }
        .quick-stat-card-modern.cancelled    { background: linear-gradient(135deg, #DC2626, #B91C1C); }

        .quick-stat-card-modern .stat-icon {
            font-size: 1.5rem;
            opacity: 0.9;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        .quick-stat-card-modern .stat-number {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }
        .quick-stat-card-modern .stat-label {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 22px;
            border-top: 1px solid var(--border-color, #E2E8F0);
            flex-wrap: wrap;
            gap: 10px;
            background: linear-gradient(0deg, rgba(11, 94, 215, 0.03), transparent);
            font-size: 0.78rem;
            color: var(--text-secondary, #64748B);
        }
        .table-footer strong { color: var(--text-primary, #1E293B); font-weight: 600; }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary, #64748B);
        }
        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
            opacity: 0.4;
        }
        .empty-state p { font-size: 0.85rem; }

        /* ================================================================
           MODERN FOOTER
           ================================================================ */
        .footer-modern {
            margin-top: 40px;
            padding: 28px 0 16px 0;
            border-top: 2px solid var(--border-color, #E2E8F0);
            position: relative;
        }
        
        .footer-modern::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary, #0B5ED7), var(--success, #059669));
            border-radius: 2px;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .footer-brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .footer-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
            flex-shrink: 0;
        }
        
        .footer-brand-text h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary, #1E293B);
            margin: 0;
            letter-spacing: -0.01em;
        }
        
        .footer-brand-text p {
            font-size: 0.68rem;
            color: var(--text-secondary, #64748B);
            margin: 2px 0 0 0;
        }
        
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .footer-link {
            font-size: 0.72rem;
            color: var(--text-secondary, #64748B);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        
        .footer-link:hover {
            color: var(--primary, #0B5ED7);
            background: var(--primary-bg, #E8F0FE);
            transform: translateY(-1px);
        }
        
        .footer-link i {
            font-size: 0.65rem;
            opacity: 0.75;
        }
        
        .footer-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge-footer {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            background: var(--success-bg, #D1FAE5);
            color: var(--success, #059669);
            border: 1px solid rgba(5, 150, 105, 0.15);
        }
        
        [data-theme="dark"] .status-badge-footer {
            background: #1A3A2A;
            color: #34D399;
            border-color: rgba(52, 211, 153, 0.2);
        }
        
        .status-dot-footer {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
            animation: pulse-dot 1.8s infinite;
            flex-shrink: 0;
        }
        
        .footer-bottom {
            padding-top: 16px;
            border-top: 1px dashed var(--border-color, #E2E8F0);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        
        .footer-copyright {
            font-size: 0.68rem;
            color: var(--text-secondary, #64748B);
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }
        
        .footer-copyright strong {
            color: var(--primary, #0B5ED7);
            font-weight: 600;
        }
        
        .footer-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.65rem;
            color: var(--text-secondary, #64748B);
        }
        
        .footer-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .footer-meta-item i {
            font-size: 0.6rem;
            color: var(--primary, #0B5ED7);
            opacity: 0.7;
        }
        
        .footer-meta-divider {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--border-color, #E2E8F0);
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .page-header { padding: 24px 26px; gap: 20px; }
            .page-header-title-text h1 { font-size: 1.35rem; }
            .page-header-icon { width: 46px; height: 46px; font-size: 1.2rem; }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px 22px;
                border-radius: 16px;
                flex-direction: column;
                align-items: stretch;
                gap: 18px;
            }
            .page-header-left { min-width: 0; }
            .page-header-actions {
                justify-content: flex-start;
                padding-top: 4px;
            }
            .action-btn-header { flex: 1; justify-content: center; min-width: 120px; }
            
            .data-table { font-size: 0.72rem; min-width: 800px; }
            .data-table th, .data-table td { padding: 8px 10px; }
            .filter-group { flex-direction: column; align-items: stretch; }
            .filter-group-item { width: 100%; }
            .filter-group-item .form-control { flex: 1; }
            .table-card .table-header { flex-direction: column; align-items: stretch; text-align: center; }
            .table-footer { flex-direction: column; text-align: center; }
            
            .footer-modern { margin-top: 30px; padding: 20px 0 12px 0; }
            .footer-content { flex-direction: column; align-items: flex-start; gap: 16px; }
            .footer-links { width: 100%; justify-content: flex-start; }
            .footer-bottom { flex-direction: column; align-items: flex-start; gap: 10px; }
            .footer-meta { width: 100%; flex-wrap: wrap; }
        }

        @media (max-width: 640px) {
            .page-header { padding: 18px 18px; }
            .page-header-title-text h1 { font-size: 1.15rem; }
            .page-header-title-text .sub { font-size: 0.72rem; }
            .page-header-icon { width: 40px; height: 40px; font-size: 1.05rem; border-radius: 11px; }
            .info-badge { font-size: 0.66rem; padding: 6px 10px; }
            .info-badge i { font-size: 0.65rem; }
            .action-btn-header { font-size: 0.75rem; padding: 9px 14px; }
            
            .data-table { font-size: 0.65rem; min-width: 700px; }
            .data-table th, .data-table td { padding: 6px 8px; }
            .btn-sm { padding: 4px 8px; font-size: 0.65rem; }
            .quick-stat-card-modern .stat-number { font-size: 1.5rem; }
            
            .footer-brand-text h4 { font-size: 0.82rem; }
            .footer-brand-text p  { font-size: 0.62rem; }
            .footer-logo { width: 36px; height: 36px; font-size: 0.95rem; }
            .footer-link { font-size: 0.68rem; padding: 5px 10px; }
            .footer-meta { font-size: 0.6rem; gap: 8px; }
            .footer-meta-divider { display: none; }
        }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.25); opacity: 0.75; }
        }
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
    </style>
</head>
<body>

<main class="main-content">

    <!-- ============================================================ -->
    <!-- MODERN PAGE HEADER -->
    <!-- ============================================================ -->
    <div class="page-header animate-fade-in-up">
        
        <!-- LEFT SIDE -->
        <div class="page-header-left">
            
            <div class="page-header-title-row">
                <div class="page-header-icon">
                    <i class="fas fa-clinic-medical"></i>
                </div>
                <div class="page-header-title-text">
                    <h1>Visits</h1>
                    <p class="sub">
                        Manage all patient visits in <strong><?= htmlspecialchars($branch_name) ?></strong>
                    </p>
                </div>
                <div class="header-pills">
                    <span class="pill pill-role">
                        <i class="fas fa-user-tie" style="font-size:0.6rem;"></i>
                        Reception
                    </span>
                    <span class="pill pill-live">
                        <span class="live-dot"></span>
                        Live
                    </span>
                </div>
            </div>
            
            <div class="page-header-badges">
                <span class="info-badge">
                    <i class="fas fa-user-md"></i>
                    <span><span class="badge-value online" id="onlineDoctorCount"><?= (int)$online_doctors ?></span> Online</span>
                </span>
                <span class="info-badge">
                    <i class="fas fa-list"></i>
                    <span><span class="badge-value" id="totalRecordsCount"><?= count($visits) ?></span> Records</span>
                </span>
                <?php if (!empty($period_filter) && $period_filter !== 'all'): ?>
                    <span class="info-badge">
                        <i class="fas fa-calendar-alt"></i>
                        <span><span class="badge-value gold"><?= htmlspecialchars($period_labels[$period_filter] ?? ucfirst($period_filter)) ?></span></span>
                    </span>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- RIGHT SIDE: ACTIONS -->
        <div class="page-header-actions">
            <a href="assign_doctor.php" class="action-btn-header primary">
                <i class="fas fa-user-md"></i>
                Assign Doctor
            </a>
            <button onclick="manualRefresh()" class="action-btn-header glass" id="refreshBtn">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </button>
        </div>
        
    </div>

    <!-- ============================================================ -->
    <!-- FILTERS -->
    <!-- ============================================================ -->
    <div class="filter-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-group">
            <div class="filter-group-item">
                <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
                <a href="visits.php?period=<?= urlencode($period_filter) ?>&search=<?= urlencode($search) ?>"
                   class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
                    All (<?= (int)array_sum($status_counts) ?>)
                </a>
                <?php foreach ($status_counts as $status => $count): ?>
                    <a href="visits.php?status=<?= urlencode($status) ?>&period=<?= urlencode($period_filter) ?>&search=<?= urlencode($search) ?>"
                       class="filter-btn <?= $status_filter === $status ? 'active' : '' ?>">
                        <?= ucfirst(str_replace('_', ' ', $status)) ?> (<?= (int)$count ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="filter-group-item">
                <span class="filter-label"><i class="fas fa-calendar-alt"></i> Period:</span>
                <select id="periodFilter" class="form-control"
                        onchange="window.location.href='visits.php?period='+this.value+'&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>'"
                        style="min-width:150px;">
                    <option value="all"      <?= $period_filter === 'all' || empty($period_filter) ? 'selected' : '' ?>>📋 All Time</option>
                    <option value="today"    <?= $period_filter === 'today' ? 'selected' : '' ?>>📅 Today</option>
                    <option value="7days"    <?= $period_filter === '7days' ? 'selected' : '' ?>>📆 Last 7 Days</option>
                    <option value="1month"   <?= $period_filter === '1month' ? 'selected' : '' ?>>📆 1 Month</option>
                    <option value="3months"  <?= $period_filter === '3months' ? 'selected' : '' ?>>📆 3 Months</option>
                    <option value="6months"  <?= $period_filter === '6months' ? 'selected' : '' ?>>📆 6 Months</option>
                    <option value="yearly"   <?= $period_filter === 'yearly' ? 'selected' : '' ?>>📆 Yearly</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- VISITS TABLE -->
    <!-- ============================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-list title-blue"></i>
                Visits List
                <span style="font-size:0.78rem;font-weight:500;color:var(--text-secondary);">
                    (<strong id="recordsCount"><?= count($visits) ?></strong> records)
                </span>
            </h3>
            <span style="font-size:0.72rem;color:var(--text-secondary);">
                <i class="fas fa-arrows-alt-h"></i> Scroll to view all
            </span>
        </div>

        <div class="table-wrap" id="tableScroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visit #</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody id="visitsTableBody">
                    <?php if (count($visits) > 0): ?>
                        <?php $i = 1; foreach ($visits as $visit): ?>
                            <tr class="visit-row">
                                <td><strong><?= $i++ ?></strong></td>
                                <td>
                                    <span class="visit-number-badge">
                                        <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600;color:var(--text-primary);">
                                        <?= htmlspecialchars($visit['patient_name']) ?>
                                    </div>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($visit['patient_id'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($visit['doctor_name']): ?>
                                        <div style="font-size:0.82rem;color:var(--text-primary);font-weight:500;">
                                            Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </div>
                                        <div style="font-size:0.7rem;color:var(--text-secondary);">
                                            <?= htmlspecialchars($visit['specialty'] ?? 'GP') ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:0.72rem;color:var(--text-secondary);">
                                            <i class="fas fa-user-slash"></i> Not assigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem;text-transform:capitalize;font-weight:600;
                                        color:<?= $visit['visit_type'] === 'emergency' ? '#DC2626' : 'var(--text-secondary)' ?>;">
                                        <?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge-visit <?= htmlspecialchars($visit['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;color:var(--text-secondary);">
                                    <?= isset($visit['created_at']) ? date('M d, Y h:i A', strtotime($visit['created_at'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;justify-content:center;">
                                        <a href="visit_details.php?id=<?= (int)$visit['id'] ?>"
                                           class="btn btn-blue btn-sm" title="View Visit Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-clinic-medical"></i>
                                    <?php if (!empty($search) || !empty($status_filter) || !empty($period_filter)): ?>
                                        <p>No visits found matching the filters</p>
                                    <?php else: ?>
                                        <p>No visits recorded yet</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                <i class="fas fa-calendar-alt"></i>
                Showing <strong id="footerRecordsCount"><?= count($visits) ?></strong> visit(s)
            </span>
            <span>
                <i class="fas fa-user"></i>
                Branch: <strong><?= htmlspecialchars($branch_name) ?></strong>
            </span>
            <span>
                <i class="fas fa-clock"></i>
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            </span>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- QUICK STATS - MODERN -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mt-5">
        <?php foreach ($status_counts as $status => $count): ?>
            <div class="quick-stat-card-modern <?= htmlspecialchars($status) ?> animate-fade-in-up" 
                 style="animation-delay:0.15s;">
                <div class="stat-icon">
                    <?php if ($status === 'completed'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif ($status === 'cancelled'): ?>
                        <i class="fas fa-times-circle"></i>
                    <?php elseif ($status === 'pending'): ?>
                        <i class="fas fa-clock"></i>
                    <?php elseif ($status === 'assigned'): ?>
                        <i class="fas fa-user-md"></i>
                    <?php elseif ($status === 'with_doctor'): ?>
                        <i class="fas fa-stethoscope"></i>
                    <?php else: ?>
                        <i class="fas fa-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="stat-number"><?= (int)$count ?></div>
                <div class="stat-label"><?= ucfirst(str_replace('_', ' ', $status)) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ============================================================ -->
    <!-- MODERN FOOTER -->
    <!-- ============================================================ -->
    <footer class="footer-modern">
        
        <div class="footer-content">
            
            <div class="footer-brand-section">
                <div class="footer-logo">
                    <i class="fas fa-clinic-medical"></i>
                </div>
                <div class="footer-brand-text">
                    <h4>Braick Dispensary</h4>
                    <p>Management System</p>
                </div>
            </div>
            
            <div class="footer-links">
                <a href="dashboard.php" class="footer-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="patients.php" class="footer-link">
                    <i class="fas fa-users"></i> Patients
                </a>
                <a href="visits.php" class="footer-link">
                    <i class="fas fa-clinic-medical"></i> Visits
                </a>
                <a href="appointments.php" class="footer-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
                <a href="profile.php" class="footer-link">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
            </div>
            
            <div class="footer-status">
                <span class="status-badge-footer">
                    <span class="status-dot-footer"></span>
                    System Online
                </span>
            </div>
            
        </div>
        
        <div class="footer-bottom">
            
            <p class="footer-copyright">
                &copy; <?= date('Y') ?> <strong>Braick Dispensary</strong> — All rights reserved.
            </p>
            
            <div class="footer-meta">
                <span class="footer-meta-item">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($full_name) ?>
                </span>
                <span class="footer-meta-divider"></span>
                <span class="footer-meta-item">
                    <i class="fas fa-store-alt"></i>
                    <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="footer-meta-divider"></span>
                <span class="footer-meta-item">
                    <i class="fas fa-clock"></i>
                    <span id="footerTimestampBottom"><?= date('h:i:s A') ?></span>
                </span>
                <span class="footer-meta-divider"></span>
                <span class="footer-meta-item">
                    <i class="fas fa-code-branch"></i>
                    v1.0.0
                </span>
            </div>
            
        </div>
        
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
        if (ft) ft.textContent = 'Last updated: ' + timeStr;
        const ftb = document.getElementById('footerTimestampBottom');
        if (ftb) ftb.textContent = timeStr;
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
    // GLOBAL STATS AUTO-UPDATE (3 SECONDS)
    // ================================================================
    let updateInterval = null;
    let isUpdating = false;

    function fetchAndUpdateStats() {
        if (isUpdating) return;
        isUpdating = true;

        fetch('/dispensary_system/frontend/api/get_global_stats.php?t=' + Date.now())
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats || {};
                    const onlineCount = stats.online_doctors || 0;

                    const onlineEl = document.getElementById('onlineDoctorCount');
                    if (onlineEl) onlineEl.textContent = onlineCount;

                    isUpdating = false;
                } else {
                    isUpdating = false;
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                isUpdating = false;
            });
    }

    // ================================================================
    // START / STOP AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(fetchAndUpdateStats, 3000);
        fetchAndUpdateStats();
    }
    function stopAutoUpdate() {
        if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        const btn = document.getElementById('refreshBtn');
        if (!btn) return;
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;

        fetchAndUpdateStats();
        setTimeout(() => { window.location.reload(); }, 800);
    }

    // ================================================================
    // VISIBILITY CHANGE
    // ================================================================
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopAutoUpdate();
        else startAutoUpdate();
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'assign_doctor.php';
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(startAutoUpdate, 1500);
    });

    console.log('%c🏥 Braick - Visits (Modern Header + Footer)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Modern header + footer', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>