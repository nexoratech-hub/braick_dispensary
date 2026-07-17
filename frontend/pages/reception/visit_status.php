<?php
// ================================================================
// FILE: frontend/pages/reception/visit_status.php
// RECEPTION - UPDATE VISIT STATUS
// WITH GLOBAL STATS AUTO-UPDATE (3 SECONDS)
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

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_status = isset($_GET['status']) ? $_GET['status'] : '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'visits.php';
$message = '';
$message_type = '';

if ($visit_id <= 0) {
    header('Location: ' . $redirect);
    exit;
}

try {
    $db = getDB();
    
    // Get visit details first
    $stmt = $db->prepare("
        SELECT v.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$visit_id, $_SESSION['branch_id']]);
    $visit = $stmt->fetch();
    
    if (!$visit) {
        header('Location: ' . $redirect);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'assigned', 'with_doctor', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        header('Location: ' . $redirect);
        exit;
    }
    
    // Update visit status
    $stmt = $db->prepare("UPDATE visits SET status = ?, updated_at = NOW() WHERE id = ?");
    
    if ($stmt->execute([$new_status, $visit_id])) {
        $message = "Visit status updated to: " . ucfirst(str_replace('_', ' ', $new_status));
        $message_type = 'success';
        
        // If status is completed, also update visit as completed
        if ($new_status === 'completed') {
            $stmt = $db->prepare("UPDATE visits SET is_completed = 1, completed_at = NOW() WHERE id = ?");
            $stmt->execute([$visit_id]);
            
            // Update patient bill if exists
            try {
                $stmt = $db->prepare("UPDATE patient_bills SET status = 'paid' WHERE visit_id = ? AND status = 'pending'");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
        }
        
        // If status is assigned, update doctor assignment
        if ($new_status === 'assigned' && $visit['doctor_id']) {
            $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
            $stmt->execute([$visit['doctor_id'], $visit['patient_id']]);
        }
        
        // Log activity
        try {
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'visit_status_updated', ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], "Visit ID: $visit_id status changed to $new_status"]);
        } catch (Exception $e) {}
        
    } else {
        $message = "Failed to update visit status!";
        $message_type = 'error';
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
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
    <title>Visit Status - Braick Dispensary</title>
    
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
           STATUS CARD
           ================================================================ */
        .status-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            max-width: 700px;
            margin: 0 auto;
            transition: all 0.3s ease;
        }
        
        .status-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }
        
        .status-card .status-icon {
            font-size: 4rem;
            margin-bottom: 12px;
        }
        
        .status-card .status-icon.success { color: #059669; }
        .status-card .status-icon.error { color: #DC2626; }
        .status-card .status-icon.info { color: #0B5ED7; }
        .status-card .status-icon.warning { color: #D97706; }
        
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
            border-radius: 12px;
            padding: 16px 20px;
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
        
        .status-card .status-details .detail-value .text-muted {
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        /* ================================================================
           STATUS BADGE DISPLAY
           ================================================================ */
        .status-badge-display {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
        }
        
        .status-badge-display.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-display.assigned { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-display.with_doctor { background: #FEF3C7; color: #D97706; }
        .status-badge-display.completed { background: #D1FAE5; color: #059669; }
        .status-badge-display.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge-display.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-display.assigned { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-display.with_doctor { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-display.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
        
        .btn-sm { padding: 4px 12px; font-size: 0.72rem; border-radius: 8px; }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 16px;
        }
        
        /* ================================================================
           BADGES
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
            max-width: 400px;
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
           MESSAGE ALERT
           ================================================================ */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-dark);
            border: 1px solid var(--success);
        }
        
        .alert-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.1rem;
            margin-top: 2px;
        }
        
        .alert .alert-content {
            flex: 1;
        }
        
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
            .status-card { padding: 20px; }
            .status-card .status-icon { font-size: 3rem; }
            .status-card .status-title { font-size: 1.2rem; }
            .btn { padding: 8px 16px; font-size: 0.78rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .status-card { padding: 14px; }
            .status-card .status-details .detail-row { font-size: 0.75rem; flex-direction: column; gap: 2px; }
            .btn-group { flex-direction: column; align-items: stretch; }
            .btn-group .btn { width: 100%; justify-content: center; }
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($_SESSION['branch_name'] ?? 'Dodoma') ?>
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
                Visit Status
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Update visit status
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount">0</span> Online
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    Visit #<strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong>
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="<?= $redirect ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATUS CARD -->
    <!-- ================================================================ -->
    <div class="status-card animate-fade-in-up">
        <div class="text-center">
            <div class="status-icon <?= $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'error' : 'info') ?>">
                <?php if ($message_type === 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php elseif ($message_type === 'error'): ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php else: ?>
                    <i class="fas fa-clinic-medical"></i>
                <?php endif; ?>
            </div>
            
            <h2 class="status-title">
                <?php if ($message_type === 'success'): ?>
                    Status Updated Successfully!
                <?php elseif ($message_type === 'error'): ?>
                    Update Failed
                <?php else: ?>
                    Visit Details
                <?php endif; ?>
            </h2>
            
            <p class="status-message"><?= htmlspecialchars($message) ?></p>
            
            <!-- Visit Details -->
            <?php if (isset($visit)): ?>
            <div class="status-details">
                <div class="detail-row">
                    <span class="detail-label">Visit Number</span>
                    <span class="detail-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Patient</span>
                    <span class="detail-value"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Patient ID</span>
                    <span class="detail-value"><?= htmlspecialchars($visit['patient_id'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Doctor</span>
                    <span class="detail-value">
                        <?php if ($visit['doctor_name']): ?>
                            Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Visit Type</span>
                    <span class="detail-value capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time</span>
                    <span class="detail-value"><?= isset($visit['created_at']) ? date('F d, Y h:i A', strtotime($visit['created_at'])) : 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge-display <?= $new_status ?? $visit['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $new_status ?? $visit['status'])) ?>
                        </span>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="btn-group">
                <a href="<?= $redirect ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="visits.php" class="btn btn-outline">
                    <i class="fas fa-clinic-medical"></i> View All Visits
                </a>
                <?php if ($message_type === 'success' && isset($visit)): ?>
                    <a href="view_patient.php?id=<?= $visit['patient_id'] ?>" class="btn btn-success">
                        <i class="fas fa-user"></i> View Patient
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <?php if (isset($visit) && $visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5" style="max-width:700px;margin:20px auto 0;">
        <a href="visit_status.php?id=<?= $visit_id ?>&status=assigned&redirect=<?= urlencode($redirect) ?>" 
           class="card text-center hover:border-primary transition p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md">
            <i class="fas fa-user-md text-blue-500 text-2xl block mb-2"></i>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign Doctor</span>
        </a>
        <a href="visit_status.php?id=<?= $visit_id ?>&status=with_doctor&redirect=<?= urlencode($redirect) ?>" 
           class="card text-center hover:border-primary transition p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md">
            <i class="fas fa-stethoscope text-yellow-500 text-2xl block mb-2"></i>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">With Doctor</span>
        </a>
        <a href="visit_status.php?id=<?= $visit_id ?>&status=completed&redirect=<?= urlencode($redirect) ?>" 
           class="card text-center hover:border-primary transition p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md">
            <i class="fas fa-check-circle text-green-500 text-2xl block mb-2"></i>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Complete Visit</span>
        </a>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Status
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
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
    console.log('%c🏥 Braick - Visit Status', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit ID: <?= $visit_id ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 New Status: <?= ucfirst(str_replace('_', ' ', $new_status ?? 'N/A')) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Online doctors count)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>