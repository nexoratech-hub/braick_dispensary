<?php
// ================================================================
// FILE: frontend/components/admin_audit_sidebar.php
// ADMIN AUDIT - SIDEBAR (V9 - REVENUE BADGE FIXED)
// ✅ Rangi: #0B4EA8 → #0A3D7A (Deep Blue)
// ✅ Hover: GREEN (#0AA84F)
// ✅ Size: 270px
// ✅ Branch Selector
// ✅ Admin role only
// ✅ FIXED: Toggle button INAFANYA KAZI kwenye mobile NA desktop
// ✅ FIXED: Revenue badge = Bills + OTC (leo)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ✅ ADMIN TU
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'audit': header('Location: /dispensary_system/frontend/pages/audit/dashboard.php'); break;
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

$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// DATABASE CONNECTION
// ================================================================
if (!isset($db) || $db === null) {
    require_once __DIR__ . '/../../backend/config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Admin audit sidebar DB error: " . $e->getMessage());
        $db = null;
    }
}

// ================================================================
// BUILD BRANCH CONDITION
// ================================================================
$branch_cond = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_cond = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

// ================================================================
// GET BADGE DATA
// ================================================================
$total_revenue_today = 0;        // ✅ Bills + OTC (leo)
$bills_today = 0;
$otc_today = 0;
$total_patients = 0;
$today_patients = 0;
$total_employees = 0;
$total_doctors = 0;
$total_inventory_items = 0;
$low_stock_count = 0;
$total_audit_logs = 0;
$today_audit_logs = 0;
$total_branches = 0;

if ($db !== null) {
    
    // ============================================================
    // ✅ REVENUE BADGE: Bills (leo, paid) + OTC (leo, paid)
    // ============================================================
    try {
        // Patient Bills (paid, today)
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                FROM bills 
                WHERE status = 'paid' 
                AND DATE(updated_at) = CURDATE()
                AND patient_id IS NOT NULL
                AND visit_id IS NOT NULL
                AND bill_number NOT LIKE 'BILL-OTC-%'" . $branch_cond;
        $stmt = $db->prepare($sql);
        $stmt->execute($branch_params);
        $bills_today = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        // OTC Sales (paid, today)
        $otc_cond = "";
        $otc_params = [];
        if ($selected_branch_id !== 'all') {
            $otc_cond = " AND branch_id = ?";
            $otc_params = [(int)$selected_branch_id];
        }
        
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                FROM otc_sales 
                WHERE payment_status = 'paid' 
                AND DATE(updated_at) = CURDATE()" . $otc_cond;
        $stmt = $db->prepare($sql);
        $stmt->execute($otc_params);
        $otc_today = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (Exception $e) {}
    
    // ✅ TOTAL REVENUE = Bills + OTC
    $total_revenue_today = $bills_today + $otc_today;
    
    // ============================================================
    // PATIENTS
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM patients WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $today_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // EMPLOYEES
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role NOT IN ('admin', 'audit') AND status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_doctors = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // INVENTORY
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_inventory_items = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active' AND quantity <= 10" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $low_stock_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // AUDIT LOGS
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_audit_logs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $today_audit_logs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // BRANCHES
    // ============================================================
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
        $total_branches = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
}

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $page === $current_page ? 'active' : '';
}

function isAdminAuditPage($pages) {
    global $current_page;
    return in_array($current_page, $pages) ? 'active' : '';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ================================================================
   ✅ ADMIN AUDIT SIDEBAR STYLES
   ================================================================ */

.sidebar {
    position: fixed; 
    top: 0; left: 0; bottom: 0;
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

.sidebar.open { transform: translateX(0) !important; }

.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: #34D399; }

/* BRAND */
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

.sidebar-brand .logo:hover { transform: rotate(-5deg) scale(1.05); }

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
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}

.audit-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
}

.audit-role-badge i { font-size: 0.62rem; }

/* BRANCH SELECTOR */
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

.sidebar-branch-selector select:hover { background-color: rgba(255,255,255,0.2); }
.sidebar-branch-selector select:focus { box-shadow: 0 0 0 2px rgba(10, 168, 79, 0.5); }
.sidebar-branch-selector select option { background: #0B4EA8; color: white; padding: 8px; }

/* NAVIGATION */
.sidebar-nav { padding: 10px 8px 20px; }

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

.sidebar-nav .nav-label:first-of-type { margin-top: 0; }
.sidebar-nav .nav-label .label-icon { margin-right: 4px; }

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
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
}

.sidebar-link:hover i,
.sidebar-link.active i { opacity: 1; }

.sidebar-link .link-text {
    flex: 1; 
    white-space: nowrap;
    overflow: hidden; 
    text-overflow: ellipsis;
}

/* BADGES */
.sidebar-link .badge {
    margin-left: auto;
    background: #0B5ED7 !important;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #FFFFFF !important;
    flex-shrink: 0;
    min-width: 24px;
    text-align: center;
    border: 1.5px solid rgba(255,255,255,0.25);
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.4);
    line-height: 1.4;
}

.sidebar-link .badge.badge-new {
    background: #10B981 !important;
    color: #FFFFFF !important;
    border-color: rgba(255,255,255,0.4) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    animation: pulse-new 2s infinite;
    font-size: 0.55rem;
    letter-spacing: 0.05em;
    font-weight: 800;
}

@keyframes pulse-new {
    0%, 100% { transform: scale(1); box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6); }
    50% { transform: scale(1.08); box-shadow: 0 3px 14px rgba(16, 185, 129, 0.9); }
}

.sidebar-link .badge.badge-warning {
    background: #F59E0B !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.6);
}

.sidebar-link .badge.badge-revenue {
    background: linear-gradient(135deg, #10B981, #059669) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
}

.sidebar-link:hover .badge {
    background: #1A73E8 !important;
    transform: scale(1.08);
}

/* LOGOUT */
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

.sidebar-link.logout-link i { opacity: 1; }

/* BACK TO ADMIN */
.sidebar-link.back-admin {
    background: rgba(245, 158, 11, 0.2);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #FCD34D;
    font-weight: 600;
    margin-top: 4px;
    padding: 8px 12px;
}

.sidebar-link.back-admin:hover {
    background: rgba(10, 168, 79, 0.4);
    color: white;
    box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
    border: 1px solid transparent;
    transform: translateX(4px);
}

.sidebar-link.back-admin i { opacity: 1; color: #FCD34D; }
.sidebar-link.back-admin:hover i { color: white !important; }

/* STATUS FOOTER */
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
}

.sidebar-status .status-dot.online {
    background: #34D399;
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
    animation: pulse-dot 1.5s infinite;
}

.sidebar-status .status-dot.offline { background: #94A3B8; }

.sidebar-status .status-text {
    font-size: 0.65rem; 
    color: #D2E3FC; 
    font-weight: 500;
}

.sidebar-status .update-time {
    font-size: 0.5rem; 
    color: #6EA8FE;
    margin-left: auto; 
}

.sidebar-live-indicator {
    display: inline-flex; 
    align-items: center; 
    gap: 4px;
    font-size: 0.5rem; 
    color: #34D399;
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

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.8); }
}

/* OVERLAY */
#sidebarOverlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 45; 
    display: none;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    transition: opacity 0.3s ease;
}

#sidebarOverlay.active { display: block !important; }

/* ================================================================
   ✅ TOGGLE BUTTON - FIXED (INAFANYA KAZI)
   ================================================================ */
#sidebarToggle {
    display: none;
    position: fixed !important;
    top: 16px !important;
    left: 16px !important;
    z-index: 2147483647 !important;
    width: 48px !important;
    height: 48px !important;
    border-radius: 12px;
    background: linear-gradient(135deg, #0B4EA8 0%, #0A3D7A 100%);
    color: white;
    border: 2px solid rgba(255,255,255,0.4);
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(11, 78, 168, 0.6);
    transition: all 0.3s ease;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    padding: 0;
    margin: 0;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    pointer-events: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
}

#sidebarToggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(11, 78, 168, 0.8);
    background: linear-gradient(135deg, #0AA84F 0%, #0B4EA8 100%);
}

#sidebarToggle:active { transform: scale(0.92); }

[data-theme="dark"] #sidebarToggle {
    background: linear-gradient(135deg, #0A3D7A 0%, #082F5E 100%);
    border-color: rgba(255,255,255,0.5);
}

#sidebarToggle i {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
    pointer-events: none !important;
    font-size: 1.3rem;
    line-height: 1;
    color: white;
}

@media (max-width: 1024px) {
    #sidebarToggle { 
        display: flex !important; 
    }
}

@media (min-width: 1025px) {
    #sidebarToggle { 
        display: none !important; 
    }
}

/* RESPONSIVE */
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
    }
    
    #sidebarOverlay.active { display: block !important; }
    
    .top-nav { z-index: 40 !important; }
    
    .sidebar.open, .sidebar.open * { pointer-events: auto !important; }
    .sidebar-link { pointer-events: auto !important; cursor: pointer !important; }
    
    .sidebar-brand { padding: 14px 14px 10px; }
    .sidebar-brand .logo { width: 36px; height: 36px; }
    .sidebar-brand .brand-text { font-size: 0.85rem; }
    .sidebar-link { padding: 7px 10px; font-size: 0.75rem; gap: 8px; }
    .sidebar-link i { width: 18px; font-size: 0.8rem; }
    .sidebar-link .badge { font-size: 0.55rem; padding: 1px 7px; }
    .sidebar-nav .nav-label { font-size: 0.45rem; }
    .sidebar-status { padding: 8px 14px; }
}

@media (min-width: 1025px) {
    .sidebar {
        transform: translateX(0) !important;
        z-index: 50;
        box-shadow: 4px 0 20px rgba(0,0,0,0.08);
    }
    #sidebarOverlay { display: none !important; }
}

@media (max-width: 768px) {
    .sidebar { width: 300px; border-radius: 0 16px 16px 0; }
    .sidebar-link { padding: 6px 10px; font-size: 0.7rem; gap: 8px; }
    .sidebar-link i { width: 16px; font-size: 0.75rem; }
    .sidebar-link .badge { font-size: 0.5rem; padding: 1px 6px; }
    .sidebar-nav .nav-label { font-size: 0.4rem; }
    #sidebarToggle { width: 46px !important; height: 46px !important; font-size: 1.2rem; top: 14px !important; left: 14px !important; }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%; 
        max-width: 320px;
        border-radius: 0 20px 20px 0;
    }
    .sidebar-link { padding: 5px 8px; font-size: 0.65rem; gap: 6px; }
    .sidebar-link i { width: 14px; font-size: 0.7rem; }
    .sidebar-link .badge { font-size: 0.45rem; padding: 1px 5px; min-width: 16px; }
    .sidebar-nav .nav-label { font-size: 0.4rem; padding: 0 8px; }
    #sidebarToggle { width: 44px !important; height: 44px !important; font-size: 1.15rem; top: 12px !important; left: 12px !important; }
}

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
    #sidebarToggle { display: none !important; }
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-3 { gap: 12px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div id="sidebarOverlay"></div>

<!-- TOGGLE BUTTON -->
<button id="sidebarToggle" type="button" aria-label="Toggle Sidebar" title="Toggle Sidebar" onclick="window.__toggleSidebar && window.__toggleSidebar(event)">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Admin Audit Sidebar">
    
    <!-- BRAND -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">
                    <i class="fas fa-search"></i>
                    Audit & Reports
                </p>
            </div>
        </div>
        <div style="margin-top:6px;padding:0 2px;">
            <span class="audit-role-badge">
                <i class="fas fa-crown"></i> SUPER ADMIN
            </span>
        </div>
    </div>
    
    <!-- BRANCH SELECTOR -->
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
    
    <!-- NAVIGATION -->
    <nav class="sidebar-nav">
        
        <div class="nav-label">
            <span class="label-icon">📋</span> Main Menu
        </div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link back-admin">
            <i class="fas fa-arrow-left"></i>
            <span class="link-text">Back to Admin</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/audit/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-shield-alt"></i>
            <span class="link-text">Audit Dashboard</span>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">📊</span> Reports
        </div>
        
        <!-- ✅ REVENUE BADGE: Bills + OTC (leo) -->
        <a href="/dispensary_system/frontend/pages/admin/audit/revenue.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('revenue.php') || isAdminAuditPage(['revenue_details.php', 'view_bill.php', 'edit_bill.php']) ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span class="link-text">Revenue</span>
            <span class="badge badge-revenue" id="badgeRevenue">TSh <?= number_format($total_revenue_today, 0) ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/audit/inventory.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('inventory.php') || isAdminAuditPage(['inventory_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-pills"></i>
            <span class="link-text">Inventory</span>
            <span class="badge" id="badgeInventory"><?= $total_inventory_items ?></span>
            <?php if ($low_stock_count > 0): ?>
                <span class="badge badge-warning" id="badgeLowStock"><?= $low_stock_count ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/audit/patients.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('patients.php') || isAdminAuditPage(['patient_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i>
            <span class="link-text">Patients</span>
            <span class="badge" id="badgePatients"><?= $total_patients ?></span>
            <?php if ($today_patients > 0): ?>
                <span class="badge badge-new" id="badgePatientsToday">+<?= $today_patients ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">👥</span> Performance
        </div>
        
        <a href="/dispensary_system/frontend/pages/admin/audit/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') || isAdminAuditPage(['employee_details.php', 'doctor_performance.php', 'reception_performance.php']) ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span class="link-text">Employees</span>
            <span class="badge" id="badgeEmployees"><?= $total_employees ?></span>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">🔍</span> Audit
        </div>
        
        <a href="/dispensary_system/frontend/pages/admin/audit/audit_logs.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('audit_logs.php') || isAdminAuditPage(['audit_log_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span class="link-text">Audit Logs</span>
            <span class="badge" id="badgeAuditLogs"><?= $total_audit_logs ?></span>
            <?php if ($today_audit_logs > 0): ?>
                <span class="badge badge-new" id="badgeTodayAudit">+<?= $today_audit_logs ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">👤</span> Account
        </div>
        
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
    
    <!-- STATUS FOOTER -->
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
// ✅ SIDEBAR TOGGLE V8 - GUARANTEED TO WORK
// ================================================================
(function() {
    'use strict';
    
    window.__toggleSidebar = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');
        
        if (!sidebar) {
            console.warn('⚠️ Sidebar not found');
            return false;
        }
        
        var isOpen = sidebar.classList.contains('open');
        
        if (isOpen) {
            sidebar.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('active');
                overlay.style.display = 'none';
            }
            document.body.classList.remove('sidebar-open');
            document.body.style.overflow = '';
            
            if (toggle) {
                var icon = toggle.querySelector('i');
                if (icon) icon.className = 'fas fa-bars';
            }
            console.log('%c❌ Sidebar CLOSED', 'color:#DC2626; font-weight:bold;');
        } else {
            sidebar.classList.add('open');
            if (overlay) {
                overlay.classList.add('active');
                overlay.style.display = 'block';
            }
            document.body.classList.add('sidebar-open');
            document.body.style.overflow = 'hidden';
            
            if (toggle) {
                var icon = toggle.querySelector('i');
                if (icon) icon.className = 'fas fa-times';
            }
            console.log('%c✅ Sidebar OPENED', 'color:#10B981; font-weight:bold;');
        }
        
        return false;
    };
    
    window.toggleSidebar = window.__toggleSidebar;
    window.openSidebar = function() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && !sidebar.classList.contains('open')) {
            window.__toggleSidebar();
        }
    };
    window.closeSidebar = function() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            window.__toggleSidebar();
        }
    };
    
    function attachListeners() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');
        
        if (!sidebar || !toggle) {
            console.warn('⚠️ Missing elements, retrying...');
            return false;
        }
        
        toggle.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.__toggleSidebar(e);
            return false;
        };
        
        if (overlay) {
            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    window.closeSidebar();
                }
            };
        }
        
        console.log('%c✅ Listeners attached', 'color:#10B981;');
        return true;
    }
    
    document.addEventListener('click', function(e) {
        var target = e.target;
        while (target && target !== document) {
            if (target.id === 'sidebarToggle') {
                e.preventDefault();
                e.stopPropagation();
                window.__toggleSidebar(e);
                return false;
            }
            target = target.parentNode;
        }
    }, true);
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('open')) {
                window.closeSidebar();
            }
        }
    });
    
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) {
                var sidebar = document.getElementById('sidebar');
                if (sidebar && sidebar.classList.contains('open')) {
                    window.closeSidebar();
                }
            }
        }, 200);
    });
    
    function init() {
        attachListeners();
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    setTimeout(window.closeSidebar, 150);
                }
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    window.addEventListener('load', init);
    setTimeout(init, 500);
    
})();

console.log('%c👑 Admin Audit Sidebar V9 - REVENUE BADGE FIXED', 'font-size:16px; font-weight:bold; color:#0B4EA8;');
console.log('%c✅ Revenue badge = Bills + OTC (leo)', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c💰 Bills Today: TSh <?= number_format($bills_today, 0) ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c💊 OTC Today: TSh <?= number_format($otc_today, 0) ?>', 'font-size:13px; color:#0891B2;');
console.log('%c📊 Total: TSh <?= number_format($total_revenue_today, 0) ?>', 'font-size:13px; color:#10B981; font-weight:bold;');
</script>