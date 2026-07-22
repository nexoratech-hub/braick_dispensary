<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_test.php
// LABORATORY - VIEW TEST DETAILS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Lab Technician
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET LAB TEST ID
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_test_id <= 0) {
    header('Location: in_progress.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();
$lab_test = null;
$all_tests = [];
$bill_items = [];
$message = '';
$message_type = '';

// ================================================================
// GET LAB TEST DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               p.id as patient_id,
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               p.gender,
               p.date_of_birth,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               u.id as doctor_id,
               u.full_name as doctor_name,
               u.specialty,
               u.phone as doctor_phone,
               v.id as visit_id,
               v.visit_number,
               v.visit_type,
               v.status as visit_status,
               v.symptoms,
               v.diagnosis,
               v.treatment,
               v.created_at as visit_created_at,
               v.completed_at as visit_completed_at,
               lr.id as request_id,
               lr.request_number,
               lr.status as request_status,
               lr.requested_at,
               lr.accepted_at,
               lr.completed_at as request_completed_at,
               b.name as branch_name
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN lab_requests lr ON lt.visit_id = lr.visit_id
        LEFT JOIN branches b ON lt.branch_id = b.id
        WHERE lt.id = ? AND lt.branch_id = ?
    ");
    $stmt->execute([$lab_test_id, $user_branch_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lab_test) {
        header('Location: in_progress.php?error=test_not_found');
        exit;
    }
    
    // ================================================================
    // GET ALL TESTS FOR THIS VISIT
    // ================================================================
    $stmt = $db->prepare("
        SELECT lt.*, 
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = lt.visit_id) as total_tests,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = lt.visit_id AND status = 'completed') as completed_tests
        FROM lab_tests lt
        WHERE lt.visit_id = ?
        ORDER BY lt.created_at ASC
    ");
    $stmt->execute([$lab_test['visit_id']]);
    $all_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET BILL ITEMS FOR THIS VISIT
    // ================================================================
    $stmt = $db->prepare("
        SELECT bi.*, pb.bill_number, pb.status as bill_status, pb.total_amount, pb.paid_amount
        FROM bill_items bi
        JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.visit_id = ?
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$lab_test['visit_id']]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'in_progress' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'accepted' => 'badge-info'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'in_progress' => '🔄 In Progress',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled',
        'accepted' => '📋 Accepted'
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Test - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           STYLES
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
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
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
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
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
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
            font-size: 1.6rem;
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
        
        .role-badge-display {
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
        
        .header-badge {
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
        
        .btn-outline-light {
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
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: var(--purple); }
        .card-title .title-orange { color: var(--warning); }
        
        /* ================================================================
           DETAIL ROWS
           ================================================================ */
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 130px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        [data-theme="dark"] .detail-row {
            border-color: var(--gray-700);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        
        .status-badge.pending {
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .status-badge.in_progress {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background: #B45309;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .table-container thead {
            background: var(--primary);
            color: white;
        }
        
        .table-container thead th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .table-container tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .table-container tbody tr:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .table-container tbody tr:hover {
            background: #1E3A5F;
        }
        
        .table-container tbody td {
            padding: 8px 14px;
            vertical-align: middle;
            color: var(--text-primary);
        }
        
        .table-container tbody tr:last-child {
            border-bottom: none;
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
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
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
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
            .card { padding: 14px 16px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .table-container thead th { padding: 6px 8px; font-size: 0.6rem; }
            .table-container tbody td { padding: 4px 8px; font-size: 0.7rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .card { padding: 10px 12px; }
            .page-title { font-size: 1.1rem; }
        }
        
        .font-mono { font-family: monospace; }
        .font-semibold { font-weight: 600; }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }
        .text-danger { color: var(--danger); }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        
        .patient-avatar-lg {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        
        .summary-box {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--primary-light);
            text-align: center;
        }
        
        [data-theme="dark"] .summary-box {
            background: #1E3A5F;
            border-color: var(--primary);
        }
        
        .summary-box .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .summary-box .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
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
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($lab_test): ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Test Details
                <span class="role-badge-display">LABORATORY</span>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($lab_test['request_number'] ?? 'N/A') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?>)
                <span class="separator">|</span>
                Test: <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Status: 
                <span class="status-badge <?= $lab_test['status'] ?? 'pending' ?>">
                    <?= getStatusLabel($lab_test['status'] ?? 'pending') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="in_progress.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if (($lab_test['status'] ?? '') !== 'completed'): ?>
                <a href="add_result.php?id=<?= $lab_test_id ?>" class="btn-outline-light" style="background:rgba(255,255,255,0.25);">
                    <i class="fas fa-edit"></i> Add Result
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SUMMARY STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="summary-box">
            <p class="number"><?= count($all_tests) ?></p>
            <p class="label">Total Tests</p>
        </div>
        <div class="summary-box">
            <p class="number"><?= $lab_test['completed_tests'] ?? 0 ?></p>
            <p class="label">Completed</p>
        </div>
        <div class="summary-box">
            <p class="number"><?= $lab_test['completed_tests'] > 0 ? round(($lab_test['completed_tests'] / count($all_tests)) * 100) : 0 ?>%</p>
            <p class="label">Progress</p>
        </div>
        <div class="summary-box">
            <p class="number"><?= count($bill_items) ?></p>
            <p class="label">Bill Items</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST DETAILS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Test Information -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i>
                Test Information
            </h3>
            <div class="detail-row">
                <span class="detail-label">Test Name</span>
                <span class="detail-value font-semibold"><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Test Type</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['test_type'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Sample Type</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['sample_type'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?= $lab_test['status'] ?? 'pending' ?>">
                        <?= getStatusLabel($lab_test['status'] ?? 'pending') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Request Number</span>
                <span class="detail-value font-mono"><?= htmlspecialchars($lab_test['request_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Request Status</span>
                <span class="detail-value">
                    <span class="badge <?= getStatusBadgeClass($lab_test['request_status'] ?? 'pending') ?>">
                        <?= getStatusLabel($lab_test['request_status'] ?? 'pending') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Requested At</span>
                <span class="detail-value"><?= formatDate($lab_test['requested_at'] ?? '') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Accepted At</span>
                <span class="detail-value"><?= formatDate($lab_test['accepted_at'] ?? '') ?></span>
            </div>
            <?php if (!empty($lab_test['results'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Result</span>
                    <span class="detail-value font-semibold text-success"><?= nl2br(htmlspecialchars($lab_test['results'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Completed At</span>
                    <span class="detail-value"><?= formatDate($lab_test['completed_at'] ?? '') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['notes'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Notes</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($lab_test['notes'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Patient Information -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-user title-green mr-2"></i>
                Patient Information
            </h3>
            <div style="display:flex;align-items:center;gap:16px;padding:12px 16px;background:var(--primary-bg);border-radius:var(--radius);margin-bottom:16px;">
                <div class="patient-avatar-lg" style="background: <?= getUserColor($lab_test['patient_name'] ?? 'Unknown') ?>;">
                    <?= strtoupper(substr($lab_test['patient_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <h4 class="text-lg font-semibold"><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></h4>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($lab_test['gender'] ?? '') ?> • <?= date('M d, Y', strtotime($lab_test['date_of_birth'] ?? 'now')) ?></p>
                </div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Allergies</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['allergies'] ?? 'None') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Address</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['address'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Emergency Contact</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['emergency_contact'] ?? 'N/A') ?></span>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR & VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Doctor Information -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-user-md title-blue mr-2"></i>
                Doctor Information
            </h3>
            <div class="detail-row">
                <span class="detail-label">Doctor Name</span>
                <span class="detail-value font-semibold">Dr. <?= htmlspecialchars($lab_test['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Specialty</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['specialty'] ?? 'General Practitioner') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['doctor_phone'] ?? 'N/A') ?></span>
            </div>
        </div>
        
        <!-- Visit Information -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-clinic-medical title-green mr-2"></i>
                Visit Information
            </h3>
            <div class="detail-row">
                <span class="detail-label">Visit Number</span>
                <span class="detail-value font-mono"><?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit Type</span>
                <span class="detail-value"><?= ucfirst($lab_test['visit_type'] ?? 'New') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit Status</span>
                <span class="detail-value">
                    <span class="badge <?= getStatusBadgeClass($lab_test['visit_status'] ?? 'pending') ?>">
                        <?= ucfirst(str_replace('_', ' ', $lab_test['visit_status'] ?? 'Pending')) ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created At</span>
                <span class="detail-value"><?= formatDate($lab_test['visit_created_at'] ?? '') ?></span>
            </div>
            <?php if (!empty($lab_test['visit_completed_at'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Completed At</span>
                    <span class="detail-value"><?= formatDate($lab_test['visit_completed_at']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['symptoms'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Symptoms</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($lab_test['symptoms'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['diagnosis'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Diagnosis</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($lab_test['diagnosis'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- ALL TESTS FOR THIS VISIT -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <h3 class="card-title">
            <i class="fas fa-list title-purple mr-2"></i>
            All Tests for This Visit
            <span class="text-sm font-normal text-gray-400">(<?= count($all_tests) ?> tests)</span>
        </h3>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Created</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($all_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                                    <?= getStatusLabel($test['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($test['results'])): ?>
                                    <span class="text-success font-semibold"><?= htmlspecialchars(substr($test['results'], 0, 30)) ?></span>
                                    <?php if (strlen($test['results']) > 30): ?>...<?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not yet</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm"><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                            <td style="text-align:center;">
                                <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (($test['status'] ?? '') !== 'completed'): ?>
                                    <a href="add_result.php?id=<?= $test['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS -->
    <!-- ================================================================ -->
    <?php if (count($bill_items) > 0): ?>
        <div class="card mb-5">
            <h3 class="card-title">
                <i class="fas fa-receipt title-orange mr-2"></i>
                Bill Items
                <span class="text-sm font-normal text-gray-400">(<?= count($bill_items) ?> items)</span>
            </h3>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($item['item_type'] ?? 'N/A') ?></span></td>
                                <td><?= $item['quantity'] ?? 1 ?></td>
                                <td style="text-align:right;"><?= formatCurrency($item['unit_price'] ?? 0) ?></td>
                                <td style="text-align:right;font-weight:600;"><?= formatCurrency($item['total_price'] ?? 0) ?></td>
                                <td>
                                    <span class="badge <?= ($item['is_paid'] ?? 0) ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ($item['is_paid'] ?? 0) ? '✅ Paid' : '⏳ Pending' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTIONS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="flex flex-wrap gap-3">
            <a href="in_progress.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <?php if (($lab_test['status'] ?? '') !== 'completed'): ?>
                <a href="add_result.php?id=<?= $lab_test_id ?>" class="btn btn-success">
                    <i class="fas fa-edit"></i> Add Result
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if (($lab_test['status'] ?? '') !== 'completed'): ?>
                <form method="POST" action="in_progress.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="lab_test_id" value="<?= $lab_test_id ?>">
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Mark this test as completed?')">
                        <i class="fas fa-check-circle"></i> Mark Completed
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-flask text-4xl block mb-3"></i>
            <p class="text-lg">Test not found</p>
            <a href="in_progress.php" class="text-primary hover:underline">Back to In Progress</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Test
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
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
        var footer = document.getElementById('footerTimestamp');
        if (footer) {
            footer.textContent = 'Last updated: ' + timeStr;
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
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c🧪 View Test Details', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= $lab_test['status'] ?? 'N/A' ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>