<?php
// ================================================================
// FILE: frontend/pages/admin/view_employee.php
// SUPER ADMIN - VIEW EMPLOYEE DETAILS
// VIEW SINGLE EMPLOYEE INFORMATION
// BRAICK DISPENSARY - USING YOUR DATABASE
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// VERIFY USER EXISTS IN DATABASE
// ================================================================
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// GET EMPLOYEE ID
// ================================================================
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH EMPLOYEE DETAILS - USING YOUR DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.username,
        u.password,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.branch_id,
        u.status,
        u.profile_pic,
        u.specialty,
        u.is_online,
        u.last_online,
        u.created_at,
        u.updated_at,
        b.name as branch_name,
        b.location as branch_location,
        b.phone as branch_phone,
        b.email as branch_email,
        (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as total_activities,
        (SELECT COUNT(*) FROM patients WHERE created_by = u.id) as total_patients,
        (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as total_visits,
        (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as total_prescriptions,
        (SELECT COUNT(*) FROM lab_tests WHERE doctor_id = u.id) as total_lab_tests,
        (SELECT COUNT(*) FROM bills WHERE created_by = u.id) as total_bills
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
$stmt = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$employee_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ROLE LABEL
// ================================================================
$role_labels = [
    'doctor' => 'Doctor',
    'reception' => 'Receptionist',
    'pharmacy' => 'Pharmacist',
    'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier'
];
$role_display = $role_labels[$employee['role']] ?? ucfirst($employee['role']);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// Employee profile pic
$employee_profile_pic_url = !empty($employee['profile_pic']) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $employee['profile_pic'] 
    : '';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// TIME AGO FUNCTION
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'Just now';
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #0F172A;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 16px;
            --radius-sm: 10px;
            --table-hover: #F8FAFC;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.4);
            --table-hover: #1E293B;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 20px 24px;
            background: var(--bg-body);
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }

        .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            margin: 4px 0 0 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .page-subtitle strong {
            color: white;
        }

        .branch-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
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

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #D97706, #B45309);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--bg-body);
            border-color: #0B5ED7;
            color: #0B5ED7;
        }

        .btn-activity {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            color: white;
        }

        .btn-activity:hover {
            background: linear-gradient(135deg, #6D28D9, #5B21B6);
        }

        .btn-delete {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
        }

        .btn-reactivate {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }

        .btn-reactivate:hover {
            background: linear-gradient(135deg, #047857, #065F46);
        }

        .btn-danger {
            background: #EF4444;
            color: white;
        }

        .btn-danger:hover {
            background: #DC2626;
        }

        .btn-success {
            background: #059669;
            color: white;
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* ================================================================
           PROFILE CARD
           ================================================================ */
        .profile-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }

        .profile-card:hover {
            border-color: #0B5ED7;
            box-shadow: var(--shadow-md);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 24px 28px;
            background: linear-gradient(135deg, #0B5ED7 0%, #1A73E8 100%);
        }

        [data-theme="dark"] .profile-header {
            background: linear-gradient(135deg, #0A4CA8 0%, #0B5ED7 100%);
        }

        .profile-avatar {
            flex-shrink: 0;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
            background: white;
        }

        .profile-img-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: white;
            background: rgba(255,255,255,0.2);
            border: 4px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(4px);
        }

        .profile-info h2 {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .profile-info .profile-username {
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
            margin: 2px 0 8px 0;
        }

        .profile-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .profile-badges .role-badge,
        .profile-badges .status-badge {
            padding: 4px 14px;
            font-size: 0.7rem;
            border-radius: 20px;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: white;
            backdrop-filter: blur(4px);
        }

        .profile-badges .status-badge.online {
            background: rgba(5, 150, 105, 0.3);
            color: #34D399;
        }

        .profile-badges .status-badge.offline {
            background: rgba(100, 116, 139, 0.3);
            color: #94A3B8;
        }

        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 18px 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .card:hover {
            border-color: #0B5ED7;
            box-shadow: var(--shadow-md);
        }

        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
        }

        .title-blue { color: #0B5ED7; }
        .title-green { color: #059669; }
        .title-purple { color: #7C3AED; }
        .title-orange { color: #F59E0B; }

        /* ================================================================
           DETAIL ITEMS
           ================================================================ */
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .detail-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .detail-value .role-badge,
        .detail-value .status-badge {
            font-size: 0.7rem;
            padding: 2px 12px;
        }

        /* ================================================================
           ROLE BADGES
           ================================================================ */
        .role-badge.role-doctor { background: #E8F0FE; color: #0B5ED7; }
        .role-badge.role-reception { background: #D1FAE5; color: #059669; }
        .role-badge.role-pharmacy { background: #FEF3C7; color: #D97706; }
        .role-badge.role-laboratory { background: #EDE9FE; color: #7B2FBE; }
        .role-badge.role-cashier { background: #FCE4EC; color: #DC2626; }

        [data-theme="dark"] .role-badge.role-doctor { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .role-badge.role-reception { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .role-badge.role-pharmacy { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .role-badge.role-laboratory { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .role-badge.role-cashier { background: #3A1A1A; color: #F87171; }

        /* Status Badges */
        .status-badge.active { background: #D1FAE5; color: #059669; }
        .status-badge.inactive { background: #FEE2E2; color: #DC2626; }

        [data-theme="dark"] .status-badge.active { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.inactive { background: #3A1A1A; color: #F87171; }

        /* ================================================================
           MODAL
           ================================================================ */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            cursor: pointer;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            max-width: 480px;
            width: 90%;
            position: relative;
            z-index: 1001;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { transform: scale(0.9) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0 4px;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 20px;
            color: var(--text-primary);
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            margin-top: 30px;
            padding: 16px 20px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .footer p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .footer-brand {
            font-weight: 700;
            color: #0B5ED7;
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .grid-cols-3 { grid-template-columns: 1fr 1fr !important; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .page-header { padding: 16px 18px; }
            .page-title { font-size: 1.2rem; }
            .profile-header { flex-direction: column; text-align: center; padding: 20px; }
            .profile-info h2 { font-size: 1.4rem; }
            .profile-img, .profile-img-placeholder { width: 80px; height: 80px; font-size: 2rem; }
            .profile-badges { justify-content: center; }
            .detail-item { flex-direction: column; align-items: flex-start; gap: 2px; }
            .grid-cols-3 { grid-template-columns: 1fr !important; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .modal-content { width: 95%; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .btn { font-size: 0.7rem; padding: 5px 10px; }
            .btn-sm { font-size: 0.6rem; padding: 3px 6px; }
            .page-title { font-size: 1rem; }
            .profile-header { padding: 16px; }
            .profile-img, .profile-img-placeholder { width: 64px; height: 64px; font-size: 1.5rem; }
            .profile-info h2 { font-size: 1.2rem; }
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

        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, #sidebarToggle, .btn, .dark-toggle-btn,
            .icon-btn, .search-wrapper, .footer, .profile-card .role-badge,
            .page-header .flex.gap-2 { display: none !important; }
            .main-content { padding: 0 !important; background: white !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .profile-card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .profile-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .profile-info h2, .profile-info .profile-username,
            .profile-badges .role-badge, .profile-badges .status-badge {
                color: white !important;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                Employee Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-edit btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEE PROFILE CARD -->
    <!-- ================================================================ -->
    <div class="profile-card animate-fade-in-up">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($employee_profile_pic_url) && file_exists($_SERVER['DOCUMENT_ROOT'] . $employee_profile_pic_url)): ?>
                    <img src="<?= $employee_profile_pic_url ?>" 
                         alt="<?= htmlspecialchars($employee['full_name']) ?>" 
                         class="profile-img">
                <?php else: ?>
                    <div class="profile-img-placeholder" style="background: <?= '#' . substr(md5($employee['full_name']), 0, 6) ?>;">
                        <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?= htmlspecialchars($employee['full_name']) ?></h2>
                <p class="profile-username">@<?= htmlspecialchars($employee['username']) ?></p>
                <div class="profile-badges">
                    <span class="role-badge role-<?= $employee['role'] ?>">
                        <i class="fas fa-user-tag"></i> <?= $role_display ?>
                    </span>
                    <span class="status-badge <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= $employee['status'] === 'active' ? 'Active' : 'Inactive' ?>
                    </span>
                    <?php if ($employee['is_online'] == 1): ?>
                        <span class="status-badge online">
                            <i class="fas fa-circle text-green-500"></i> Online
                        </span>
                    <?php else: ?>
                        <span class="status-badge offline">
                            <i class="fas fa-circle text-gray-400"></i> Offline
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEE DETAILS GRID -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5 animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- Personal Information -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-user title-blue mr-2"></i> Personal Information
            </h3>
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?= htmlspecialchars($employee['full_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Username</span>
                <span class="detail-value">@<?= htmlspecialchars($employee['username']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($employee['email']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($employee['phone'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($employee['specialty'])): ?>
            <div class="detail-item">
                <span class="detail-label">Specialty</span>
                <span class="detail-value"><?= htmlspecialchars($employee['specialty']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Branch & Role Information -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-building title-green mr-2"></i> Branch & Role
            </h3>
            <div class="detail-item">
                <span class="detail-label">Branch</span>
                <span class="detail-value">
                    <i class="fas fa-store-alt mr-1"></i>
                    <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Location</span>
                <span class="detail-value"><?= htmlspecialchars($employee['branch_location'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Branch Phone</span>
                <span class="detail-value"><?= htmlspecialchars($employee['branch_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Role</span>
                <span class="detail-value">
                    <span class="role-badge role-<?= $employee['role'] ?>" style="font-size: 0.75rem;">
                        <?= $role_display ?>
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>" style="font-size: 0.75rem;">
                        <?= $employee['status'] === 'active' ? 'Active' : 'Inactive' ?>
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Online Status</span>
                <span class="detail-value">
                    <?php if ($employee['is_online'] == 1): ?>
                        <span class="text-green-600 dark:text-green-400">
                            <i class="fas fa-circle text-green-500"></i> Online
                        </span>
                    <?php else: ?>
                        <span class="text-gray-500">
                            <i class="fas fa-circle text-gray-400"></i> Offline
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($employee['last_online']): ?>
            <div class="detail-item">
                <span class="detail-label">Last Online</span>
                <span class="detail-value"><?= date('M d, Y H:i:s', strtotime($employee['last_online'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-chart-bar title-purple mr-2"></i> Statistics
            </h3>
            <div class="detail-item">
                <span class="detail-label">Total Activities</span>
                <span class="detail-value font-bold text-blue-600"><?= number_format($employee['total_activities'] ?? 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Patients Registered</span>
                <span class="detail-value font-bold text-green-600"><?= number_format($employee['total_patients'] ?? 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Visits</span>
                <span class="detail-value font-bold text-purple-600"><?= number_format($employee['total_visits'] ?? 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Prescriptions</span>
                <span class="detail-value font-bold text-orange-600"><?= number_format($employee['total_prescriptions'] ?? 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Lab Tests</span>
                <span class="detail-value font-bold text-purple-600"><?= number_format($employee['total_lab_tests'] ?? 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Bills Created</span>
                <span class="detail-value font-bold text-green-600"><?= number_format($employee['total_bills'] ?? 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Member Since</span>
                <span class="detail-value"><?= date('M d, Y', strtotime($employee['created_at'])) ?></span>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="flex justify-between items-center mb-3">
            <h3 class="card-title" style="margin-bottom:0;">
                <i class="fas fa-clock title-blue mr-2"></i> Recent Activities
                <span class="text-xs text-gray-400 font-normal">(Last 10 activities)</span>
            </h3>
            <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
        </div>
        <?php if (count($recent_activities) > 0): ?>
            <div class="space-y-2 max-h-60 overflow-y-auto">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                        <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 text-white">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-sm text-gray-800 dark:text-gray-200">
                                <?= htmlspecialchars($activity['action'] ?? 'Action') ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($activity['details'] ?? '') ?>
                            </p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-gray-400 text-sm py-5">
                <i class="fas fa-inbox text-2xl block mb-2"></i>
                No activities found for this employee
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
        <h3 class="card-title" style="margin-bottom:12px;">
            <i class="fas fa-bolt title-blue mr-2"></i> Quick Actions
        </h3>
        <div class="flex flex-wrap gap-2">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn btn-edit btn-sm">
                <i class="fas fa-edit"></i> Edit Employee
            </a>
            <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn btn-activity btn-sm">
                <i class="fas fa-clock"></i> View All Activities
            </a>
            <?php if ($employee['status'] === 'active'): ?>
                <button onclick="confirmDelete(<?= $employee['id'] ?>, '<?= htmlspecialchars($employee['full_name']) ?>')" 
                        class="btn btn-delete btn-sm">
                    <i class="fas fa-user-slash"></i> Deactivate
                </button>
            <?php else: ?>
                <button onclick="confirmReactivate(<?= $employee['id'] ?>, '<?= htmlspecialchars($employee['full_name']) ?>')" 
                        class="btn btn-reactivate btn-sm">
                    <i class="fas fa-undo"></i> Reactivate
                </button>
            <?php endif; ?>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" 
               class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Employee Details
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Deactivate Employee</h3>
            <button onclick="closeModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to deactivate <strong id="deleteName"></strong>?</p>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> 
                This employee will no longer be able to login. Their records will remain in the system for historical data.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn btn-outline btn-sm">Cancel</button>
            <a href="#" id="deleteLink" class="btn btn-danger btn-sm">
                <i class="fas fa-user-slash"></i> Deactivate
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- REACTIVATE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="reactivateModal" class="modal">
    <div class="modal-overlay" onclick="closeReactivateModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-undo text-green-500 mr-2"></i> Reactivate Employee</h3>
            <button onclick="closeReactivateModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to reactivate <strong id="reactivateName"></strong>?</p>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> 
                This employee will be able to login again.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeReactivateModal()" class="btn btn-outline btn-sm">Cancel</button>
            <a href="#" id="reactivateLink" class="btn btn-success btn-sm">
                <i class="fas fa-undo"></i> Reactivate
            </a>
        </div>
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // MODAL FUNCTIONS
    // ================================================================
    function confirmDelete(id, name) {
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteLink').href = 'employees.php?delete=' + id + '&branch=<?= $selected_branch_id ?>';
        document.getElementById('deleteModal').classList.add('show');
    }
    
    function closeModal() {
        document.getElementById('deleteModal').classList.remove('show');
    }

    function confirmReactivate(id, name) {
        document.getElementById('reactivateName').textContent = name;
        document.getElementById('reactivateLink').href = 'employees.php?reactivate=' + id + '&branch=<?= $selected_branch_id ?>';
        document.getElementById('reactivateModal').classList.add('show');
    }
    
    function closeReactivateModal() {
        document.getElementById('reactivateModal').classList.remove('show');
    }

    // ================================================================
    // CLOSE MODALS ON ESC KEY
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeReactivateModal();
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput?.value?.trim() || '';
        if (query.length > 0) {
            window.location.href = 'employees.php?search=' + encodeURIComponent(query) + '&branch=<?= $selected_branch_id ?>';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    console.log('%c👤 Braick Dispensary - Employee Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🎭 Role: <?= $role_display ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c📊 Activities: <?= number_format($employee['total_activities'] ?? 0) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c✅ Using database tables: users, branches, activity_logs, patients, visits, prescriptions, lab_tests, bills', 'font-size:13px; color:#34D399;');
    console.log('%c🔑 Role: <?= $_SESSION['role'] ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>