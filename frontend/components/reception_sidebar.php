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
// FIXED: Assign Doctor badge shows correct count
// FIXED: Debug logging added
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
        // Get site name
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['setting_value'])) {
            $site_name = $result['setting_value'];
        }
        
        // Get site logo
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
// GET REAL DATA FOR BADGES - FIXED SQL QUERIES
// ================================================================
$patient_count = 0;
$appointment_count = 0;
$pending_appointments = 0;
$today_visits = 0;
$pending_patients = 0;
$services_count = 0;
$assigned_count = 0;
$lab_only_count = 0;
$total_active_patients = 0;

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        error_log("=== RECEPTION SIDEBAR - FETCHING STATS ===");
        error_log("Branch ID: " . $user_branch_id);
        
        // 1. Total Patients
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([$user_branch_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Total Patients: " . $patient_count);
        
        // 2. Today's Appointments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
        $stmt->execute([$user_branch_id]);
        $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Today's Appointments: " . $appointment_count);
        
        // 3. Pending Appointments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
        $stmt->execute([$user_branch_id]);
        $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Pending Appointments: " . $pending_appointments);
        
        // 4. Today's Visits
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND deleted_at IS NULL");
        $stmt->execute([$user_branch_id]);
        $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Today's Visits: " . $today_visits);
        
        // 5. FIXED: Assigned Patients (with doctor) - ONLY ACTIVE VISITS
        //     Checks ALL statuses that indicate assigned: 'assigned', 'with_doctor'
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE branch_id = ? 
            AND status IN ('assigned', 'with_doctor') 
            AND deleted_at IS NULL
        ");
        $stmt->execute([$user_branch_id]);
        $assigned_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Assigned Patients (status=assigned/with_doctor): " . $assigned_count);
        
        // 6. FIXED: Lab Only Patients - with pending lab tests
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT v.patient_id) as count 
            FROM visits v
            WHERE v.branch_id = ? 
            AND v.status = 'lab_test' 
            AND v.deleted_at IS NULL
            AND (
                SELECT COUNT(*) 
                FROM lab_tests lt 
                WHERE lt.visit_id = v.id 
                AND lt.status NOT IN ('completed', 'cancelled')
            ) > 0
        ");
        $stmt->execute([$user_branch_id]);
        $lab_only_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Lab Only Patients (pending tests): " . $lab_only_count);
        
        // 7. Total Active = Assigned + Lab Only
        $total_active_patients = $assigned_count + $lab_only_count;
        error_log("TOTAL ACTIVE PATIENTS: " . $total_active_patients);
        
        // 8. Pending Patients (new/pending - need doctor)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE branch_id = ? 
            AND status IN ('pending', 'new') 
            AND deleted_at IS NULL
        ");
        $stmt->execute([$user_branch_id]);
        $pending_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Pending Patients (status=pending/new): " . $pending_patients);
        
        // 9. Services Count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ? OR branch_id IS NULL");
        $stmt->execute([$user_branch_id]);
        $services_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        error_log("Services Count: " . $services_count);
        
        error_log("=== SIDEBAR STATS COMPLETE ===");
        
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
// HANDLE AJAX REQUEST FOR SIDEBAR DATA - FIXED
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_reception_sidebar_data') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $branch_id = (int)($_POST['branch_id'] ?? $_SESSION['branch_id'] ?? 1);
    
    $response = [
        'success' => false,
        'patients' => 0,
        'appointments' => 0,
        'pending_appointments' => 0,
        'today_visits' => 0,
        'pending_patients' => 0,
        'services_count' => 0,
        'assigned_count' => 0,
        'lab_only_count' => 0,
        'total_active_patients' => 0,
        'debug' => [],
        'hash' => ''
    ];
    
    if ($db !== null) {
        try {
            error_log("=== AJAX: FETCHING SIDEBAR DATA ===");
            error_log("Branch ID: " . $branch_id);
            
            // Total Patients
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
            $stmt->execute([$branch_id]);
            $response['patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['patients'] = $response['patients'];
            
            // Today's Appointments
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
            $stmt->execute([$branch_id]);
            $response['appointments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['appointments'] = $response['appointments'];
            
            // Pending Appointments
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
            $stmt->execute([$branch_id]);
            $response['pending_appointments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['pending_appointments'] = $response['pending_appointments'];
            
            // Today's Visits
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND deleted_at IS NULL");
            $stmt->execute([$branch_id]);
            $response['today_visits'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['today_visits'] = $response['today_visits'];
            
            // FIXED: Assigned Patients (with doctor) - ALL STATUSES
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE branch_id = ? 
                AND status IN ('assigned', 'with_doctor') 
                AND deleted_at IS NULL
            ");
            $stmt->execute([$branch_id]);
            $response['assigned_count'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['assigned_count'] = $response['assigned_count'];
            
            // FIXED: Lab Only Patients - with pending lab tests
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT v.patient_id) as count 
                FROM visits v
                WHERE v.branch_id = ? 
                AND v.status = 'lab_test' 
                AND v.deleted_at IS NULL
                AND (
                    SELECT COUNT(*) 
                    FROM lab_tests lt 
                    WHERE lt.visit_id = v.id 
                    AND lt.status NOT IN ('completed', 'cancelled')
                ) > 0
            ");
            $stmt->execute([$branch_id]);
            $response['lab_only_count'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['lab_only_count'] = $response['lab_only_count'];
            
            // Total Active = Assigned + Lab Only
            $response['total_active_patients'] = $response['assigned_count'] + $response['lab_only_count'];
            $response['debug']['total_active_patients'] = $response['total_active_patients'];
            
            // Pending Patients (new/pending)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE branch_id = ? 
                AND status IN ('pending', 'new') 
                AND deleted_at IS NULL
            ");
            $stmt->execute([$branch_id]);
            $response['pending_patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['pending_patients'] = $response['pending_patients'];
            
            // Services Count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ? OR branch_id IS NULL");
            $stmt->execute([$branch_id]);
            $response['services_count'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            $response['debug']['services_count'] = $response['services_count'];
            
            $response['success'] = true;
            $response['hash'] = md5(
                $response['patients'] . 
                $response['appointments'] . 
                $response['pending_appointments'] . 
                $response['today_visits'] . 
                $response['pending_patients'] .
                $response['services_count'] .
                $response['assigned_count'] .
                $response['lab_only_count'] .
                $response['total_active_patients']
            );
            
            error_log("=== AJAX RESPONSE ===");
            error_log("Assigned: " . $response['assigned_count']);
            error_log("Lab Only: " . $response['lab_only_count']);
            error_log("Total Active: " . $response['total_active_patients']);
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
            error_log("AJAX Error: " . $e->getMessage());
        }
    }
    
    echo json_encode($response);
    exit;
}
?>
<style>
    /* ================================================================
       SIDEBAR STYLES - FULLY FIXED FOR MOBILE
       ================================================================ */
    
    /* Sidebar Container */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        background: #0B4EA8;
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
        background: #0A3D7A;
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0A3D7A; }
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
        background: #0B4EA8;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    [data-theme="dark"] .sidebar-brand {
        background: #0A3D7A;
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
    
    /* ================================================================
       SIDEBAR CLOSE BUTTON (Mobile)
       ================================================================ */
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
        color: #9EC5FE;
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
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    }
    
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 20%;
        bottom: 20%;
        width: 4px;
        background: white;
        border-radius: 0 4px 4px 0;
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
        background: rgba(255,255,255,0.15);
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
    }
    
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-link .badge.green {
        background: #059669;
    }
    
    .sidebar-link .badge.orange {
        background: #D97706;
    }
    
    .sidebar-link .badge.purple {
        background: #7C3AED;
    }
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
    }
    
    .sidebar-link .badge.info {
        background: #0EA5E9;
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
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
       SIDEBAR STATUS (Footer)
       ================================================================ */
    .sidebar-status {
        padding: 10px 16px;
        border-top: 2px solid rgba(255,255,255,0.08);
        display: flex;
        align-items: center;
        gap: 10px;
        background: #0B4EA8;
        position: sticky;
        bottom: 0;
    }
    
    [data-theme="dark"] .sidebar-status {
        background: #0A3D7A;
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
        color: #9EC5FE;
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
            <!-- Close button for mobile -->
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
        
        <!-- 4. Assign Doctor - TOTAL = ASSIGNED + LAB ONLY -->
        <a href="/dispensary_system/frontend/pages/reception/assign_doctor.php" class="sidebar-link <?= isActive('assign_doctor.php') ?>">
            <i class="fas fa-user-md"></i> Assign Doctor
            <?php if ($total_active_patients > 0): ?>
                <span class="badge danger" id="receptionTotalActivePatients"><?= $total_active_patients ?></span>
            <?php else: ?>
                <span class="badge" id="receptionTotalActivePatients">0</span>
            <?php endif; ?>
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
            
            // Toggle button from header
            if (toggleBtn) {
                var newToggle = toggleBtn.cloneNode(true);
                toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
                var freshToggle = document.getElementById('sidebarToggle');
                
                freshToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🔘 Hamburger clicked!');
                    toggleSidebar();
                });
                console.log('✅ Toggle button attached');
            } else {
                console.warn('⚠️ Toggle button not found');
            }
            
            // Close button
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
                console.log('✅ Close button attached');
            }
            
            // Overlay click
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
                console.log('✅ Overlay click handler attached');
            }
            
            // ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // Resize
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
        var el = document.getElementById('receptionPatientCount');
        if (el && data.patients !== undefined) {
            el.textContent = data.patients;
            el.classList.remove('badge-update');
            void el.offsetWidth;
            el.classList.add('badge-update');
        }
        
        el = document.getElementById('receptionAppointmentCount');
        if (el && data.appointments !== undefined) {
            el.textContent = data.appointments;
            el.className = data.pending_appointments > 0 ? 'badge danger' : 'badge';
            el.classList.remove('badge-update');
            void el.offsetWidth;
            el.classList.add('badge-update');
        }
        
        el = document.getElementById('receptionTodayVisits');
        if (el && data.today_visits !== undefined) {
            el.textContent = data.today_visits;
            el.className = data.today_visits > 0 ? 'badge green' : 'badge';
            el.classList.remove('badge-update');
            void el.offsetWidth;
            el.classList.add('badge-update');
        }
        
        // Assign Doctor badge - shows TOTAL ACTIVE (Assigned + Lab Only)
        el = document.getElementById('receptionTotalActivePatients');
        if (el && data.total_active_patients !== undefined) {
            el.textContent = data.total_active_patients;
            el.className = data.total_active_patients > 0 ? 'badge danger' : 'badge';
            el.classList.remove('badge-update');
            void el.offsetWidth;
            el.classList.add('badge-update');
            
            // Log change for debugging
            console.log('🔄 Assign Doctor badge updated: ' + data.total_active_patients + 
                       ' (Assigned: ' + data.assigned_count + ' + Lab Only: ' + data.lab_only_count + ')');
        }
        
        el = document.getElementById('receptionServicesCount');
        if (el && data.services_count !== undefined) {
            el.textContent = data.services_count;
            el.className = data.services_count > 0 ? 'badge purple' : 'badge';
            el.classList.remove('badge-update');
            void el.offsetWidth;
            el.classList.add('badge-update');
        }
        
        var timeEl = document.getElementById('sidebarLiveTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
            });
            timeEl.textContent = timeStr;
        }
    }

    // ================================================================
    // FETCH SIDEBAR DATA - AUTO UPDATE EVERY 3 SECONDS
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;
    var branchId = <?= json_encode($_SESSION['branch_id'] ?? 1) ?>;
    var lastDataHash = null;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_reception_sidebar_data');
        formData.append('branch_id', branchId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                if (lastDataHash !== data.hash) {
                    lastDataHash = data.hash;
                    console.log('🔄 Sidebar data changed! Updating badges...');
                    console.log('📊 Debug data:', data.debug);
                    updateSidebarBadges(data);
                }
            } else {
                console.warn('⚠️ Sidebar data fetch failed:', data.error);
            }
            sidebarIsUpdating = false;
        })
        .catch(function(error) {
            console.error('❌ Sidebar fetch error:', error);
            sidebarIsUpdating = false;
        });
    }

    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // First fetch after 1 second
        setTimeout(function() {
            fetchSidebarData();
        }, 1000);
        // Then every 3 seconds
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
        console.log('%c🔄 Reception Sidebar auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    window.updateSidebarBadges = updateSidebarBadges;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;

    console.log('%c🏥 Reception Sidebar (UPDATED - FIXED)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Jina na logo kutoka system_settings table', 'font-size:12px; color:#34D399;');
    console.log('%c📋 MENU: 1.Dashboard 2.Register Patient 3.Patients 4.Assign Doctor 5.Visit 6.Appointments 7.Services 8.Cashier 9.Profile 10.Logout', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:12px; color:#6EA8FE;');
    console.log('%c📋 Patients: <?= $patient_count ?> | Appointments: <?= $appointment_count ?>', 'font-size:12px; color:#9EC5FE;');
    console.log('%c👨‍⚕️ Assign Doctor: <?= $total_active_patients ?> (Assigned: <?= $assigned_count ?> + Lab Only: <?= $lab_only_count ?>)', 'font-size:12px; color:#F59E0B;');
    console.log('%c🔄 Auto-update active - badge updates automatically every 3 seconds', 'font-size:12px; color:#34D399;');
    console.log('%c💡 Check server error_log for detailed debug info', 'font-size:12px; color:#EF4444;');
</script>