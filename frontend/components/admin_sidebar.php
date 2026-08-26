<?php
// ================================================================
// FILE: frontend/components/admin_sidebar.php
// SUPER ADMIN - SHARED SIDEBAR (FULLY FIXED WITH API)
// FULLY RESPONSIVE - ALL DEVICES
// BACKGROUND: BLUE | HOVER: GREEN
// WITH API REAL-TIME DATA UPDATES
// WITH LOGIN PROTECTION
// WITH ABSOLUTE PATHS
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Admin only)
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
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
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

// Pass these variables from each page
$selected_branch_id = $selected_branch_id ?? 'all';

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
// GET INITIAL DATA FOR BADGES
// ================================================================
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;
$total_patients = 0;
$today_patients = 0;
$total_services = 0;
$today_services = 0;
$module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        // Total employees (non-admin)
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active' AND branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total doctors
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $total_doctors = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Module counts
        $modules = ['pharmacy', 'reception', 'laboratory', 'cashier'];
        foreach ($modules as $module) {
            if ($selected_branch_id === 'all') {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
                $stmt->execute([$module]);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
                $stmt->execute([$module, (int)$selected_branch_id]);
            }
            $module_counts[$module] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        }
        
        // Total patients
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's patients
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $today_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total services
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled'");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $total_services = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's services
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled' AND DATE(created_at) = CURDATE()");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $today_services = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Pending prescriptions
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $pending_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Pending lab tests
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', '') OR status IS NULL");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND (status IN ('pending', '') OR status IS NULL)");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $pending_lab_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total branches
        $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
        $total_branches = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Admin sidebar initial data error: " . $e->getMessage());
    }
}

// ================================================================
// GENERATE INITIAL HASH
// ================================================================
$initial_hash = md5(json_encode([
    'total_employees' => $total_employees,
    'total_patients' => $total_patients,
    'total_doctors' => $total_doctors,
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests,
    'total_branches' => $total_branches
]));

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

function isAdminPage($pages) {
    global $current_page;
    if (in_array($current_page, $pages)) {
        return 'active';
    }
    return '';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PASS DATA TO JAVASCRIPT
// ================================================================
$initial_data = [
    'total_employees' => $total_employees,
    'total_patients' => $total_patients,
    'today_patients' => $today_patients,
    'total_doctors' => $total_doctors,
    'pharmacy_count' => $module_counts['pharmacy'],
    'reception_count' => $module_counts['reception'],
    'laboratory_count' => $module_counts['laboratory'],
    'cashier_count' => $module_counts['cashier'],
    'total_services' => $total_services,
    'today_services' => $today_services,
    'total_branches' => $total_branches,
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests,
    'branch_id' => $selected_branch_id,
    'user_name' => $user_full_name,
    'user_is_online' => $user_is_online
];
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - FULL
       ================================================================ */
    
    /* Sidebar Container */
    .sidebar {
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0;
        width: 270px; 
        background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
        color: white;
        z-index: 50; 
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(-100%);
        box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        scroll-behavior: smooth;
    }
    
    [data-theme="dark"] .sidebar {
        background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    /* Sidebar Open State */
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    /* Scrollbar */
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #34D399; }
    
    /* ================================================================
       SIDEBAR BRAND / HEADER
       ================================================================ */
    .sidebar-brand {
        padding: 18px 16px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 5;
        backdrop-filter: blur(10px);
    }
    
    .sidebar-brand .logo {
        width: 42px; 
        height: 42px; 
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
    
    /* ================================================================
       BRANCH SELECTOR
       ================================================================ */
    .sidebar-branch-selector {
        padding: 10px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.06);
        background: rgba(0,0,0,0.05);
    }
    
    .sidebar-branch-selector select {
        width: 100%;
        padding: 7px 10px;
        border-radius: 8px;
        border: none;
        background: rgba(255,255,255,0.12);
        color: white;
        font-size: 0.75rem;
        cursor: pointer;
        outline: none;
        transition: all 0.3s ease;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
    }
    
    .sidebar-branch-selector select:hover {
        background-color: rgba(255,255,255,0.2);
    }
    
    .sidebar-branch-selector select:focus {
        box-shadow: 0 0 0 2px rgba(10, 168, 79, 0.5);
    }
    
    .sidebar-branch-selector select option {
        background: #0B4EA8;
        color: white;
        padding: 8px;
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
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
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
        background: rgba(10, 168, 79, 0.4);
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
        transform: translateX(4px);
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
    
    .sidebar-link .badge.success {
        background: #059669;
        border-color: #059669;
    }
    
    .sidebar-link .badge.warning {
        background: #F59E0B;
        color: #1E293B;
        border-color: #F59E0B;
    }
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
        border-color: #0B5ED7;
    }
    
    .sidebar-link .badge.purple {
        background: #7C3AED;
        border-color: #7C3AED;
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
        0% { background: rgba(52, 211, 153, 0.15); }
        50% { background: rgba(52, 211, 153, 0.03); }
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
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
        display: inline-block;
    }
    
    /* ================================================================
       SIDEBAR STATUS FOOTER
       ================================================================ */
    .sidebar-status {
        padding: 10px 16px;
        border-top: 2px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.1);
        position: sticky;
        bottom: 0;
        backdrop-filter: blur(10px);
    }
    
    .sidebar-status .status-dot {
        width: 8px;
        height: 8px;
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
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
    }
    
    /* ================================================================
       OVERLAY - For mobile
       ================================================================ */
    #sidebarOverlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 45;
        display: none;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        transition: opacity 0.3s ease;
    }
    
    #sidebarOverlay.active {
        display: block !important;
    }
    
    /* ================================================================
       RESPONSIVE BREAKPOINTS
       ================================================================ */
    
    /* Desktop: Sidebar always visible */
    @media (min-width: 1025px) {
        .sidebar {
            transform: translateX(0) !important;
            z-index: 50;
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
        }
        #sidebarOverlay {
            display: none !important;
        }
    }
    
    /* Tablet and below: Sidebar hidden by default */
    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            z-index: 9999;
            border-radius: 0 12px 12px 0;
            box-shadow: 4px 0 30px rgba(0,0,0,0.3);
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
        .sidebar-nav .nav-label {
            font-size: 0.45rem;
        }
        .sidebar-status {
            padding: 8px 14px;
        }
    }
    
    /* Mobile phones */
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
            font-size: 0.4rem;
        }
        .sidebar-status {
            padding: 6px 12px;
        }
        .sidebar-status .status-text {
            font-size: 0.6rem;
        }
        .sidebar-status .update-time {
            font-size: 0.5rem;
        }
    }
    
    /* Small phones */
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
        .sidebar-status .update-time {
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
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Admin Sidebar">
    
    <!-- ================================================================ -->
    <!-- BRAND / HEADER -->
    <!-- ================================================================ -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">👑 Super Admin</p>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- BRANCH SELECTOR -->
    <!-- ================================================================ -->
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php
            try {
                if ($db !== null) {
                    $branches_list = [];
                    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $branches_list[] = $row;
                    }
                    foreach ($branches_list as $branch) {
                        $sel = ($selected_branch_id == $branch['id']) ? 'selected' : '';
                        echo '<option value="' . $branch['id'] . '" ' . $sel . '>🏥 ' . htmlspecialchars($branch['name']) . '</option>';
                    }
                }
            } catch (Exception $e) {}
            ?>
        </select>
    </div>
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav">
        
        <!-- ============================================================ -->
        <!-- MAIN MENU -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">📋</span> Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i>
            <span class="link-text">Dashboard</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') ?>">
            <i class="fas fa-users"></i>
            <span class="link-text">Employees</span>
            <span class="badge" id="badgeEmployees"><?= $total_employees ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/patients.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('patients.php') || isAdminPage(['patient_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i>
            <span class="link-text">Patients</span>
            <span class="badge" id="badgePatients"><?= $total_patients ?></span>
            <?php if ($today_patients > 0): ?>
                <span class="badge success" id="badgePatientsToday">+<?= $today_patients ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- MODULES -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">⚙️</span> Modules</div>
        
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('doctors_list.php') || isAdminPage(['view_doctor.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i>
            <span class="link-text">Doctors</span>
            <span class="badge" id="badgeDoctors"><?= $total_doctors ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_pharmacy.php') ?>">
            <i class="fas fa-prescription"></i>
            <span class="link-text">Pharmacy</span>
            <span class="badge" id="badgePharmacy"><?= $module_counts['pharmacy'] ?? 0 ?></span>
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge danger" id="badgePendingPrescriptions"><?= $pending_prescriptions ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_reception.php') ?>">
            <i class="fas fa-headset"></i>
            <span class="link-text">Reception</span>
            <span class="badge" id="badgeReception"><?= $module_counts['reception'] ?? 0 ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_laboratory.php') ?>">
            <i class="fas fa-flask"></i>
            <span class="link-text">Laboratory</span>
            <span class="badge" id="badgeLaboratory"><?= $module_counts['laboratory'] ?? 0 ?></span>
            <?php if ($pending_lab_tests > 0): ?>
                <span class="badge danger" id="badgePendingLabTests"><?= $pending_lab_tests ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_cashier.php') ?>">
            <i class="fas fa-cash-register"></i>
            <span class="link-text">Cashier</span>
            <span class="badge" id="badgeCashier"><?= $module_counts['cashier'] ?? 0 ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- SERVICES -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">💼</span> Services</div>
        
        <a href="/dispensary_system/frontend/pages/admin/services.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('services.php') || isActive('service_categories.php') ? 'active' : '' ?>">
            <i class="fas fa-concierge-bell"></i>
            <span class="link-text">Services</span>
            <span class="badge" id="badgeServices"><?= $total_services ?></span>
            <?php if ($today_services > 0): ?>
                <span class="badge success" id="badgeServicesToday">+<?= $today_services ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- MANAGEMENT -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">🏢</span> Management</div>
        
        <a href="/dispensary_system/frontend/pages/admin/branches.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('branches.php') || isAdminPage(['view_branch.php', 'add_branch.php', 'edit_branch.php']) ? 'active' : '' ?>">
            <i class="fas fa-store-alt"></i>
            <span class="link-text">Branches</span>
            <span class="badge" id="badgeBranches"><?= $total_branches ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/departments.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('departments.php') || isAdminPage(['add_department.php', 'edit_department.php']) ? 'active' : '' ?>">
            <i class="fas fa-building"></i>
            <span class="link-text">Departments</span>
        </a>
        
        <!-- BILLS MENU REMOVED -->
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i>
            <span class="link-text">Reports</span>
        </a>
        
        <!-- ============================================================ -->
        <!-- SYSTEM -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">🔧</span> System</div>
        
        <a href="/dispensary_system/frontend/pages/admin/settings.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('settings.php') ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span class="link-text">Settings</span>
        </a>
        
        <!-- ============================================================ -->
        <!-- ACCOUNT -->
        <!-- ============================================================ -->
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        
        <a href="/dispensary_system/frontend/pages/admin/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <!-- ============================================================ -->
        <!-- LOGOUT - ABSOLUTE PATH -->
        <!-- ============================================================ -->
        <a href="/dispensary_system/frontend/pages/logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
    <!-- ================================================================ -->
    <!-- SIDEBAR STATUS FOOTER -->
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
<!-- JAVASCRIPT - FULL SIDEBAR FUNCTIONALITY WITH API -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var SIDEBAR_CONFIG = {
        API_URL: '/dispensary_system/backend/api/get_admin_sidebar_stats.php',
        CHECK_INTERVAL: 2000,
        FORCE_INTERVAL: 10000,
        BRANCH_ID: '<?= $selected_branch_id ?>',
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        if (url.searchParams.has('id')) {
            url.searchParams.delete('id');
        }
        window.location.href = url.toString();
    }

    // ================================================================
    // SIDEBAR TOGGLE - FULLY FIXED
    // ================================================================
    (function() {
        function initSidebar() {
            console.log('🔧 Initializing Admin Sidebar...');
            
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                document.body.appendChild(overlay);
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
            
            // Toggle button (hamburger)
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
                var fallbackBtn = document.querySelector('.sidebar-toggle-btn');
                if (fallbackBtn) {
                    fallbackBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleSidebar();
                    });
                    console.log('✅ Fallback toggle button attached');
                }
            }
            
            // Close button (X)
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
            
            // Window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            console.log('✅ Admin Sidebar fully initialized!');
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
        
        var badgeMap = {
            'badgeEmployees': 'total_employees',
            'badgePatients': 'total_patients',
            'badgeDoctors': 'total_doctors',
            'badgePharmacy': 'pharmacy_count',
            'badgeReception': 'reception_count',
            'badgeLaboratory': 'laboratory_count',
            'badgeCashier': 'cashier_count',
            'badgeServices': 'total_services',
            'badgeBranches': 'total_branches',
            'badgePendingPrescriptions': 'pending_prescriptions',
            'badgePendingLabTests': 'pending_lab_tests'
        };
        
        for (var elId in badgeMap) {
            var key = badgeMap[elId];
            if (data[key] !== undefined) {
                var el = document.getElementById(elId);
                if (el) {
                    var oldVal = el.textContent;
                    var newVal = data[key];
                    if (oldVal !== String(newVal)) {
                        hasChanges = true;
                        el.textContent = newVal;
                        el.classList.remove('badge-update');
                        void el.offsetWidth;
                        el.classList.add('badge-update');
                        
                        // Update badge class based on value
                        var numVal = parseInt(newVal);
                        if (key === 'pending_prescriptions' || key === 'pending_lab_tests') {
                            el.className = numVal > 0 ? 'badge danger badge-update' : 'badge badge-update';
                        }
                        if (key === 'total_patients' || key === 'total_employees' || 
                            key === 'total_doctors' || key === 'total_branches' || key === 'total_services') {
                            el.className = numVal > 0 ? 'badge blue badge-update' : 'badge badge-update';
                        }
                        if (key === 'pharmacy_count' || key === 'reception_count' || 
                            key === 'laboratory_count' || key === 'cashier_count') {
                            el.className = numVal > 0 ? 'badge purple badge-update' : 'badge badge-update';
                        }
                    }
                }
            }
        }
        
        // Today patients badge
        var todayPatientEl = document.getElementById('badgePatientsToday');
        if (todayPatientEl && data.today_patients !== undefined) {
            var oldVal = todayPatientEl.textContent;
            var newVal = data.today_patients > 0 ? '+' + data.today_patients : '';
            if (oldVal !== newVal) {
                hasChanges = true;
                if (data.today_patients > 0) {
                    todayPatientEl.textContent = newVal;
                    todayPatientEl.style.display = '';
                    todayPatientEl.className = 'badge success badge-update';
                } else {
                    todayPatientEl.style.display = 'none';
                }
            }
        }
        
        // Today services badge
        var todayServiceEl = document.getElementById('badgeServicesToday');
        if (todayServiceEl && data.today_services !== undefined) {
            var oldVal = todayServiceEl.textContent;
            var newVal = data.today_services > 0 ? '+' + data.today_services : '';
            if (oldVal !== newVal) {
                hasChanges = true;
                if (data.today_services > 0) {
                    todayServiceEl.textContent = newVal;
                    todayServiceEl.style.display = '';
                    todayServiceEl.className = 'badge success badge-update';
                } else {
                    todayServiceEl.style.display = 'none';
                }
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
                    
                    var event = new CustomEvent('sidebarDataUpdated', {
                        detail: {
                            data: data.data,
                            summary: data.summary,
                            timestamp: data.timestamp
                        }
                    });
                    document.dispatchEvent(event);
                    
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
    console.log('%c🏥 Braick Dispensary - Admin Sidebar (API Integrated)', 
        'font-size:16px; font-weight:bold; color:#0AA84F;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 
        'font-size:13px; color:#34D399;');
    console.log('%c📊 Initial Data:', 'font-size:13px; font-weight:bold; color:#D97706;');
    console.log('   Employees: <?= $total_employees ?>, Patients: <?= $total_patients ?>');
    console.log('   Doctors: <?= $total_doctors ?>, Branches: <?= $total_branches ?>');
    console.log('   Pending Prescriptions: <?= $pending_prescriptions ?>, Pending Lab: <?= $pending_lab_tests ?>');
    console.log('%c⚡ Smart Auto-Update: Every 2s (only if data changed)', 
        'font-size:13px; color:#34D399;');
    console.log('%c🔄 Force refresh: Every 10s (safety net)', 
        'font-size:13px; color:#F59E0B;');
    console.log('%c📡 API Endpoint: ' + SIDEBAR_CONFIG.API_URL, 
        'font-size:12px; color:#94A3B8;');
    console.log('%c💡 Call window.refreshSidebarData() to manually update', 
        'font-size:12px; color:#6EA8FE;');
    console.log('%c📱 Click ☰ in header to open sidebar on mobile', 
        'font-size:12px; color:#34D399;');
    console.log('%c✅ Data updates automatically when database changes!', 
        'font-size:13px; font-weight:bold; color:#34D399;');
    console.log('%c🚫 Bills menu: REMOVED', 
        'font-size:12px; color:#DC2626;');
    console.log('%c🚪 Logout: /dispensary_system/frontend/pages/logout.php (ABSOLUTE PATH)', 
        'font-size:12px; color:#F87171;');
</script>