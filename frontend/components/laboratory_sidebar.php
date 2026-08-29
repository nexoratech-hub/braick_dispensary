<?php
// ================================================================
// FILE: frontend/components/laboratory_sidebar.php
// LABORATORY - SHARED SIDEBAR (WITH AUTO-UPDATE API)
// WITH SIDEBAR TOGGLE BUTTON - SMOOTH SLIDE TRANSITION
// INCREASED FONT SIZE & SLOWER SLIDE ANIMATION
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
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Laboratory or Admin)
// ================================================================
$allowed_roles = ['laboratory', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Laboratory';
$user_role = $_SESSION['role'] ?? 'laboratory';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

// ================================================================
// INCLUDE DATABASE FOR INITIAL DATA
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $db = null;
}

// ================================================================
// GET SITE NAME
// ================================================================
$site_name = 'Braick Dispensary';
try {
    if ($db !== null) {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $site_name = $result['setting_value'];
        }
    }
} catch (Exception $e) {
    // Keep default
}

// ================================================================
// GET INITIAL DATA FOR BADGES
// ================================================================
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$today_tests = 0;
$total_tests = 0;

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        // Pending
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND (status IS NULL OR status = '' OR status = 'pending')
        ");
        $stmt->execute([$user_branch_id]);
        $pending_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // In Progress
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$user_branch_id]);
        $in_progress_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed'
        ");
        $stmt->execute([$user_branch_id]);
        $completed_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's tests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $today_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total tests
        $total_tests = $pending_count + $in_progress_count + $completed_count;
        
    } catch (Exception $e) {
        error_log("Sidebar initial data error: " . $e->getMessage());
    }
}

// ================================================================
// GENERATE INITIAL HASH
// ================================================================
$initial_hash = md5(json_encode([
    'pending' => $pending_count,
    'in_progress' => $in_progress_count,
    'completed' => $completed_count,
    'today_tests' => $today_tests
]));

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// DETECT CURRENT PAGE
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE
// ================================================================
function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PASS INITIAL DATA TO JAVASCRIPT
// ================================================================
$initial_data = [
    'pending' => $pending_count,
    'in_progress' => $in_progress_count,
    'completed' => $completed_count,
    'today_tests' => $today_tests,
    'total' => $total_tests,
    'branch_id' => $user_branch_id,
    'branch_name' => $user_branch_name,
    'user_name' => $user_full_name,
    'user_is_online' => $user_is_online
];
?>

<!-- ================================================================ -->
<!-- SIDEBAR CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       SIDEBAR STYLES
       ================================================================ */
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 260px;
        background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
        color: white;
        z-index: 9999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(0);
        box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        padding-bottom: 20px;
        scroll-behavior: smooth;
    }
    
    [data-theme="dark"] .sidebar {
        background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar.hidden {
        transform: translateX(-100%) !important;
    }
    
    .sidebar.visible {
        transform: translateX(0) !important;
    }
    
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #9EC5FE; }
    
    /* ================================================================
       SIDEBAR TOGGLE BUTTON - FLOATING
       SHOWS WHEN SIDEBAR IS HIDDEN, HIDES WHEN SIDEBAR IS OPEN
       ================================================================ */
    .sidebar-toggle-float {
        position: fixed;
        top: 14px;
        left: 14px;
        z-index: 10000;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--primary);
        color: white;
        border: none;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        display: none;
        opacity: 0;
        pointer-events: none;
    }
    
    /* Show button only when sidebar is hidden */
    .sidebar-toggle-float.show {
        display: flex !important;
        opacity: 1;
        pointer-events: auto;
    }
    
    .sidebar-toggle-float:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.5);
    }
    
    .sidebar-toggle-float .toggle-icon {
        transition: transform 0.3s ease;
        font-size: 0.95rem;
    }
    
    [data-theme="dark"] .sidebar-toggle-float {
        background: #1E3A5F;
        box-shadow: 0 4px 16px rgba(30, 58, 95, 0.4);
    }
    
    [data-theme="dark"] .sidebar-toggle-float:hover {
        box-shadow: 0 6px 24px rgba(30, 58, 95, 0.6);
    }
    
    /* ================================================================
       RESPONSIVE - SHOW BUTTON ON MOBILE WHEN SIDEBAR IS HIDDEN
       ================================================================ */
    @media (max-width: 1024px) {
        .sidebar-toggle-float {
            display: none !important;
        }
        
        .sidebar-toggle-float.show {
            display: flex !important;
        }
        
        .sidebar {
            transform: translateX(-100%);
            border-radius: 0 12px 12px 0;
        }
        
        .sidebar.visible {
            transform: translateX(0) !important;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%) !important;
        }
    }
    
    @media (min-width: 1025px) {
        .sidebar-toggle-float {
            display: none !important;
        }
        
        .sidebar {
            transform: translateX(0) !important;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%) !important;
        }
    }
    
    /* ================================================================
       OVERLAY
       ================================================================ */
    #sidebarOverlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 9998;
        display: none;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        transition: opacity 0.3s ease;
    }
    #sidebarOverlay.active {
        display: block !important;
    }
    
    /* ================================================================
       SIDEBAR BRAND
       ================================================================ */
    .sidebar-brand {
        padding: 16px 14px 12px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 5;
        backdrop-filter: blur(10px);
    }
    .sidebar-brand .logo {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        object-fit: cover;
        background: white;
        padding: 4px;
        border: 2px solid rgba(255,255,255,0.15);
        transition: transform 0.3s ease;
    }
    .sidebar-brand .logo:hover {
        transform: rotate(-5deg) scale(1.05);
    }
    .sidebar-brand .brand-text {
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        line-height: 1.2;
        letter-spacing: 0.5px;
    }
    .sidebar-brand .brand-sub {
        color: #9EC5FE;
        font-size: 0.65rem;
        font-weight: 500;
        letter-spacing: 0.3px;
    }
    
    .sidebar-close-btn {
        display: none;
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 3px 8px;
        border-radius: 6px;
        transition: all 0.3s ease;
        margin-left: auto;
    }
    .sidebar-close-btn:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
        color: white;
    }
    @media (max-width: 1024px) {
        .sidebar-close-btn {
            display: block;
        }
    }
    
    /* ================================================================
       NAVIGATION - INCREASED FONT SIZE
       ================================================================ */
    .sidebar-nav {
        padding: 6px 8px 12px;
    }
    .sidebar-nav .nav-label {
        font-size: 0.55rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6EA8FE;
        padding: 8px 10px 4px;
        margin: 8px 0 2px;
        font-weight: 700;
        opacity: 0.8;
    }
    .sidebar-nav .nav-label:first-of-type {
        margin-top: 0;
    }
    .sidebar-nav .nav-label .label-icon {
        margin-right: 4px;
    }
    
    /* ================================================================
       SIDEBAR LINKS - INCREASED FONT SIZE
       ================================================================ */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        color: #D2E3FC;
        text-decoration: none;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 0.85rem;
        font-weight: 500;
        margin: 2px 0;
        background: transparent;
        cursor: pointer;
        border: none;
        width: 100%;
        text-align: left;
        position: relative;
    }
    .sidebar-link:hover {
        background: rgba(10, 168, 79, 0.4);
        color: white;
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
    }
    .sidebar-link.active {
        background: rgba(10, 168, 79, 0.5);
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.3);
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 15%;
        bottom: 15%;
        width: 4px;
        background: #0AA84F;
        border-radius: 0 4px 4px 0;
        box-shadow: 0 0 12px rgba(10, 168, 79, 0.5);
    }
    .sidebar-link i {
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        opacity: 0.8;
    }
    .sidebar-link:hover i,
    .sidebar-link.active i {
        opacity: 1;
    }
    .sidebar-link .link-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.85rem;
    }
    
    /* ================================================================
       BADGES - INCREASED FONT SIZE
       ================================================================ */
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.12);
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
        border-color: #EF4444;
    }
    .sidebar-link .badge.warning {
        background: #D97706;
        border-color: #D97706;
    }
    .sidebar-link .badge.success {
        background: #059669;
        border-color: #059669;
    }
    .sidebar-link .badge.blue {
        background: #0B5ED7;
        border-color: #0B5ED7;
    }
    .sidebar-link .badge.purple {
        background: #7C3AED;
        border-color: #7C3AED;
    }
    .sidebar-link .badge.orange {
        background: #EA580C;
        border-color: #EA580C;
    }
    .sidebar-link .badge.teal {
        background: #0D9488;
        border-color: #0D9488;
    }
    .sidebar-link .badge.green {
        background: #059669;
        border-color: #059669;
    }
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
    }
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }
    
    /* ================================================================
       BADGE UPDATE ANIMATION
       ================================================================ */
    .badge-update {
        animation: badgePop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes badgePop {
        0% { transform: scale(0.3); opacity: 0; }
        60% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ================================================================
       DATA CHANGED FLASH
       ================================================================ */
    .sidebar-data-flash {
        animation: flashGreen 0.6s ease;
    }
    @keyframes flashGreen {
        0% { background: rgba(52, 211, 153, 0.2); }
        50% { background: rgba(52, 211, 153, 0.05); }
        100% { background: transparent; }
    }
    
    /* ================================================================
       LOGOUT LINK
       ================================================================ */
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.06);
        padding-top: 10px;
        margin-top: 4px;
        color: #FCA5A5;
    }
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        transform: translateX(4px);
    }
    .sidebar-link.logout-link i {
        opacity: 1;
    }
    
    /* ================================================================
       LIVE INDICATOR
       ================================================================ */
    .sidebar-live-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.5rem;
        color: #34D399;
        margin-left: auto;
        font-weight: 500;
    }
    .sidebar-live-indicator .dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
        display: inline-block;
    }
    
    /* ================================================================
       SIDEBAR STATUS FOOTER
       ================================================================ */
    .sidebar-status {
        padding: 8px 14px;
        border-top: 2px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(0,0,0,0.1);
        position: sticky;
        bottom: 0;
        backdrop-filter: blur(10px);
    }
    .sidebar-status .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
        transition: all 0.3s ease;
    }
    .sidebar-status .status-dot.online {
        background: #34D399;
        box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
        animation: pulse-dot 1.5s infinite;
    }
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
    }
    .sidebar-status .status-text {
        font-size: 0.65rem;
        color: #D2E3FC;
        font-weight: 500;
    }
    .sidebar-status .update-time {
        font-size: 0.5rem;
        color: #6EA8FE;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .sidebar {
            width: 260px;
            border-radius: 0 12px 12px 0;
        }
        .sidebar.visible {
            transform: translateX(0) !important;
        }
        .sidebar.hidden {
            transform: translateX(-100%) !important;
        }
        #sidebarOverlay {
            display: none;
            z-index: 9998;
        }
        #sidebarOverlay.active {
            display: block !important;
        }
        .sidebar-brand {
            padding: 12px 12px 10px;
        }
        .sidebar-brand .logo {
            width: 34px;
            height: 34px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.85rem;
        }
        .sidebar-link {
            padding: 6px 10px;
            font-size: 0.8rem;
            gap: 8px;
        }
        .sidebar-link i {
            width: 18px;
            font-size: 0.85rem;
        }
        .sidebar-link .badge {
            font-size: 0.6rem;
            padding: 1px 8px;
        }
    }
    
    @media (max-width: 768px) {
        .sidebar {
            width: 280px;
        }
        .sidebar-toggle-float {
            width: 36px;
            height: 36px;
            font-size: 0.85rem;
            top: 12px;
            left: 12px;
        }
        .sidebar-toggle-float .toggle-icon {
            font-size: 0.85rem;
        }
        .sidebar-brand {
            padding: 10px 12px 8px;
        }
        .sidebar-brand .logo {
            width: 32px;
            height: 32px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.8rem;
        }
        .sidebar-link {
            padding: 5px 8px;
            font-size: 0.75rem;
        }
        .sidebar-link i {
            width: 16px;
            font-size: 0.8rem;
        }
    }
    
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 300px;
        }
        .sidebar-toggle-float {
            width: 32px;
            height: 32px;
            font-size: 0.75rem;
            top: 10px;
            left: 10px;
        }
        .sidebar-toggle-float .toggle-icon {
            font-size: 0.75rem;
        }
        .sidebar-brand {
            padding: 8px 10px 8px;
        }
        .sidebar-brand .logo {
            width: 28px;
            height: 28px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.75rem;
        }
        .sidebar-link {
            padding: 4px 8px;
            font-size: 0.7rem;
            gap: 6px;
        }
        .sidebar-link i {
            width: 14px;
            font-size: 0.75rem;
        }
        .sidebar-link .badge {
            font-size: 0.55rem;
            padding: 1px 6px;
            min-width: 16px;
        }
        .sidebar-nav .nav-label {
            font-size: 0.45rem;
        }
        .sidebar-status .status-text {
            font-size: 0.55rem;
        }
    }
    
    /* ================================================================
       PRINT HIDE
       ================================================================ */
    @media print {
        .sidebar {
            display: none !important;
        }
        #sidebarOverlay {
            display: none !important;
        }
        .sidebar-toggle-float {
            display: none !important;
        }
    }
    
    /* ================================================================
       UTILITY
       ================================================================ */
    .flex { display: flex; }
    .items-center { align-items: center; }
    .gap-2 { gap: 6px; }
    .gap-3 { gap: 10px; }
    .mt-2 { margin-top: 6px; }
    .mt-1 { margin-top: 4px; }
    .ml-auto { margin-left: auto; }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<!-- ================================================================ -->
<!-- FLOATING TOGGLE BUTTON -->
<!-- ================================================================ -->
<button class="sidebar-toggle-float" id="sidebarToggleFloat" aria-label="Toggle Sidebar">
    <i class="fas fa-bars toggle-icon" id="toggleFloatIcon"></i>
</button>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar hidden" id="sidebar" role="navigation" aria-label="Laboratory Sidebar">
    
    <!-- ================================================================ -->
    <!-- BRAND -->
    <!-- ================================================================ -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text"><?= htmlspecialchars($site_name) ?></p>
                <p class="brand-sub">🧪 Laboratory Panel</p>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav" id="sidebarNav">
        
        <!-- ============================================================ -->
        <!-- LABORATORY MENU -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">📋</span> Laboratory</div>
        
        <!-- Dashboard -->
        <a href="/dispensary_system/frontend/pages/laboratory/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i>
            <span class="link-text">Dashboard</span>
        </a>
        
        <!-- ============================================================ -->
        <!-- LAB TESTS -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">🧪</span> Lab Tests</div>
        
        <!-- Pending -->
        <a href="/dispensary_system/frontend/pages/laboratory/pending_tests.php" class="sidebar-link <?= isActive('pending_tests.php') ?>" id="sidebarPendingLink">
            <i class="fas fa-clock"></i>
            <span class="link-text">Pending</span>
            <span class="badge <?= $pending_count > 0 ? 'danger' : '' ?>" id="sidebarPendingBadge"><?= $pending_count ?></span>
        </a>
        
        <!-- In Progress -->
        <a href="/dispensary_system/frontend/pages/laboratory/in_progress_tests.php" class="sidebar-link <?= isActive('in_progress_tests.php') ?>" id="sidebarInProgressLink">
            <i class="fas fa-spinner"></i>
            <span class="link-text">In Progress</span>
            <span class="badge <?= $in_progress_count > 0 ? 'orange' : '' ?>" id="sidebarInProgressBadge"><?= $in_progress_count ?></span>
        </a>
        
        <!-- Completed -->
        <a href="/dispensary_system/frontend/pages/laboratory/completed_tests.php" class="sidebar-link <?= isActive('completed_tests.php') ?>" id="sidebarCompletedLink">
            <i class="fas fa-check-circle"></i>
            <span class="link-text">Completed</span>
            <span class="badge <?= $completed_count > 0 ? 'green' : '' ?>" id="sidebarCompletedBadge"><?= $completed_count ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- RESULTS -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">📊</span> Results</div>
        
        <!-- Results History -->
        <a href="/dispensary_system/frontend/pages/laboratory/results_history.php" class="sidebar-link <?= isActive('results_history.php') ?>" id="sidebarResultsLink">
            <i class="fas fa-history"></i>
            <span class="link-text">Results History</span>
            <span class="badge <?= $today_tests > 0 ? 'green' : '' ?>" id="sidebarTodayTests"><?= $today_tests ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- ACCOUNT -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        
        <!-- Profile -->
        <a href="/dispensary_system/frontend/pages/laboratory/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <!-- Logout -->
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
    <!-- ================================================================ -->
    <!-- STATUS FOOTER -->
    <!-- ================================================================ -->
    <div class="sidebar-status" id="sidebarStatusFooter">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>" id="sidebarFooterDot"></span>
        <span class="status-text" id="sidebarFooterText"><?= $user_is_online ? 'Online' : 'Offline' ?></span>
        <span class="update-time" id="sidebarUpdateTime">
            <span class="sidebar-live-indicator">
                <span class="dot"></span> Live
            </span>
        </span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT - SIDEBAR TOGGLE WITH SMOOTH SLIDE -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE - SMOOTH SLIDE TRANSITION (SLOWER)
    // ================================================================
    (function() {
        var sidebar = document.getElementById('sidebar');
        var toggleBtn = document.getElementById('sidebarToggleFloat');
        var toggleIcon = document.getElementById('toggleFloatIcon');
        var closeBtn = document.getElementById('sidebarCloseBtn');
        var overlay = document.getElementById('sidebarOverlay');
        
        if (!sidebar) {
            console.warn('⚠️ Sidebar not found');
            return;
        }
        
        if (!toggleBtn) {
            console.warn('⚠️ Toggle button not found');
            return;
        }
        
        console.log('✅ Sidebar toggle initialized');
        
        // Create overlay if not exists
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
            document.body.appendChild(overlay);
        }
        
        function updateToggleButton(show) {
            if (show) {
                toggleBtn.classList.add('show');
                toggleBtn.style.display = 'flex';
            } else {
                toggleBtn.classList.remove('show');
                toggleBtn.style.display = 'none';
            }
        }
        
        function openSidebar() {
            // Remove hidden class, add visible class for smooth slide in
            sidebar.classList.remove('hidden');
            sidebar.classList.add('visible');
            
            if (overlay) {
                overlay.style.display = 'block';
                overlay.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
            
            // Update icon
            if (toggleIcon) {
                toggleIcon.className = 'fas fa-times toggle-icon';
            }
            
            // HIDE toggle button when sidebar is open
            updateToggleButton(false);
            
            // Update button style
            if (toggleBtn) {
                toggleBtn.style.background = '#DC2626';
                toggleBtn.style.boxShadow = '0 4px 16px rgba(220, 38, 38, 0.4)';
            }
            console.log('🔓 Sidebar opened - toggle button hidden');
        }
        
        function closeSidebar() {
            // Add hidden class, remove visible class for smooth slide out
            sidebar.classList.add('hidden');
            sidebar.classList.remove('visible');
            
            if (overlay) {
                overlay.style.display = 'none';
                overlay.classList.remove('active');
            }
            document.body.style.overflow = '';
            
            // Update icon
            if (toggleIcon) {
                toggleIcon.className = 'fas fa-bars toggle-icon';
            }
            
            // SHOW toggle button when sidebar is closed
            updateToggleButton(true);
            
            // Update button style
            if (toggleBtn) {
                toggleBtn.style.background = '';
                toggleBtn.style.boxShadow = '';
            }
            console.log('🔒 Sidebar closed - toggle button shown');
        }
        
        function toggleSidebar() {
            if (sidebar.classList.contains('hidden')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        }
        
        // ================================================================
        // TOGGLE BUTTON CLICK
        // ================================================================
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🔘 Toggle button clicked!');
            toggleSidebar();
        });
        
        // ================================================================
        // CLOSE BUTTON (X)
        // ================================================================
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });
        }
        
        // ================================================================
        // OVERLAY CLICK
        // ================================================================
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeSidebar();
                }
            });
        }
        
        // ================================================================
        // ESC KEY
        // ================================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !sidebar.classList.contains('hidden')) {
                closeSidebar();
            }
        });
        
        // ================================================================
        // WINDOW RESIZE
        // ================================================================
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                // On desktop, always show sidebar
                sidebar.classList.remove('hidden');
                sidebar.classList.add('visible');
                if (overlay) {
                    overlay.style.display = 'none';
                    overlay.classList.remove('active');
                }
                if (toggleIcon) {
                    toggleIcon.className = 'fas fa-times toggle-icon';
                }
                // Hide toggle button on desktop
                updateToggleButton(false);
            } else {
                // On mobile, if sidebar is not visible and not hidden, hide it
                if (!sidebar.classList.contains('hidden') && !sidebar.classList.contains('visible')) {
                    sidebar.classList.add('hidden');
                }
                if (sidebar.classList.contains('visible')) {
                    // Keep it as is
                }
            }
        });
        
        // ================================================================
        // CHECK INITIAL STATE
        // ================================================================
        if (window.innerWidth > 1024) {
            // Desktop: show sidebar, hide button
            sidebar.classList.remove('hidden');
            sidebar.classList.add('visible');
            if (toggleIcon) {
                toggleIcon.className = 'fas fa-times toggle-icon';
            }
            updateToggleButton(false);
        } else {
            // Mobile: hide sidebar, show button
            sidebar.classList.add('hidden');
            sidebar.classList.remove('visible');
            if (toggleIcon) {
                toggleIcon.className = 'fas fa-bars toggle-icon';
            }
            updateToggleButton(true);
        }
        
        console.log('✅ Sidebar toggle ready!');
        console.log('📱 Current state:', sidebar.classList.contains('hidden') ? 'HIDDEN' : 'VISIBLE');
        console.log('📱 Toggle button:', toggleBtn.classList.contains('show') ? 'VISIBLE' : 'HIDDEN');
        console.log('✅ Smooth slide transition: 0.6s cubic-bezier');
        console.log('✅ Font size increased for better readability');
    })();

    // ================================================================
    // CONFIGURATION
    // ================================================================
    var SIDEBAR_CONFIG = {
        API_URL: '/dispensary_system/backend/api/get_lab_sidebar_stats.php',
        CHECK_INTERVAL: 2000,
        FORCE_INTERVAL: 10000,
        BRANCH_ID: <?= json_encode($user_branch_id) ?>,
        INITIAL_HASH: '<?= $initial_hash ?>'
    };
    
    // ================================================================
    // STATE
    // ================================================================
    var sidebarState = {
        dataHash: SIDEBAR_CONFIG.INITIAL_HASH,
        isUpdating: false,
        hasInitialData: false,
        updateInterval: null,
        forceInterval: null,
        lastUpdate: null,
        changeCount: 0
    };
    
    // ================================================================
    // UPDATE SIDEBAR BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        if (!data) return false;
        
        var hasChanges = false;
        
        // Pending Badge
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge && data.pending !== undefined) {
            var oldVal = pendingBadge.textContent;
            var newVal = data.pending;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                pendingBadge.textContent = newVal;
                pendingBadge.className = parseInt(newVal) > 0 ? 'badge danger' : 'badge';
                pendingBadge.classList.remove('badge-update');
                void pendingBadge.offsetWidth;
                pendingBadge.classList.add('badge-update');
            }
        }
        
        // In Progress Badge
        var inProgressBadge = document.getElementById('sidebarInProgressBadge');
        if (inProgressBadge && data.in_progress !== undefined) {
            var oldVal = inProgressBadge.textContent;
            var newVal = data.in_progress;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                inProgressBadge.textContent = newVal;
                inProgressBadge.className = parseInt(newVal) > 0 ? 'badge orange' : 'badge';
                inProgressBadge.classList.remove('badge-update');
                void inProgressBadge.offsetWidth;
                inProgressBadge.classList.add('badge-update');
            }
        }
        
        // Completed Badge
        var completedBadge = document.getElementById('sidebarCompletedBadge');
        if (completedBadge && data.completed !== undefined) {
            var oldVal = completedBadge.textContent;
            var newVal = data.completed;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                completedBadge.textContent = newVal;
                completedBadge.className = parseInt(newVal) > 0 ? 'badge green' : 'badge';
                completedBadge.classList.remove('badge-update');
                void completedBadge.offsetWidth;
                completedBadge.classList.add('badge-update');
            }
        }
        
        // Today Tests Badge
        var todayBadge = document.getElementById('sidebarTodayTests');
        if (todayBadge && data.today_tests !== undefined) {
            var oldVal = todayBadge.textContent;
            var newVal = data.today_tests;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                todayBadge.textContent = newVal;
                todayBadge.className = parseInt(newVal) > 0 ? 'badge green' : 'badge';
                todayBadge.classList.remove('badge-update');
                void todayBadge.offsetWidth;
                todayBadge.classList.add('badge-update');
            }
        }
        
        // Update timestamp
        var timeEl = document.getElementById('sidebarUpdateTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            var newHtml = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr;
            if (timeEl.innerHTML !== newHtml) {
                timeEl.innerHTML = newHtml;
            }
        }
        
        // Flash sidebar if data changed
        if (hasChanges) {
            var sidebarEl = document.getElementById('sidebar');
            if (sidebarEl) {
                sidebarEl.classList.remove('sidebar-data-flash');
                void sidebarEl.offsetWidth;
                sidebarEl.classList.add('sidebar-data-flash');
            }
            sidebarState.changeCount++;
            console.log('📊 Sidebar updated: ' + sidebarState.changeCount + ' changes detected');
        }
        
        return hasChanges;
    }

    // ================================================================
    // FETCH SIDEBAR DATA FROM API
    // ================================================================
    function fetchSidebarData(forceUpdate) {
        if (sidebarState.isUpdating && !forceUpdate) return;
        if (!SIDEBAR_CONFIG.BRANCH_ID) return;
        
        sidebarState.isUpdating = true;
        
        var formData = new FormData();
        formData.append('branch_id', SIDEBAR_CONFIG.BRANCH_ID);
        formData.append('hash', sidebarState.dataHash);
        if (forceUpdate) {
            formData.append('force_update', '1');
        }
        
        fetch(SIDEBAR_CONFIG.API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            sidebarState.isUpdating = false;
            
            if (data.success) {
                if (data.has_changed && data.data) {
                    updateSidebarBadges(data.data);
                    sidebarState.dataHash = data.hash;
                    sidebarState.hasInitialData = true;
                    sidebarState.lastUpdate = new Date();
                    
                    var event = new CustomEvent('sidebarDataUpdated', {
                        detail: {
                            data: data.data,
                            summary: data.summary,
                            timestamp: data.timestamp
                        }
                    });
                    document.dispatchEvent(event);
                    
                    console.log('✅ Sidebar data updated at:', sidebarState.lastUpdate.toLocaleTimeString());
                    
                } else if (data.has_changed === false) {
                    var timeEl = document.getElementById('sidebarUpdateTime');
                    if (timeEl) {
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', {
                            hour: '2-digit', minute: '2-digit', second: '2-digit'
                        });
                        var newHtml = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr;
                        if (timeEl.innerHTML !== newHtml) {
                            timeEl.innerHTML = newHtml;
                        }
                    }
                    sidebarState.hasInitialData = true;
                }
            } else {
                if (data.message && data.message.includes('Unauthorized')) {
                    window.location.href = '/dispensary_system/frontend/pages/login.php';
                }
            }
        })
        .catch(function(error) {
            sidebarState.isUpdating = false;
            if (forceUpdate) {
                console.warn('Sidebar API error:', error.message);
            }
        });
    }

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    function startSidebarAutoUpdate() {
        if (sidebarState.updateInterval) {
            clearInterval(sidebarState.updateInterval);
        }
        if (sidebarState.forceInterval) {
            clearInterval(sidebarState.forceInterval);
        }
        
        // Initial fetch after 500ms
        setTimeout(function() {
            fetchSidebarData(true);
        }, 500);
        
        // Regular check for changes (every 2 seconds)
        sidebarState.updateInterval = setInterval(function() {
            if (!sidebarState.isUpdating) {
                fetchSidebarData(false);
            }
        }, SIDEBAR_CONFIG.CHECK_INTERVAL);
        
        // Force refresh (every 10 seconds as safety net)
        sidebarState.forceInterval = setInterval(function() {
            if (!sidebarState.isUpdating && sidebarState.hasInitialData) {
                fetchSidebarData(true);
            }
        }, SIDEBAR_CONFIG.FORCE_INTERVAL);
        
        console.log('🔄 Sidebar auto-update started (check: ' + 
            SIDEBAR_CONFIG.CHECK_INTERVAL/1000 + 's, force: ' + 
            SIDEBAR_CONFIG.FORCE_INTERVAL/1000 + 's)');
    }

    function stopSidebarAutoUpdate() {
        if (sidebarState.updateInterval) {
            clearInterval(sidebarState.updateInterval);
            sidebarState.updateInterval = null;
        }
        if (sidebarState.forceInterval) {
            clearInterval(sidebarState.forceInterval);
            sidebarState.forceInterval = null;
        }
        console.log('🔄 Sidebar auto-update stopped');
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function refreshSidebarData() {
        fetchSidebarData(true);
        return true;
    }

    // ================================================================
    // EXPOSE FUNCTIONS
    // ================================================================
    window.refreshSidebarData = refreshSidebarData;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;
    window.getSidebarState = function() { return sidebarState; };
    window.getSidebarHash = function() { return sidebarState.dataHash; };

    // ================================================================
    // VISIBILITY CHANGE - Pause when tab is hidden
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
            setTimeout(function() {
                fetchSidebarData(true);
            }, 500);
        }
    });

    // ================================================================
    // DOM READY
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 1500);
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c🧪 Braick Dispensary - Laboratory Sidebar (Auto-Update)', 
        'font-size:16px; font-weight:bold; color:#0AA84F;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 
        'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 
        'font-size:13px; color:#6EA8FE;');
    console.log('%c📊 Initial Data:', 'font-size:13px; font-weight:bold; color:#D97706;');
    console.log('   Pending: <?= $pending_count ?>, In Progress: <?= $in_progress_count ?>');
    console.log('   Completed: <?= $completed_count ?>, Today: <?= $today_tests ?>');
    console.log('%c⚡ Auto-Update: Every 2s (only if data changed)', 
        'font-size:13px; color:#34D399;');
    console.log('%c🔄 Force refresh: Every 10s (safety net)', 
        'font-size:13px; color:#F59E0B;');
    console.log('%c📡 API: ' + SIDEBAR_CONFIG.API_URL, 
        'font-size:12px; color:#94A3B8;');
    console.log('%c📱 Toggle button: Shows when sidebar hidden, hides when open', 
        'font-size:12px; color:#34D399;');
    console.log('%c✅ SLOW slide transition: 0.6s cubic-bezier', 
        'font-size:13px; font-weight:bold; color:#34D399;');
    console.log('%c✅ Font size increased: links 0.85rem, badges 0.65rem', 
        'font-size:13px; color:#34D399;');
    console.log('%c✅ Profile removed from sidebar', 
        'font-size:13px; color:#34D399;');
</script>