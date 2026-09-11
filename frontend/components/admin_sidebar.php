<?php
// ================================================================
// FILE: frontend/components/admin_sidebar.php
// SUPER ADMIN - SHARED SIDEBAR (FULLY FIXED)
// FIXED: All badges BLUE background
// FIXED: Mobile sidebar z-index and shadow issues
// FIXED: Body scroll lock when sidebar opens on mobile
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

$selected_branch_id = $selected_branch_id ?? 'all';

// ================================================================
// DATABASE CONNECTION
// ================================================================
if (!isset($db) || $db === null) {
    require_once __DIR__ . '/../../backend/config/database.php';
    
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Admin sidebar DB connection error: " . $e->getMessage());
        $db = null;
    }
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

if ($db !== null) {
    try {
        // Total employees
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
            try {
                if ($selected_branch_id === 'all') {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
                    $stmt->execute([$module]);
                } else {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
                    $stmt->execute([$module, (int)$selected_branch_id]);
                }
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $module_counts[$module] = (int)($result['count'] ?? 0);
            } catch (Exception $e) {
                $module_counts[$module] = 0;
            }
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
            $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status IN ('pending', 'confirmed')");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status IN ('pending', 'confirmed')");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $pending_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Pending lab tests
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', 'in_progress')");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status IN ('pending', 'in_progress')");
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

$initial_hash = md5(json_encode([
    'total_employees' => $total_employees,
    'total_patients' => $total_patients,
    'total_doctors' => $total_doctors,
    'pharmacy_count' => $module_counts['pharmacy'],
    'reception_count' => $module_counts['reception'],
    'laboratory_count' => $module_counts['laboratory'],
    'cashier_count' => $module_counts['cashier'],
    'total_services' => $total_services,
    'total_branches' => $total_branches,
    'pending_prescriptions' => $pending_prescriptions,
    'pending_lab_tests' => $pending_lab_tests
]));

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $page === $current_page ? 'active' : '';
}

function isAdminPage($pages) {
    global $current_page;
    return in_array($current_page, $pages) ? 'active' : '';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<style>
/* ================================================================ */
/* SIDEBAR STYLES */
/* ================================================================ */
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

.sidebar.open {
    transform: translateX(0) !important;
}

.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: #34D399; }

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

/* ================================================================ */
/* ✅ ALL BADGES - BLUE BACKGROUND */
/* ================================================================ */
.sidebar-link .badge {
    margin-left: auto;
    background: #0B5ED7 !important;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #FFFFFF !important;
    transition: all 0.3s ease;
    flex-shrink: 0;
    min-width: 24px;
    text-align: center;
    border: 1.5px solid rgba(255,255,255,0.25);
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.4);
    line-height: 1.4;
}

.sidebar-link .badge.danger,
.sidebar-link .badge.success,
.sidebar-link .badge.warning,
.sidebar-link .badge.blue,
.sidebar-link .badge.purple,
.sidebar-link .badge.green {
    background: #0B5ED7 !important;
    color: #FFFFFF !important;
    border-color: rgba(255,255,255,0.25) !important;
}

.sidebar-link .badge.danger {
    animation: pulse-badge 2s infinite;
}

.sidebar-link:hover .badge {
    background: #1A73E8 !important;
    transform: scale(1.08);
    box-shadow: 0 3px 10px rgba(11, 94, 215, 0.6);
}

.sidebar-link.active .badge {
    background: #1A73E8 !important;
    color: #FFFFFF !important;
    border-color: rgba(255,255,255,0.4) !important;
}

@keyframes pulse-badge {
    0%, 100% { 
        transform: scale(1); 
        box-shadow: 0 2px 6px rgba(11, 94, 215, 0.4);
    }
    50% { 
        transform: scale(1.1); 
        box-shadow: 0 3px 12px rgba(11, 94, 215, 0.7);
    }
}

.badge-update {
    animation: badgePop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes badgePop {
    0% { transform: scale(0.3); opacity: 0; }
    60% { transform: scale(1.3); }
    100% { transform: scale(1); opacity: 1; }
}

.sidebar-data-flash {
    animation: flashGreen 0.6s ease;
}

@keyframes flashGreen {
    0% { background: rgba(52, 211, 153, 0.15); }
    50% { background: rgba(52, 211, 153, 0.03); }
    100% { background: transparent; }
}

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

/* ================================================================ */
/* ✅ OVERLAY - Base styles */
/* ================================================================ */
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

/* ================================================================ */
/* DESKTOP: Sidebar always visible */
/* ================================================================ */
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

/* ================================================================ */
/* ✅ TABLET/MOBILE: Fixed z-index and shadow */
/* ================================================================ */
@media (max-width: 1024px) {
    .sidebar {
        width: 280px;
        transform: translateX(-100%);
        z-index: 99999 !important;
        border-radius: 0 12px 12px 0;
        box-shadow: 2px 0 10px rgba(0,0,0,0.15);
        will-change: transform;
        isolation: isolate;
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
        box-shadow: 4px 0 20px rgba(0,0,0,0.25);
    }
    
    #sidebarOverlay {
        display: none;
        z-index: 99998 !important;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    
    #sidebarOverlay.active {
        display: block !important;
    }
    
    /* Ensure top-nav is below sidebar */
    .top-nav {
        z-index: 40 !important;
    }
    
    /* Make sidebar links clickable */
    .sidebar.open,
    .sidebar.open * {
        pointer-events: auto !important;
    }
    
    .sidebar-link {
        pointer-events: auto !important;
        cursor: pointer !important;
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

/* ================================================================ */
/* ✅ PHONE: Even smaller shadow */
/* ================================================================ */
@media (max-width: 768px) {
    .sidebar {
        width: 300px;
        transform: translateX(-100%);
        border-radius: 0 16px 16px 0;
        z-index: 99999 !important;
        box-shadow: 2px 0 8px rgba(0,0,0,0.15);
        will-change: transform;
        isolation: isolate;
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
        box-shadow: 4px 0 20px rgba(0,0,0,0.25);
    }
    
    #sidebarOverlay {
        z-index: 99998 !important;
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

/* ================================================================ */
/* ✅ SMALL PHONE: Full width with minimal shadow */
/* ================================================================ */
@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        max-width: 320px;
        transform: translateX(-100%);
        border-radius: 0 20px 20px 0;
        z-index: 99999 !important;
        box-shadow: 2px 0 6px rgba(0,0,0,0.15);
        will-change: transform;
        isolation: isolate;
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
        box-shadow: 4px 0 20px rgba(0,0,0,0.25);
    }
    
    #sidebarOverlay {
        z-index: 99998 !important;
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

/* ================================================================ */
/* ✅ PREVENT ANY OVERLAY FROM BLOCKING SIDEBAR */
/* ================================================================ */
@media (max-width: 1024px) {
    body.sidebar-open {
        overflow: hidden !important;
        position: fixed !important;
        width: 100% !important;
        height: 100% !important;
    }
}

@media print {
    .sidebar { display: none !important; }
    #sidebarOverlay { display: none !important; }
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Admin Sidebar">
    
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
    
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php
            try {
                if ($db !== null) {
                    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $sel = ($selected_branch_id == $row['id']) ? 'selected' : '';
                        echo '<option value="' . $row['id'] . '" ' . $sel . '>🏥 ' . htmlspecialchars($row['name']) . '</option>';
                    }
                }
            } catch (Exception $e) {}
            ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        
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
                <span class="badge" id="badgePatientsToday">+<?= $today_patients ?></span>
            <?php else: ?>
                <span class="badge" id="badgePatientsToday" style="display:none;">+0</span>
            <?php endif; ?>
        </a>
        
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
                <span class="badge" id="badgePendingPrescriptions"><?= $pending_prescriptions ?></span>
            <?php else: ?>
                <span class="badge" id="badgePendingPrescriptions" style="display:none;">0</span>
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
                <span class="badge" id="badgePendingLabTests"><?= $pending_lab_tests ?></span>
            <?php else: ?>
                <span class="badge" id="badgePendingLabTests" style="display:none;">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_cashier.php') ?>">
            <i class="fas fa-cash-register"></i>
            <span class="link-text">Cashier</span>
            <span class="badge" id="badgeCashier"><?= $module_counts['cashier'] ?? 0 ?></span>
        </a>
        
        <div class="nav-label"><span class="label-icon">💼</span> Services</div>
        
        <a href="/dispensary_system/frontend/pages/admin/services.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('services.php') || isActive('service_categories.php') ? 'active' : '' ?>">
            <i class="fas fa-concierge-bell"></i>
            <span class="link-text">Services</span>
            <span class="badge" id="badgeServices"><?= $total_services ?></span>
            <?php if ($today_services > 0): ?>
                <span class="badge" id="badgeServicesToday">+<?= $today_services ?></span>
            <?php else: ?>
                <span class="badge" id="badgeServicesToday" style="display:none;">+0</span>
            <?php endif; ?>
        </a>
        
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
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i>
            <span class="link-text">Reports</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">🔧</span> System</div>
        
        <a href="/dispensary_system/frontend/pages/admin/settings.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('settings.php') ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span class="link-text">Settings</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        
        <a href="/dispensary_system/frontend/pages/admin/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
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

<script>
// ================================================================
// SIDEBAR CONFIG
// ================================================================
var SIDEBAR_CONFIG = {
    CHECK_INTERVAL: 3000,
    BRANCH_ID: '<?= $selected_branch_id ?>',
    CURRENT_PAGE: '<?= $current_page ?>'
};

var sidebarState = {
    isUpdating: false,
    updateInterval: null,
    isOpen: false
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
// ✅ SIDEBAR TOGGLE - FIXED for mobile
// ================================================================
(function() {
    function initSidebar() {
        var sidebar = document.getElementById('sidebar');
        var toggleBtn = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            document.body.appendChild(overlay);
        }
        
        if (!sidebar) {
            console.error('❌ Sidebar not found');
            return;
        }
        
        function openSidebar() {
            sidebar.classList.add('open');
            overlay.style.display = 'block';
            overlay.classList.add('active');
            
            // ✅ Prevent body scroll with position fixed
            document.body.classList.add('sidebar-open');
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.height = '100%';
            
            // ✅ Ensure sidebar is on top
            sidebar.style.zIndex = '99999';
            overlay.style.zIndex = '99998';
            
            sidebarState.isOpen = true;
            console.log('🔓 Sidebar opened');
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
            overlay.classList.remove('active');
            
            // ✅ Restore body scroll
            document.body.classList.remove('sidebar-open');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
            
            sidebar.style.zIndex = '';
            overlay.style.zIndex = '';
            
            sidebarState.isOpen = false;
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
                    console.log('🔘 Hamburger clicked');
                    toggleSidebar();
                });
            }
        }
        
        // Overlay click
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeSidebar();
                }
            });
        }
        
        // ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        // Resize handler
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        // Close sidebar on link click (mobile only)
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024 && sidebarState.isOpen) {
                    setTimeout(closeSidebar, 100);
                }
            });
        });
        
        console.log('✅ Admin Sidebar initialized');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();

// ================================================================
// AUTO-UPDATE BADGE DATA
// ================================================================
function refreshSidebarBadges() {
    if (sidebarState.isUpdating) return;
    sidebarState.isUpdating = true;
    
    var formData = new FormData();
    formData.append('branch_id', SIDEBAR_CONFIG.BRANCH_ID);
    
    fetch('/dispensary_system/backend/api/admin_sidebar_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        sidebarState.isUpdating = false;
        
        if (data.success && data.data) {
            var badgeMap = {
                'badgeEmployees': 'total_employees',
                'badgePatients': 'total_patients',
                'badgeDoctors': 'total_doctors',
                'badgePharmacy': 'pharmacy_count',
                'badgeReception': 'reception_count',
                'badgeLaboratory': 'laboratory_count',
                'badgeCashier': 'cashier_count',
                'badgeServices': 'total_services',
                'badgeBranches': 'total_branches'
            };
            
            for (var elId in badgeMap) {
                var key = badgeMap[elId];
                if (data.data[key] !== undefined) {
                    var el = document.getElementById(elId);
                    if (el) {
                        var oldVal = el.textContent.trim();
                        var newVal = String(data.data[key]);
                        if (oldVal !== newVal) {
                            el.textContent = newVal;
                            el.classList.remove('badge-update');
                            void el.offsetWidth;
                            el.classList.add('badge-update');
                        }
                    }
                }
            }
            
            // Pending prescriptions
            var prEl = document.getElementById('badgePendingPrescriptions');
            if (prEl && data.data.pending_prescriptions !== undefined) {
                var val = parseInt(data.data.pending_prescriptions);
                if (val > 0) {
                    prEl.textContent = val;
                    prEl.style.display = '';
                } else {
                    prEl.style.display = 'none';
                }
            }
            
            // Pending lab tests
            var ltEl = document.getElementById('badgePendingLabTests');
            if (ltEl && data.data.pending_lab_tests !== undefined) {
                var val2 = parseInt(data.data.pending_lab_tests);
                if (val2 > 0) {
                    ltEl.textContent = val2;
                    ltEl.style.display = '';
                } else {
                    ltEl.style.display = 'none';
                }
            }
            
            // Today patients
            var tpEl = document.getElementById('badgePatientsToday');
            if (tpEl && data.data.today_patients !== undefined) {
                var val3 = parseInt(data.data.today_patients);
                if (val3 > 0) {
                    tpEl.textContent = '+' + val3;
                    tpEl.style.display = '';
                } else {
                    tpEl.style.display = 'none';
                }
            }
            
            // Today services
            var tsEl = document.getElementById('badgeServicesToday');
            if (tsEl && data.data.today_services !== undefined) {
                var val4 = parseInt(data.data.today_services);
                if (val4 > 0) {
                    tsEl.textContent = '+' + val4;
                    tsEl.style.display = '';
                } else {
                    tsEl.style.display = 'none';
                }
            }
        }
    })
    .catch(function(error) {
        sidebarState.isUpdating = false;
    });
}

function startSidebarAutoUpdate() {
    if (sidebarState.updateInterval) {
        clearInterval(sidebarState.updateInterval);
    }
    
    setTimeout(function() {
        refreshSidebarBadges();
    }, 1500);
    
    sidebarState.updateInterval = setInterval(function() {
        if (!document.hidden) {
            refreshSidebarBadges();
        }
    }, SIDEBAR_CONFIG.CHECK_INTERVAL);
}

document.addEventListener('DOMContentLoaded', function() {
    startSidebarAutoUpdate();
});

window.refreshSidebarData = refreshSidebarBadges;

console.log('%c🏥 Braick - Admin Sidebar (Mobile FIXED)', 'font-size:16px; font-weight:bold; color:#0AA84F;');
console.log('%c✅ All badges have BLUE background', 'font-size:13px; color:#34D399;');
console.log('%c✅ Mobile sidebar z-index: 99999', 'font-size:13px; color:#34D399;');
console.log('%c✅ Mobile sidebar shadow: reduced', 'font-size:13px; color:#34D399;');
console.log('%c✅ Body scroll lock: fixed', 'font-size:13px; color:#34D399;');
console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#34D399;');
console.log('%c📊 Cashier: <?= $module_counts['cashier'] ?> | Pharmacy: <?= $module_counts['pharmacy'] ?>', 'font-size:13px; color:#34D399;');
</script>