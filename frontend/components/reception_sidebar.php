<?php
// ================================================================
// FILE: frontend/components/reception_sidebar.php
// RECEPTION - SHARED SIDEBAR (BLUE BACKGROUND)
// WITH LOGIN SESSION PROTECTION
// WITH SERVICES SECTION - SHOWS ALL SERVICES FROM services TABLE
// WITH SIDEBAR TOGGLE - FULLY WORKING WITH HEADER
// ✅ JINA NA LOGO KUTOKA system_settings TABLE
// ✅ MENU MPANGILIO MPYA
// FULLY RESPONSIVE - ALL DEVICES
// BRAICK DISPENSARY
// ✅ FIXED: Assign Doctor | Lab Test shows BOTH counts separately
//    - Assign Doctor: count visits with doctor_id assigned (assigned status)
//    - Lab Test: count lab_tests with doctor_id IS NULL (direct lab requests)
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN SESSION PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS TO THIS SIDEBAR
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': 
            header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); 
            break;
        case 'pharmacy': 
            header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); 
            break;
        case 'laboratory': 
            header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); 
            break;
        case 'cashier': 
            header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); 
            break;
        default: 
            header('Location: /dispensary_system/frontend/pages/login.php'); 
            break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Reception';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $db = null;
}

// ================================================================
// ✅ GET SYSTEM SETTINGS - JINA NA LOGO LA DISPENSARY
// ================================================================
$site_name = 'Braick Dispensary';
$site_logo = '';
$site_logo_path = '';

if ($db !== null) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['setting_value'])) {
            $site_name = $result['setting_value'];
        }
        
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_logo'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['setting_value'])) {
            $site_logo = $result['setting_value'];
        }
        
    } catch (Exception $e) {
        error_log("Error fetching system settings: " . $e->getMessage());
    }
}

// ================================================================
// ✅ SITE LOGO PATH
// ================================================================
if (!empty($site_logo)) {
    $site_logo_path = '/dispensary_system/frontend/assets/uploads/settings/' . $site_logo;
} else {
    $site_logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
}

// ================================================================
// GET REAL DATA FOR BADGES
// ✅ FIXED: Separate counts for Assign Doctor and Lab Test
// ================================================================
$patient_count = 0;
$appointment_count = 0;
$pending_appointments = 0;
$today_visits = 0;
$services_count = 0;

// ✅ NEW: Separate counts
$assigned_doctor_count = 0;   // Visits with doctor_id assigned
$lab_test_count = 0;          // Lab tests with doctor_id IS NULL (direct lab requests)

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        // 1. Total patients
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([$user_branch_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Today's appointments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
        $stmt->execute([$user_branch_id]);
        $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 3. Pending appointments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
        $stmt->execute([$user_branch_id]);
        $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 4. Today's visits
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$user_branch_id]);
        $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // ============================================================
        // ✅ 5. ASSIGN DOCTOR COUNT
        // Count visits with doctor_id assigned (waiting to be seen by doctor)
        // ============================================================
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT v.id) as count 
            FROM visits v 
            WHERE v.branch_id = ? 
            AND v.doctor_id IS NOT NULL
            AND v.status IN ('assigned', 'pending')
        ");
        $stmt->execute([$user_branch_id]);
        $assigned_doctor_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // ============================================================
        // ✅ 6. LAB TEST COUNT
        // Count visits with direct lab requests (no doctor assigned)
        // ============================================================
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT lt.visit_id) as count 
            FROM lab_tests lt 
            INNER JOIN visits v ON lt.visit_id = v.id
            WHERE lt.branch_id = ? 
            AND lt.doctor_id IS NULL
            AND lt.status IN ('pending', 'in_progress')
            AND v.branch_id = ?
        ");
        $stmt->execute([$user_branch_id, $user_branch_id]);
        $lab_test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // ============================================================
        // 7. Services count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ? OR branch_id IS NULL");
        $stmt->execute([$user_branch_id]);
        $services_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Log for debugging
        error_log("Reception Sidebar - Patients: $patient_count, Assigned Doctor: $assigned_doctor_count, Lab Test: $lab_test_count");
        
    } catch (Exception $e) {
        error_log("Reception sidebar stats error: " . $e->getMessage());
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// Detect current page
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE
// ================================================================
function isActive($page, $exact = false) {
    global $current_page;
    global $current_uri;
    
    if ($exact) {
        return (strpos($current_uri, $page) !== false) ? 'active' : '';
    }
    
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// GENERATE INITIAL HASH
// ================================================================
$initial_hash = md5(json_encode([
    'patients' => $patient_count,
    'appointments' => $appointment_count,
    'pending_appointments' => $pending_appointments,
    'today_visits' => $today_visits,
    'assigned_doctor_count' => $assigned_doctor_count,
    'lab_test_count' => $lab_test_count,
    'services_count' => $services_count
]));

// ================================================================
// PASS DATA TO JAVASCRIPT
// ================================================================
$initial_data = [
    'patients' => $patient_count,
    'appointments' => $appointment_count,
    'pending_appointments' => $pending_appointments,
    'today_visits' => $today_visits,
    'assigned_doctor_count' => $assigned_doctor_count,
    'lab_test_count' => $lab_test_count,
    'services_count' => $services_count,
    'branch_id' => $user_branch_id,
    'branch_name' => $user_branch_name,
    'user_name' => $user_full_name
];
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - FULLY FIXED FOR MOBILE
       ================================================================ */
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
        color: white;
        z-index: 9999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(-100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        padding-bottom: 20px;
    }
    
    [data-theme="dark"] .sidebar {
        background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #9EC5FE; }
    
    /* ================================================================
       OVERLAY - For mobile
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
       SIDEBAR BRAND / HEADER - KUTOKA system_settings
       ================================================================ */
    .sidebar-brand {
        padding: 18px 16px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 5;
        backdrop-filter: blur(10px);
    }
    
    [data-theme="dark"] .sidebar-brand {
        background: rgba(0,0,0,0.1);
    }
    
    .sidebar-brand .logo {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        object-fit: cover;
        background: white;
        padding: 4px;
        border: 2px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-brand .brand-text {
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        line-height: 1.2;
    }
    
    .sidebar-brand .brand-sub {
        color: #9EC5FE;
        font-size: 0.65rem;
        font-weight: 500;
    }
    
    .sidebar-close-btn {
        display: none;
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 4px 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
        margin-left: auto;
    }
    
    .sidebar-close-btn:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
    }
    
    @media (max-width: 1024px) {
        .sidebar-close-btn {
            display: block;
        }
    }
    
    /* ================================================================
       NAVIGATION
       ================================================================ */
    .sidebar-nav {
        padding: 10px 8px 20px;
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6EA8FE;
        padding: 0 10px;
        margin: 12px 0 4px;
        font-weight: 700;
    }
    
    .sidebar-nav .nav-label:first-of-type {
        margin-top: 0;
    }
    
    /* ================================================================
       SIDEBAR LINKS
       ================================================================ */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        color: #D2E3FC;
        text-decoration: none;
        transition: all 0.25s ease;
        font-size: 0.8rem;
        font-weight: 500;
        margin: 1px 0;
        background: transparent;
        cursor: pointer;
        border: none;
        width: 100%;
        text-align: left;
        position: relative;
    }
    
    .sidebar-link:hover {
        background: rgba(255,255,255,0.08);
        color: white;
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: rgba(255,255,255,0.12);
        color: white;
        box-shadow: inset 3px 0 0 #6EA8FE;
    }
    
    .sidebar-link i {
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    
    /* ================================================================
       BADGES ON SIDEBAR
       ================================================================ */
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.12);
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
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
    
    .sidebar-link .badge.green {
        background: #059669;
        border-color: #059669;
    }
    
    .sidebar-link .badge.orange {
        background: #D97706;
        border-color: #D97706;
    }
    
    .sidebar-link .badge.purple {
        background: #7C3AED;
        border-color: #7C3AED;
    }
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
        border-color: #0B5ED7;
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
        50% { transform: scale(1.1); }
    }
    
    /* ================================================================
       DUAL BADGE - Assign Doctor | Lab Test
       ================================================================ */
    .dual-badge-container {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }
    
    .dual-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        padding: 2px 7px;
        border-radius: 12px;
        font-size: 0.55rem;
        font-weight: 700;
        color: white;
        min-width: 26px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.15);
        transition: all 0.3s ease;
    }
    
    .dual-badge.doctor-badge {
        background: #D97706;
        border-color: #D97706;
    }
    
    .dual-badge.doctor-badge.zero {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.1);
        color: #9EC5FE;
    }
    
    .dual-badge.lab-badge {
        background: #7C3AED;
        border-color: #7C3AED;
    }
    
    .dual-badge.lab-badge.zero {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.1);
        color: #9EC5FE;
    }
    
    .dual-badge-separator {
        color: rgba(255,255,255,0.3);
        font-size: 0.55rem;
        font-weight: 600;
    }
    
    .sidebar-link:hover .dual-badge {
        transform: scale(1.05);
    }
    
    /* ================================================================
       BADGE UPDATE ANIMATION
       ================================================================ */
    .badge-update {
        animation: badgePop 0.3s ease;
    }
    
    @keyframes badgePop {
        0% { transform: scale(0.5); opacity: 0; }
        70% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ================================================================
       LOGOUT LINK
       ================================================================ */
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.08);
        padding-top: 10px;
        margin-top: 6px;
        color: #FCA5A5;
    }
    
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    /* ================================================================
       SIDEBAR STATUS (Footer)
       ================================================================ */
    .sidebar-status {
        padding: 10px 16px;
        border-top: 2px solid rgba(255,255,255,0.08);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.05);
        position: sticky;
        bottom: 0;
        backdrop-filter: blur(10px);
    }
    
    [data-theme="dark"] .sidebar-status {
        background: rgba(0,0,0,0.1);
    }
    
    .sidebar-status .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .sidebar-status .status-dot.online {
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
    }
    
    .sidebar-status .status-text {
        font-size: 0.7rem;
        color: #D2E3FC;
    }
    
    .sidebar-status .status-time {
        font-size: 0.55rem;
        color: #6EA8FE;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .sidebar-status .status-time .live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34D399;
        display: inline-block;
        animation: pulse-dot 1.5s infinite;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }
    
    /* ================================================================
       RESPONSIVE BREAKPOINTS
       ================================================================ */
    
    @media (min-width: 1025px) {
        .sidebar {
            transform: translateX(0) !important;
            z-index: 50;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        #sidebarOverlay {
            display: none !important;
        }
        .sidebar-close-btn {
            display: none !important;
        }
    }
    
    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            z-index: 9999;
            border-radius: 0 12px 12px 0;
        }
        .sidebar.open {
            transform: translateX(0) !important;
        }
        #sidebarOverlay {
            display: none;
            z-index: 9998;
        }
        #sidebarOverlay.active {
            display: block !important;
        }
        .sidebar-brand {
            padding: 14px 14px 10px;
        }
        .sidebar-brand .logo {
            width: 36px;
            height: 36px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.85rem;
        }
        .sidebar-link {
            padding: 7px 10px;
            font-size: 0.75rem;
            gap: 8px;
        }
        .sidebar-link i {
            width: 18px;
            font-size: 0.8rem;
        }
        .sidebar-link .badge {
            font-size: 0.55rem;
            padding: 1px 7px;
        }
        .dual-badge {
            font-size: 0.5rem;
            padding: 1px 6px;
            min-width: 22px;
        }
        .sidebar-status {
            padding: 8px 14px;
        }
    }
    
    @media (max-width: 768px) {
        .sidebar {
            width: 300px;
            transform: translateX(-100%);
            border-radius: 0 16px 16px 0;
        }
        .sidebar.open {
            transform: translateX(0) !important;
        }
        .sidebar-brand {
            padding: 12px 12px 10px;
        }
        .sidebar-brand .logo {
            width: 34px;
            height: 34px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.8rem;
        }
        .sidebar-link {
            padding: 6px 10px;
            font-size: 0.7rem;
            gap: 8px;
        }
        .sidebar-link i {
            width: 16px;
            font-size: 0.75rem;
        }
        .sidebar-link .badge {
            font-size: 0.5rem;
            padding: 1px 6px;
        }
        .dual-badge {
            font-size: 0.45rem;
            padding: 1px 5px;
            min-width: 20px;
        }
        .sidebar-nav .nav-label {
            font-size: 0.45rem;
        }
        .sidebar-status {
            padding: 6px 12px;
        }
        .sidebar-status .status-text {
            font-size: 0.6rem;
        }
        .sidebar-status .status-time {
            font-size: 0.5rem;
        }
    }
    
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
            transform: translateX(-100%);
            border-radius: 0 20px 20px 0;
        }
        .sidebar.open {
            transform: translateX(0) !important;
        }
        .sidebar-brand {
            padding: 10px 10px 8px;
        }
        .sidebar-brand .logo {
            width: 30px;
            height: 30px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.75rem;
        }
        .sidebar-link {
            padding: 5px 8px;
            font-size: 0.65rem;
            gap: 6px;
        }
        .sidebar-link i {
            width: 14px;
            font-size: 0.7rem;
        }
        .sidebar-link .badge {
            font-size: 0.45rem;
            padding: 1px 5px;
            min-width: 16px;
        }
        .dual-badge {
            font-size: 0.4rem;
            padding: 1px 4px;
            min-width: 18px;
        }
        .dual-badge-separator {
            font-size: 0.5rem;
        }
        .sidebar-nav .nav-label {
            font-size: 0.4rem;
            padding: 0 8px;
        }
        .sidebar-status {
            padding: 4px 10px;
        }
        .sidebar-status .status-text {
            font-size: 0.55rem;
        }
        .sidebar-status .status-time {
            font-size: 0.45rem;
        }
        .sidebar-status .status-dot {
            width: 6px;
            height: 6px;
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
    }
    
    /* ================================================================
       UTILITY
       ================================================================ */
    .flex { display: flex; }
    .items-center { align-items: center; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .mt-2 { margin-top: 8px; }
    .mt-1 { margin-top: 4px; }
    .ml-auto { margin-left: auto; }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY (Mobile) -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR - RECEPTION PANEL -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- ================================================================ -->
    <!-- ✅ BRAND / HEADER - JINA NA LOGO KUTOKA system_settings -->
    <!-- ================================================================ -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $site_logo_path ?>" 
                 alt="<?= htmlspecialchars($site_name) ?>" 
                 class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text" id="sidebarSiteName"><?= htmlspecialchars($site_name) ?></p>
                <p class="brand-sub">Reception Panel</p>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- ✅ NAVIGATION - MPANGILIO MPYA (10 MENUS) -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav">
        
        <!-- ============================================================ -->
        <!-- RECEPTION MENU -->
        <!-- ============================================================ -->
        <div class="nav-label">Reception</div>
        
        <!-- 1. Dashboard -->
        <a href="/dispensary_system/frontend/pages/reception/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- 2. Register Patient -->
        <a href="/dispensary_system/frontend/pages/reception/new_patient.php" class="sidebar-link <?= isActive('new_patient.php') ?>">
            <i class="fas fa-user-plus"></i> Register Patient
        </a>
        
        <!-- 3. Patients -->
        <a href="/dispensary_system/frontend/pages/reception/patients.php" class="sidebar-link <?= isActive('patients.php') ?>">
            <i class="fas fa-users"></i> Patients
            <span class="badge" id="receptionPatientCount"><?= $patient_count ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- 4. Assign Doctor | Lab Test - DUAL BADGE -->
        <!-- ============================================================ -->
        <a href="/dispensary_system/frontend/pages/reception/assign_doctor.php" class="sidebar-link <?= isActive('assign_doctor.php') ?>">
            <i class="fas fa-user-md"></i>
            <span class="link-text">Assign Doctor</span>
            <div class="dual-badge-container">
                <!-- Doctor Badge (Assigned) -->
                <span class="dual-badge doctor-badge <?= $assigned_doctor_count == 0 ? 'zero' : '' ?>" 
                      id="sidebarAssignedDoctorBadge"
                      title="Visits waiting for doctor">
                    <?= $assigned_doctor_count ?>
                </span>
                
                <!-- Separator -->
                <span class="dual-badge-separator">|</span>
                
                <!-- Lab Test Badge -->
                <span class="dual-badge lab-badge <?= $lab_test_count == 0 ? 'zero' : '' ?>" 
                      id="sidebarLabTestBadge"
                      title="Direct lab requests (no doctor)">
                    <?= $lab_test_count ?>
                </span>
            </div>
        </a>
        
        <!-- ============================================================ -->
        <!-- VISITS & APPOINTMENTS -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Visits & Appointments</div>
        
        <!-- 5. Visit -->
        <a href="/dispensary_system/frontend/pages/reception/visits.php?filter=today" class="sidebar-link <?= isActive('visits.php') ?>">
            <i class="fas fa-clinic-medical"></i> Visit
            <span class="badge" id="receptionTodayVisits"><?= $today_visits ?></span>
        </a>
        
        <!-- 6. Appointments -->
        <a href="/dispensary_system/frontend/pages/reception/appointments.php" class="sidebar-link <?= isActive('appointments.php') ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <?php if ($pending_appointments > 0): ?>
                <span class="badge danger" id="receptionAppointmentCount"><?= $appointment_count ?></span>
            <?php else: ?>
                <span class="badge" id="receptionAppointmentCount"><?= $appointment_count ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- SERVICES & FINANCE -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Services & Finance</div>
        
        <!-- 7. Services -->
        <a href="/dispensary_system/frontend/pages/reception/services.php" class="sidebar-link <?= isActive('services.php') ?>">
            <i class="fas fa-cog"></i> Services
            <?php if ($services_count > 0): ?>
                <span class="badge purple" id="receptionServicesCount"><?= $services_count ?></span>
            <?php else: ?>
                <span class="badge" id="receptionServicesCount">0</span>
            <?php endif; ?>
        </a>
        
        <!-- 8. Cashier -->
        <a href="/dispensary_system/frontend/pages/cashier/dashboard.php" class="sidebar-link <?= (strpos($current_uri, '/cashier/dashboard.php') !== false) ? 'active' : '' ?>">
            <i class="fas fa-cash-register"></i> Cashier
        </a>
        
        <!-- ============================================================ -->
        <!-- ACCOUNT -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- 9. Profile -->
        <a href="/dispensary_system/frontend/pages/reception/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- 10. Logout -->
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- ================================================================ -->
    <!-- SIDEBAR STATUS (Footer) -->
    <!-- ================================================================ -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
        <span class="status-time" id="sidebarStatusTime">
            <span class="live-dot"></span>
            <span id="sidebarLiveTime"><?= date('H:i:s') ?></span>
        </span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FULL SIDEBAR FUNCTIONALITY -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var SIDEBAR_CONFIG = {
        AJAX_URL: '/dispensary_system/backend/api/reception_sidebar_ajax.php',
        CHECK_INTERVAL: 2000,
        FORCE_INTERVAL: 5000,
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
    // SIDEBAR TOGGLE - FULLY FIXED FOR ALL DEVICES
    // ================================================================
    (function() {
        function initSidebar() {
            console.log('🔧 Initializing Reception Sidebar...');
            
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
                console.log('✅ Sidebar overlay created');
            }
            
            if (!sidebar) {
                console.error('❌ Sidebar element not found!');
                return;
            }
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                console.log('🔓 Sidebar opened');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                console.log('🔒 Sidebar closed');
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            if (toggleBtn) {
                var newToggle = toggleBtn.cloneNode(true);
                toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
                var freshToggle = document.getElementById('sidebarToggle');
                
                if (freshToggle) {
                    freshToggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('🔘 Hamburger clicked!');
                        toggleSidebar();
                    });
                    console.log('✅ Toggle button attached');
                }
            } else {
                console.warn('⚠️ Toggle button not found');
            }
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
                console.log('✅ Close button attached');
            }
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
                console.log('✅ Overlay click handler attached');
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            console.log('✅ Reception Sidebar fully initialized!');
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            initSidebar();
        }
    })();

    // ================================================================
    // UPDATE SIDEBAR BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        if (!data) return false;
        
        var hasChanges = false;
        
        // 1. Patients
        var patientEl = document.getElementById('receptionPatientCount');
        if (patientEl && data.patients !== undefined) {
            var oldVal = patientEl.textContent;
            var newVal = data.patients;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                patientEl.textContent = newVal;
                patientEl.className = 'badge badge-update';
                patientEl.classList.remove('badge-update');
                void patientEl.offsetWidth;
                patientEl.classList.add('badge-update');
                console.log('🔄 Patients: ' + oldVal + ' → ' + newVal);
            }
        }
        
        // 2. Appointments
        var apptEl = document.getElementById('receptionAppointmentCount');
        if (apptEl && data.appointments !== undefined) {
            var oldVal = apptEl.textContent;
            var newVal = data.appointments;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                apptEl.textContent = newVal;
                var pending = data.pending_appointments || 0;
                apptEl.className = parseInt(pending) > 0 ? 'badge danger badge-update' : 'badge badge-update';
                apptEl.classList.remove('badge-update');
                void apptEl.offsetWidth;
                apptEl.classList.add('badge-update');
                console.log('🔄 Appointments: ' + oldVal + ' → ' + newVal);
            }
        }
        
        // 3. Today Visits
        var visitEl = document.getElementById('receptionTodayVisits');
        if (visitEl && data.today_visits !== undefined) {
            var oldVal = visitEl.textContent;
            var newVal = data.today_visits;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                visitEl.textContent = newVal;
                visitEl.className = parseInt(newVal) > 0 ? 'badge green badge-update' : 'badge badge-update';
                visitEl.classList.remove('badge-update');
                void visitEl.offsetWidth;
                visitEl.classList.add('badge-update');
                console.log('🔄 Today Visits: ' + oldVal + ' → ' + newVal);
            }
        }
        
        // ============================================================
        // 4. ASSIGNED DOCTOR BADGE (DUAL)
        // ============================================================
        var doctorBadge = document.getElementById('sidebarAssignedDoctorBadge');
        if (doctorBadge && data.assigned_doctor_count !== undefined) {
            var oldVal = doctorBadge.textContent;
            var newVal = data.assigned_doctor_count;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                doctorBadge.textContent = newVal;
                doctorBadge.className = parseInt(newVal) > 0 
                    ? 'dual-badge doctor-badge badge-update' 
                    : 'dual-badge doctor-badge zero badge-update';
                doctorBadge.classList.remove('badge-update');
                void doctorBadge.offsetWidth;
                doctorBadge.classList.add('badge-update');
                console.log('🔄 Assigned Doctor: ' + oldVal + ' → ' + newVal);
            }
        }
        
        // ============================================================
        // 5. LAB TEST BADGE (DUAL)
        // ============================================================
        var labBadge = document.getElementById('sidebarLabTestBadge');
        if (labBadge && data.lab_test_count !== undefined) {
            var oldVal = labBadge.textContent;
            var newVal = data.lab_test_count;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                labBadge.textContent = newVal;
                labBadge.className = parseInt(newVal) > 0 
                    ? 'dual-badge lab-badge badge-update' 
                    : 'dual-badge lab-badge zero badge-update';
                labBadge.classList.remove('badge-update');
                void labBadge.offsetWidth;
                labBadge.classList.add('badge-update');
                console.log('🔄 Lab Test: ' + oldVal + ' → ' + newVal);
            }
        }
        
        // 6. Services
        var servicesEl = document.getElementById('receptionServicesCount');
        if (servicesEl && data.services_count !== undefined) {
            var oldVal = servicesEl.textContent;
            var newVal = data.services_count;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                servicesEl.textContent = newVal;
                servicesEl.className = parseInt(newVal) > 0 ? 'badge purple badge-update' : 'badge badge-update';
                servicesEl.classList.remove('badge-update');
                void servicesEl.offsetWidth;
                servicesEl.classList.add('badge-update');
                console.log('🔄 Services: ' + oldVal + ' → ' + newVal);
            }
        }
        
        // 7. Update timestamp
        var timeEl = document.getElementById('sidebarLiveTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            if (timeEl.textContent !== timeStr) {
                timeEl.textContent = timeStr;
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
    // FETCH SIDEBAR DATA - DIRECT AJAX
    // ================================================================
    function fetchSidebarData(forceUpdate) {
        if (sidebarState.isUpdating && !forceUpdate) return;
        if (!SIDEBAR_CONFIG.BRANCH_ID) return;
        
        sidebarState.isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_reception_sidebar_data');
        formData.append('branch_id', SIDEBAR_CONFIG.BRANCH_ID);
        formData.append('hash', sidebarState.dataHash);
        if (forceUpdate) {
            formData.append('force_update', '1');
        }
        
        console.log('📡 Fetching sidebar data via AJAX... (force: ' + (forceUpdate ? 'YES' : 'NO') + ')');
        
        fetch(SIDEBAR_CONFIG.AJAX_URL, {
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
                console.log('📥 AJAX Response: has_changed=' + data.has_changed + ', hash=' + data.hash);
                
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
                    var timeEl = document.getElementById('sidebarLiveTime');
                    if (timeEl) {
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', {
                            hour: '2-digit', minute: '2-digit', second: '2-digit'
                        });
                        if (timeEl.textContent !== timeStr) {
                            timeEl.textContent = timeStr;
                        }
                    }
                    sidebarState.hasInitialData = true;
                }
                
                var statusDot = document.getElementById('sidebarStatusDot');
                if (statusDot) {
                    statusDot.className = 'status-dot online';
                }
                var statusText = document.getElementById('sidebarStatusText');
                if (statusText) {
                    statusText.textContent = 'Online';
                }
                
            } else {
                if (data.message && data.message.includes('Unauthorized')) {
                    window.location.href = '/dispensary_system/frontend/pages/login.php';
                }
                console.warn('⚠️ AJAX Error:', data.message);
            }
        })
        .catch(function(error) {
            sidebarState.isUpdating = false;
            console.warn('❌ Sidebar AJAX error:', error.message);
            
            var statusDot = document.getElementById('sidebarStatusDot');
            if (statusDot) {
                statusDot.className = 'status-dot offline';
            }
            var statusText = document.getElementById('sidebarStatusText');
            if (statusText) {
                statusText.textContent = 'Offline';
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
        
        setTimeout(function() {
            fetchSidebarData(true);
        }, 500);
        
        sidebarState.updateInterval = setInterval(function() {
            if (!sidebarState.isUpdating) {
                fetchSidebarData(false);
            }
        }, SIDEBAR_CONFIG.CHECK_INTERVAL);
        
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
    // VISIBILITY CHANGE
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
    console.log('%c🏥 Braick Dispensary - Reception Sidebar (AJAX)', 
        'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Jina na logo kutoka system_settings table', 
        'font-size:12px; color:#34D399;');
    console.log('%c📋 MENU: 1.Dashboard 2.Register Patient 3.Patients 4.Assign Doctor|Lab Test 5.Visit 6.Appointments 7.Services 8.Cashier 9.Profile 10.Logout', 
        'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 
        'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 
        'font-size:12px; color:#6EA8FE;');
    console.log('%c📊 Initial Data:', 'font-size:13px; font-weight:bold; color:#D97706;');
    console.log('   Patients: <?= $patient_count ?>, Appointments: <?= $appointment_count ?>');
    console.log('   Today Visits: <?= $today_visits ?>');
    console.log('   ✅ Assigned Doctor: <?= $assigned_doctor_count ?>');
    console.log('   ✅ Lab Test (Direct): <?= $lab_test_count ?>');
    console.log('   Services: <?= $services_count ?>');
    console.log('%c✅ FIXED: Assign Doctor | Lab Test shows BOTH counts separately', 
        'font-size:13px; font-weight:bold; color:#34D399;');
    console.log('%c⚡ Auto-Update: Every 2s (only if data changed)', 
        'font-size:13px; color:#34D399;');
    console.log('%c🔄 Force refresh: Every 5s (safety net)', 
        'font-size:13px; color:#F59E0B;');
    console.log('%c📡 AJAX URL: ' + SIDEBAR_CONFIG.AJAX_URL, 
        'font-size:12px; color:#94A3B8;');
    console.log('%c💡 Call window.refreshSidebarData() to manually update', 
        'font-size:12px; color:#6EA8FE;');
    console.log('%c📱 Click ☰ in header to open sidebar on mobile', 
        'font-size:12px; color:#34D399;');
</script>