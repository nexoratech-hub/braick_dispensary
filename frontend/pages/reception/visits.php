<?php
// ================================================================
// FILE: frontend/pages/reception/visits.php
// RECEPTION - VISITS LIST (BRANCH FILTERED)
// WITH GLOBAL STATS AUTO-UPDATE (3 SECONDS)
// FIXED: Black text + Small Visit # column
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$period_filter = $_GET['period'] ?? '';
$search = $_GET['search'] ?? '';

// ================================================================
// DATE FILTER PERIODS
// ================================================================
if (!empty($period_filter)) {
    switch ($period_filter) {
        case 'today':
            $date_filter = date('Y-m-d');
            break;
        case '7days':
            $date_filter = date('Y-m-d', strtotime('-7 days'));
            break;
        case '1month':
            $date_filter = date('Y-m-d', strtotime('-1 month'));
            break;
        case '3months':
            $date_filter = date('Y-m-d', strtotime('-3 months'));
            break;
        case '6months':
            $date_filter = date('Y-m-d', strtotime('-6 months'));
            break;
        case 'yearly':
            $date_filter = date('Y-m-d', strtotime('-1 year'));
            break;
        case 'all':
        default:
            $date_filter = '';
            $period_filter = 'all';
            break;
    }
}

try {
    $db = getDB();
    
    // ================================================================
    // BUILD QUERY WITH DATE RANGE
    // ================================================================
    $query = "
        SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone,
               u.full_name as doctor_name, u.specialty
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
    
    // Date range filter
    if (!empty($date_filter) && $period_filter !== 'all') {
        $query .= " AND DATE(v.created_at) >= ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY v.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $visits = $stmt->fetchAll();
    
    // ================================================================
    // STATUS COUNTS
    // ================================================================
    $status_counts = [];
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
        $status_counts[$status] = $stmt->fetch()['count'] ?? 0;
    }
    
    // Get online doctors count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $online_doctors = $stmt->fetch()['total'] ?? 0;
    
    // Get total doctors
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $total_doctors = $stmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    $visits = [];
    $status_counts = [];
    $online_doctors = 0;
    $total_doctors = 0;
}

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
    <title>Visits - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
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
        
        /* ================================================================
           TOP NAV
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - IMPROVED
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
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
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .header-badge .online-count {
            color: #34D399;
            font-weight: 700;
        }
        
        .page-header .header-badge .period-badge {
            color: #FCD34D;
            font-weight: 600;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
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
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        
        .filter-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .filter-btn {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group-item {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .form-control {
            padding: 4px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .form-control select {
            cursor: pointer;
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
        }
        
        .table-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .table-card .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-card .table-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table-card .table-title .title-blue {
            color: var(--primary);
        }
        
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 900px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: #1A202C !important;
            vertical-align: middle;
            font-size: 0.78rem;
        }
        
        .data-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           VISIT NUMBER BADGE - SMALL BLACK TEXT
           ================================================================ */
        .visit-number-badge {
            display: inline-block;
            font-family: 'Courier New', monospace;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 4px;
            background: #E2E8F0;
            color: #1A202C;
            border: 1px solid #CBD5E1;
            letter-spacing: 0.3px;
        }
        
        [data-theme="dark"] .visit-number-badge {
            background: #2D3748;
            color: #E2E8F0;
            border-color: #4A5568;
        }
        
        /* ================================================================
           TABLE - BLACK TEXT IN LIGHT MODE
           ================================================================ */
        .data-table .font-medium {
            color: #1A202C !important;
            font-weight: 600;
        }
        
        .data-table .text-sm {
            color: #2D3748 !important;
        }
        
        .data-table .text-xs {
            color: #4A5568 !important;
        }
        
        .data-table .text-gray-700 {
            color: #2D3748 !important;
        }
        
        .data-table .text-gray-800 {
            color: #1A202C !important;
        }
        
        .data-table .text-gray-600 {
            color: #4A5568 !important;
        }
        
        .data-table .text-gray-500 {
            color: #4A5568 !important;
        }
        
        .data-table .text-red-600 {
            color: #DC2626 !important;
        }
        
        /* ================================================================
           DARK MODE - FULL OPACITY TEXT
           ================================================================ */
        [data-theme="dark"] .data-table td {
            color: #F1F5F9 !important;
            opacity: 1 !important;
        }
        
        [data-theme="dark"] .data-table .font-medium {
            color: #F1F5F9 !important;
            opacity: 1 !important;
        }
        
        [data-theme="dark"] .data-table .text-sm {
            color: #CBD5E1 !important;
            opacity: 1 !important;
        }
        
        [data-theme="dark"] .data-table .text-xs {
            color: #94A3B8 !important;
            opacity: 1 !important;
        }
        
        [data-theme="dark"] .data-table .visit-number-badge {
            color: #E2E8F0 !important;
            opacity: 1 !important;
        }
        
        [data-theme="dark"] .data-table .text-gray-800 {
            color: #F1F5F9 !important;
        }
        
        [data-theme="dark"] .data-table .text-gray-600 {
            color: #CBD5E1 !important;
        }
        
        [data-theme="dark"] .data-table .text-gray-500 {
            color: #94A3B8 !important;
        }
        
        [data-theme="dark"] .data-table .text-red-600 {
            color: #F87171 !important;
        }
        
        [data-theme="dark"] .data-table .text-gray-700 {
            color: #E2E8F0 !important;
        }
        
        /* ================================================================
           STATUS BADGES - DARK MODE COMPATIBLE
           ================================================================ */
        .status-badge-visit {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 12px;
        }
        
        .status-badge-visit.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.assigned { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.with_doctor { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge-visit.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.assigned { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-visit.with_doctor { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-visit.cancelled { background: #3A1A1A; color: #F87171; }
        
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-blue {
            background: var(--primary);
            color: white;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-green {
            background: var(--success);
            color: white;
        }
        .btn-green:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
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
        
        .btn-sm { padding: 2px 8px; font-size: 0.65rem; border-radius: 4px; }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-card .stat-number.green { color: #059669; }
        .stat-card .stat-number.yellow { color: #D97706; }
        .stat-card .stat-number.blue { color: #0B5ED7; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.gray { color: #94A3B8; }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card .stat-icon {
            font-size: 1.2rem;
            margin-bottom: 2px;
        }
        
        /* ================================================================
           BADGES DISPLAY
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
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
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .data-table { font-size: 0.7rem; min-width: 750px; }
            .data-table th, .data-table td { padding: 6px 8px; }
            .filter-group { flex-direction: column; align-items: stretch; }
            .filter-group-item { width: 100%; }
            .filter-group-item .form-control { width: 100%; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .data-table { font-size: 0.6rem; min-width: 650px; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .btn-sm { padding: 2px 6px; font-size: 0.55rem; }
            .table-card .table-header { flex-direction: column; align-items: stretch; text-align: center; }
            .table-footer { flex-direction: column; text-align: center; }
            .stat-card .stat-number { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search visits..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - IMPROVED -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Visits
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Manage all patient visits in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors ?></span> Online
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-list"></i>
                    <span id="totalRecordsCount"><?= count($visits) ?></span> records
                </span>
                
                <?php if (!empty($period_filter) && $period_filter !== 'all'): ?>
                    <span class="header-badge">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="period-badge">
                            <?php 
                                $period_labels = [
                                    'today' => 'Today',
                                    '7days' => 'Last 7 Days',
                                    '1month' => '1 Month',
                                    '3months' => '3 Months',
                                    '6months' => '6 Months',
                                    'yearly' => 'Yearly'
                                ];
                                echo $period_labels[$period_filter] ?? ucfirst($period_filter);
                            ?>
                        </span>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="assign_doctor.php" class="btn-outline-light">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-card animate-fade-in-up">
        <div class="filter-group">
            <!-- Status Filters -->
            <div class="filter-group-item">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 mr-1">Status:</span>
                <a href="visits.php?period=<?= $period_filter ?>&search=<?= urlencode($search) ?>" 
                   class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">All (<?= array_sum($status_counts) ?>)</a>
                <?php foreach ($status_counts as $status => $count): ?>
                    <a href="visits.php?status=<?= $status ?>&period=<?= $period_filter ?>&search=<?= urlencode($search) ?>" 
                       class="filter-btn <?= $status_filter === $status ? 'active' : '' ?>">
                        <?= ucfirst(str_replace('_', ' ', $status)) ?> (<?= $count ?>)
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Period Filter -->
            <div class="filter-group-item">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 mr-1">📆 Period:</span>
                <select id="periodFilter" class="form-control" 
                        onchange="window.location.href='visits.php?period='+this.value+'&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>'"
                        style="width:auto;min-width:140px;padding:4px 12px;font-size:0.8rem;">
                    <option value="all" <?= $period_filter === 'all' || empty($period_filter) ? 'selected' : '' ?>>📋 All</option>
                    <option value="today" <?= $period_filter === 'today' ? 'selected' : '' ?>>📅 Today</option>
                    <option value="7days" <?= $period_filter === '7days' ? 'selected' : '' ?>>📆 Last 7 Days</option>
                    <option value="1month" <?= $period_filter === '1month' ? 'selected' : '' ?>>📆 1 Month</option>
                    <option value="3months" <?= $period_filter === '3months' ? 'selected' : '' ?>>📆 3 Months</option>
                    <option value="6months" <?= $period_filter === '6months' ? 'selected' : '' ?>>📆 6 Months</option>
                    <option value="yearly" <?= $period_filter === 'yearly' ? 'selected' : '' ?>>📆 Yearly</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS TABLE -->
    <!-- ================================================================ -->
    <div class="table-card animate-fade-in-up">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-list title-blue mr-2"></i> Visits List
                <span class="text-sm font-normal text-gray-400">(<strong id="recordsCount"><?= count($visits) ?></strong> records)</span>
            </h3>
            <span class="text-xs text-gray-400">Scroll to view all</span>
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
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="visitsTableBody">
                    <?php if (count($visits) > 0): ?>
                        <?php $i = 1; foreach ($visits as $visit): ?>
                            <tr class="visit-row">
                                <td class="text-gray-700 dark:text-gray-300"><?= $i++ ?></td>
                                <td>
                                    <span class="visit-number-badge">
                                        <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-gray-800 dark:text-gray-100"><?= htmlspecialchars($visit['patient_name']) ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($visit['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <?php if ($visit['doctor_name']): ?>
                                        <div class="text-sm text-gray-800 dark:text-gray-100">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($visit['specialty'] ?? 'GP') ?></div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs capitalize <?= $visit['visit_type'] === 'emergency' ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-300' ?>">
                                        <?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge-visit <?= $visit['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-xs text-gray-600 dark:text-gray-300">
                                    <?= isset($visit['created_at']) ? date('M d, Y h:i A', strtotime($visit['created_at'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <div class="flex gap-1 justify-center">
                                        <a href="visit_details.php?id=<?= $visit['id'] ?>" 
                                           class="btn btn-blue btn-sm" title="View Visit">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="view_patient.php?id=<?= $visit['patient_id'] ?>" 
                                           class="btn btn-outline btn-sm" title="View Patient" 
                                           style="border-color:var(--primary);color:var(--primary);">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                                            <a href="visit_status.php?id=<?= $visit['id'] ?>&status=completed&redirect=visits.php" 
                                               class="btn btn-outline btn-sm" title="Complete" 
                                               style="border-color:var(--success);color:var(--success);">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400 dark:text-gray-500">
                                <i class="fas fa-clinic-medical text-3xl block mb-2"></i>
                                <?php if (!empty($search) || !empty($status_filter) || !empty($period_filter)): ?>
                                    No visits found matching the filters
                                <?php else: ?>
                                    No visits recorded yet
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="table-footer">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-calendar-alt mr-1"></i> 
                Showing <strong id="footerRecordsCount"><?= count($visits) ?></strong> visit(s)
            </span>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-user mr-1"></i> 
                Branch: <strong><?= htmlspecialchars($branch_name) ?></strong>
            </span>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-clock mr-1"></i> 
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mt-5">
        <?php foreach ($status_counts as $status => $count): ?>
            <div class="stat-card <?= $status === 'completed' ? 'border-green-500' : ($status === 'pending' || $status === 'assigned' ? 'border-yellow-500' : '') ?>">
                <div class="stat-icon <?= $status === 'completed' ? 'text-green-500' : ($status === 'cancelled' ? 'text-red-500' : 'text-yellow-500') ?>">
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
                    <?php endif; ?>
                </div>
                <p class="stat-number <?= $status === 'completed' ? 'green' : ($status === 'cancelled' ? 'red' : ($status === 'pending' ? 'yellow' : ($status === 'assigned' ? 'blue' : 'gray'))) ?>">
                    <?= $count ?>
                </p>
                <p class="stat-label capitalize"><?= ucfirst(str_replace('_', ' ', $status)) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visits
            <span class="text-gray-300 mx-2">|</span>
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
<!-- JAVASCRIPT - WITH GLOBAL STATS AUTO-UPDATE (3 SECONDS) -->
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
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
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
        var status = '<?= $status_filter ?>';
        var period = '<?= $period_filter ?>';
        window.location.href = 'visits.php?search=' + encodeURIComponent(query) + '&status=' + status + '&period=' + period;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
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
    // GLOBAL STATS AUTO-UPDATE (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;

    function fetchAndUpdateStats() {
        if (isUpdating) return;
        isUpdating = true;
        
        fetch('/dispensary_system/frontend/api/get_global_stats.php?t=' + new Date().getTime())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var stats = data.stats || {};
                    var onlineCount = stats.online_doctors || 0;
                    
                    // Update online doctors count
                    document.getElementById('onlineDoctorCount').textContent = onlineCount;
                    
                    // Update update badge
                    var now = new Date();
                    document.getElementById('updateBadge').innerHTML = 
                        '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + 
                        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    document.getElementById('footerTimestamp').textContent = 'Last updated: ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Update error:', error);
                isUpdating = false;
            });
    }

    // ================================================================
    // START / STOP AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(fetchAndUpdateStats, 3000);
        fetchAndUpdateStats();
    }

    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        fetchAndUpdateStats();
        window.location.reload();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Visits data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'assign_doctor.php';
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            searchInput.blur();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1500);
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c🏥 Braick - Visits List (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📆 Period: <?= $period_filter ?: 'All' ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Online doctors count)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Black text in table', 'font-size:13px; color:#1A202C;');
    console.log('%c✅ Small Visit # badge with gray background', 'font-size:13px; color:#4A5568;');
</script>

</body>
</html>