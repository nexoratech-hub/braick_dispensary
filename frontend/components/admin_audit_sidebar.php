<?php
// ================================================================
// FILE: frontend/components/admin_audit_sidebar.php
// ADMIN AUDIT - SIDEBAR (COMPACT + GREEN HOVER + JETBRAINS MONO) - V6
// ✅ Size imepunguzwa (280px → 260px)
// ✅ Hover = GREEN (#059669)
// ✅ Font: JetBrains Mono (sawa na pages)
// ✅ Icons nzuri zaidi
// ✅ BLUE THEME: #0B5ED7 (base)
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

// DATABASE CONNECTION
if (!isset($db) || $db === null) {
    require_once __DIR__ . '/../../backend/config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Admin audit sidebar DB error: " . $e->getMessage());
        $db = null;
    }
}

// BUILD BRANCH CONDITION
$branch_cond = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_cond = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

// GET BADGE DATA
$total_revenue_today = 0;
$total_patients = 0;
$total_employees = 0;
$total_inventory_items = 0;
$low_stock_count = 0;
$total_audit_logs = 0;
$today_audit_logs = 0;

if ($db !== null) {
    try {
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE status = 'paid' AND DATE(updated_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_revenue_today = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM patients WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role NOT IN ('admin', 'audit') AND status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
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

<!-- ✅ FONT AWESOME ICONS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ✅ FONT: JETBRAINS MONO + INTER FALLBACK -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================================================================
   ✅ FONT: JETBRAINS MONO (SAWA NA PAGES) + INTER FALLBACK
   ================================================================ */
.sidebar,
.sidebar *:not(i):not(.fas):not(.far):not(.fab):not(.fa-solid):not(.fa-regular):not(.fa-brands),
.sidebar *::before:not(i),
.sidebar *::after:not(i) {
    font-family: 'JetBrains Mono', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', monospace !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
}

/* ✅ FONT AWESOME ICONS */
.sidebar i,
.sidebar i::before,
.sidebar .fas,
.sidebar .fas::before,
.sidebar .far,
.sidebar .far::before,
.sidebar .fab,
.sidebar .fab::before {
    font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "Font Awesome 5 Free" !important;
    font-weight: 900 !important;
    -webkit-font-smoothing: antialiased;
    display: inline-block;
    font-style: normal;
    font-variant: normal;
    text-rendering: auto;
    line-height: 1;
}

.sidebar .far, .sidebar .far::before { font-weight: 400 !important; }
.sidebar .fab, .sidebar .fab::before {
    font-family: "Font Awesome 6 Brands" !important;
    font-weight: 400 !important;
}

/* ================================================================
   ✅ SIDEBAR - COMPACT SIZE (260px)
   ================================================================ */
.sidebar {
    position: fixed; 
    top: 0; left: 0; bottom: 0;
    width: 260px; /* ✅ Imepunguzwa kutoka 280px */
    
    background: linear-gradient(180deg, #0B5ED7 0%, #0A4CA8 100%);
    
    color: white;
    z-index: 99999; 
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-100%);
    box-shadow: 4px 0 20px rgba(11, 94, 215, 0.25);
    scroll-behavior: smooth;
}

[data-theme="dark"] .sidebar {
    background: linear-gradient(180deg, #0A4CA8 0%, #083A7F 100%);
    box-shadow: 4px 0 30px rgba(0,0,0,0.5);
}

.sidebar.open { transform: translateX(0) !important; }

.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { background: #60A5FA; border-radius: 10px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: #93C5FD; }

/* ================================================================
   BRAND SECTION - COMPACT
   ================================================================ */
.sidebar-brand {
    padding: 14px 14px 12px; /* ✅ Imepunguzwa */
    border-bottom: 1.5px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 5;
    backdrop-filter: blur(10px);
}

.sidebar-brand .logo {
    width: 40px; /* ✅ Imepunguzwa kutoka 48px */
    height: 40px;
    border-radius: 10px;
    object-fit: cover; 
    background: white; 
    padding: 3px;
    border: 1.5px solid rgba(255,255,255,0.25);
    transition: transform 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.sidebar-brand .logo:hover { 
    transform: rotate(-5deg) scale(1.08); 
    box-shadow: 0 6px 16px rgba(96, 165, 250, 0.5);
}

.sidebar-brand .brand-text { 
    color: white; 
    font-weight: 800;
    font-size: 0.88rem; /* ✅ Imepunguzwa */
    line-height: 1.2; 
    letter-spacing: 0.02em;
}

.sidebar-brand .brand-sub { 
    color: #BFDBFE; 
    font-size: 0.65rem; /* ✅ Imepunguzwa */
    font-weight: 600;
    letter-spacing: 0.03em;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}

/* ROLE BADGE */
.audit-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5);
    margin-top: 5px;
}

.audit-role-badge i {
    font-size: 0.65rem;
}

/* ================================================================
   BRANCH SELECTOR - COMPACT
   ================================================================ */
.sidebar-branch-selector {
    padding: 10px 14px; /* ✅ Imepunguzwa */
    border-bottom: 1.5px solid rgba(255,255,255,0.08);
    background: rgba(0,0,0,0.05);
}

.sidebar-branch-selector select {
    width: 100%; 
    padding: 7px 10px; /* ✅ Imepunguzwa */
    border-radius: 8px; 
    border: none;
    background: rgba(255,255,255,0.15);
    color: white; 
    font-size: 0.75rem; /* ✅ Imepunguzwa */
    font-weight: 600;
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
    background-color: rgba(255,255,255,0.25); 
}

.sidebar-branch-selector select:focus { 
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.6); 
}

.sidebar-branch-selector select option { 
    background: #0B5ED7; 
    color: white; 
    padding: 8px; 
}

/* ================================================================
   NAVIGATION - COMPACT + GREEN HOVER
   ================================================================ */
.sidebar-nav { padding: 8px 8px 20px; } /* ✅ Imepunguzwa */

.sidebar-nav .nav-label {
    font-size: 0.55rem; /* ✅ Imepunguzwa */
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #BFDBFE;
    padding: 8px 10px 4px; /* ✅ Imepunguzwa */
    margin: 8px 0 3px;
    font-weight: 800;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 5px;
}

.sidebar-nav .nav-label:first-of-type { margin-top: 0; }

.sidebar-nav .nav-label .label-icon { 
    font-size: 0.65rem;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3));
}

/* ================================================================
   ✅ SIDEBAR LINK - COMPACT + GREEN HOVER
   ================================================================ */
.sidebar-link {
    display: flex; 
    align-items: center; 
    gap: 10px; /* ✅ Imepunguzwa */
    padding: 8px 12px; /* ✅ Imepunguzwa */
    border-radius: 8px;
    color: #DBEAFE; 
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.8rem; /* ✅ Imepunguzwa */
    font-weight: 600;
    margin: 1px 0;
    background: transparent;
    cursor: pointer; 
    border: none;
    width: 100%; 
    text-align: left;
    position: relative;
    letter-spacing: 0.01em;
}

/* ✅ GREEN HOVER */
.sidebar-link:hover {
    background: linear-gradient(90deg, rgba(5, 150, 105, 0.35), rgba(16, 185, 129, 0.2));
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    transform: translateX(4px);
    border-left: 3px solid #10B981;
}

/* ACTIVE - kama blue inabaki */
.sidebar-link.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4);
}

.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: 0; top: 15%; bottom: 15%;
    width: 3px; 
    background: #BFDBFE;
    border-radius: 0 3px 3px 0;
    box-shadow: 0 0 12px rgba(191, 219, 254, 0.8);
}

/* ================================================================
   ✅ ICONS - NZURI ZAIDI + COLORED
   ================================================================ */
.sidebar-link i { 
    width: 22px; /* ✅ Imepunguzwa */
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem; /* ✅ Imepunguzwa */
    flex-shrink: 0; 
    opacity: 0.95;
    transition: all 0.3s ease;
    border-radius: 5px;
    
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
}

.sidebar-link:hover i,
.sidebar-link.active i { 
    opacity: 1; 
    transform: scale(1.15);
}

/* ✅ Colored icons kwa kila link */
.sidebar-link .fa-arrow-left { color: #FCD34D; }
.sidebar-link .fa-shield-alt { color: #93C5FD; }
.sidebar-link .fa-chart-line { color: #34D399; }
.sidebar-link .fa-pills { color: #C4B5FD; }
.sidebar-link .fa-user-injured { color: #FBBF24; }
.sidebar-link .fa-users { color: #93C5FD; }
.sidebar-link .fa-clipboard-list { color: #F9A8D4; }
.sidebar-link .fa-user-circle { color: #7DD3FC; }
.sidebar-link .fa-sign-out-alt { color: #FCA5A5; }

/* ✅ Hover: Icons zinakuwa GREEN */
.sidebar-link:hover i {
    color: #34D399 !important;
    filter: drop-shadow(0 2px 6px rgba(52, 211, 153, 0.6));
}

.sidebar-link.active i {
    color: white !important;
}

.sidebar-link .link-text {
    flex: 1; 
    white-space: nowrap;
    overflow: hidden; 
    text-overflow: ellipsis;
}

/* ================================================================
   BADGES - COMPACT
   ================================================================ */
.sidebar-link .badge {
    margin-left: auto;
    background: #3B82F6 !important;
    padding: 2px 9px; /* ✅ Imepunguzwa */
    border-radius: 20px;
    font-size: 0.62rem; /* ✅ Imepunguzwa */
    font-weight: 800;
    color: #FFFFFF !important;
    transition: all 0.3s ease;
    flex-shrink: 0;
    min-width: 24px;
    text-align: center;
    border: 1.5px solid rgba(255,255,255,0.35);
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.5);
    line-height: 1.4;
}

.sidebar-link .badge.badge-new {
    background: #10B981 !important;
    color: #FFFFFF !important;
    border-color: rgba(255,255,255,0.45) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.7);
    animation: pulse-new 2s infinite;
    font-size: 0.55rem;
    padding: 2px 8px;
}

.sidebar-link .badge.badge-warning {
    background: #F59E0B !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.6);
    animation: pulse-badge 2s infinite;
}

.sidebar-link .badge.badge-revenue {
    background: linear-gradient(135deg, #10B981, #059669) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
}

@keyframes pulse-new {
    0%, 100% { transform: scale(1); box-shadow: 0 2px 8px rgba(16, 185, 129, 0.7); }
    50% { transform: scale(1.08); box-shadow: 0 3px 14px rgba(16, 185, 129, 1); }
}

@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* ✅ Hover: Badges zinakuwa GREEN */
.sidebar-link:hover .badge {
    background: #059669 !important;
    transform: scale(1.1);
    box-shadow: 0 3px 10px rgba(5, 150, 105, 0.7);
}

/* ================================================================
   LOGOUT LINK - COMPACT + GREEN HOVER INABaki RED
   ================================================================ */
.sidebar-link.logout-link {
    border-top: 1.5px solid rgba(255,255,255,0.1);
    padding-top: 10px;
    margin-top: 6px;
    color: #FCA5A5;
    font-weight: 700;
}

.sidebar-link.logout-link:hover {
    background: linear-gradient(90deg, rgba(220, 38, 38, 0.5), rgba(220, 38, 38, 0.3));
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.5);
    transform: translateX(4px);
    border-left: 3px solid #DC2626;
}

.sidebar-link.logout-link i { 
    opacity: 1; 
    color: #FCA5A5;
}

.sidebar-link.logout-link:hover i {
    color: white !important;
}

/* ================================================================
   BACK TO ADMIN - COMPACT
   ================================================================ */
.sidebar-link.back-admin {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(251, 191, 36, 0.2));
    border: 1px solid rgba(245, 158, 11, 0.4);
    color: #FCD34D;
    font-weight: 800;
    margin-top: 6px;
    padding: 9px 12px;
}

.sidebar-link.back-admin:hover {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.5);
    transform: translateX(4px);
    border-left: 3px solid #F59E0B;
}

.sidebar-link.back-admin i { 
    opacity: 1; 
    color: #FCD34D;
    font-size: 0.9rem;
}

.sidebar-link.back-admin:hover i {
    color: #78350F !important;
    filter: none;
}

/* ================================================================
   STATUS FOOTER - COMPACT
   ================================================================ */
.sidebar-status {
    padding: 9px 14px; /* ✅ Imepunguzwa */
    border-top: 1.5px solid rgba(255,255,255,0.1);
    display: flex; 
    align-items: center; 
    gap: 10px;
    background: rgba(0,0,0,0.15);
    position: sticky; 
    bottom: 0;
    backdrop-filter: blur(10px);
}

.sidebar-status .status-dot {
    width: 8px; /* ✅ Imepunguzwa */
    height: 8px;
    border-radius: 50%;
    display: inline-block; 
    transition: all 0.3s ease;
}

.sidebar-status .status-dot.online {
    background: #34D399;
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.6);
    animation: pulse-dot 1.5s infinite;
}

.sidebar-status .status-dot.offline { 
    background: #94A3B8; 
}

.sidebar-status .status-text {
    font-size: 0.68rem; /* ✅ Imepunguzwa */
    color: #DBEAFE; 
    font-weight: 700;
    letter-spacing: 0.02em;
}

.sidebar-status .update-time {
    font-size: 0.55rem;
    color: #BFDBFE;
    margin-left: auto; 
    display: flex; 
    align-items: center; 
    gap: 5px;
}

.sidebar-live-indicator {
    display: inline-flex; 
    align-items: center; 
    gap: 4px;
    font-size: 0.55rem; 
    color: #93C5FD;
    margin-left: auto; 
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-live-indicator .dot {
    width: 6px; 
    height: 6px;
    border-radius: 50%;
    background: #93C5FD; 
    animation: pulse-dot 1.5s infinite;
    display: inline-block;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.8); }
}

/* ================================================================
   OVERLAY
   ================================================================ */
#sidebarOverlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 99998;
    display: none;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    transition: opacity 0.3s ease;
}

#sidebarOverlay.active { display: block !important; }

/* ================================================================
   CLOSE BUTTON
   ================================================================ */
.sidebar-close-btn {
    display: none;
    position: absolute;
    top: 14px;
    right: 14px;
    width: 36px; /* ✅ Imepunguzwa */
    height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    cursor: pointer;
    font-size: 1rem;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 10;
}

.sidebar-close-btn:hover {
    background: rgba(16, 185, 129, 0.6);
    border-color: #10B981;
    transform: rotate(90deg);
}

.sidebar-close-btn i {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
}

@media (max-width: 1024px) {
    .sidebar-close-btn {
        display: flex;
    }
}

/* ================================================================
   FLOATING TOGGLE BUTTON
   ================================================================ */
.floating-sidebar-toggle {
    position: fixed;
    top: 76px;
    left: 16px;
    z-index: 99997;
    width: 46px; /* ✅ Imepunguzwa */
    height: 46px;
    border-radius: 12px;
    
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    
    color: white;
    border: 2px solid rgba(96, 165, 250, 0.5);
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.floating-sidebar-toggle i {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
}

/* ✅ GREEN HOVER */
.floating-sidebar-toggle:hover {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 8px 28px rgba(16, 185, 129, 0.6);
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    border-color: #34D399;
}

.floating-sidebar-toggle:active {
    transform: scale(0.95);
}

.floating-sidebar-toggle .badge-dot {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #10B981;
    border: 2px solid #0B5ED7;
    animation: pulse-dot 1.5s infinite;
}

@media (max-width: 1024px) {
    .floating-sidebar-toggle { display: flex; }
}

@media (min-width: 1025px) {
    .floating-sidebar-toggle { display: none !important; }
}

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (min-width: 1025px) {
    .sidebar {
        transform: translateX(0) !important;
        z-index: 50;
        box-shadow: 4px 0 20px rgba(11, 94, 215, 0.15);
    }
    #sidebarOverlay { display: none !important; }
    .sidebar-close-btn { display: none !important; }
    .floating-sidebar-toggle { display: none !important; }
}

@media (max-width: 1024px) {
    .sidebar {
        width: 270px; /* ✅ Imepunguzwa */
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
    
    .sidebar.open, .sidebar.open * { pointer-events: auto !important; }
    .sidebar-link { pointer-events: auto !important; cursor: pointer !important; }
    
    .sidebar-brand { padding: 12px 12px 10px; }
    .sidebar-brand .logo { width: 36px; height: 36px; }
    .sidebar-brand .brand-text { font-size: 0.82rem; }
    .sidebar-link { padding: 7px 10px; font-size: 0.75rem; gap: 8px; }
    .sidebar-link i { width: 20px; font-size: 0.8rem; }
    .sidebar-link .badge { font-size: 0.58rem; padding: 2px 8px; }
    .sidebar-nav .nav-label { font-size: 0.5rem; }
    .sidebar-status { padding: 8px 12px; }
}

@media (max-width: 768px) {
    .sidebar { width: 290px; border-radius: 0 16px 16px 0; }
    .sidebar-link { padding: 6px 9px; font-size: 0.72rem; gap: 8px; }
    .sidebar-link i { width: 18px; font-size: 0.75rem; }
    .floating-sidebar-toggle {
        top: 70px;
        left: 12px;
        width: 44px;
        height: 44px;
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%; max-width: 310px;
        border-radius: 0 20px 20px 0;
    }
    .sidebar-link { padding: 6px 9px; font-size: 0.7rem; gap: 7px; }
    .sidebar-link i { width: 17px; font-size: 0.72rem; }
    .sidebar-link .badge { font-size: 0.55rem; padding: 1px 7px; min-width: 22px; }
    .sidebar-nav .nav-label { font-size: 0.48rem; padding: 0 8px; }
    .floating-sidebar-toggle {
        top: 66px;
        left: 10px;
        width: 42px;
        height: 42px;
        font-size: 1.05rem;
    }
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
    .floating-sidebar-toggle { display: none !important; }
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 10px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Admin Audit Sidebar">
    
    <!-- CLOSE BUTTON -->
    <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
    </button>
    
    <!-- BRAND -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B5ED7%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
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
        
        <!-- MAIN MENU -->
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
        
        <!-- REPORTS -->
        <div class="nav-label">
            <span class="label-icon">📊</span> Reports
        </div>
        
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
        </a>
        
        <!-- PERFORMANCE -->
        <div class="nav-label">
            <span class="label-icon">👥</span> Performance
        </div>
        
        <a href="/dispensary_system/frontend/pages/admin/audit/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') || isAdminAuditPage(['employee_details.php', 'doctor_performance.php', 'reception_performance.php']) ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span class="link-text">Employees</span>
            <span class="badge" id="badgeEmployees"><?= $total_employees ?></span>
        </a>
        
        <!-- AUDIT -->
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
        
        <!-- ACCOUNT -->
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

<!-- FLOATING TOGGLE BUTTON -->
<button class="floating-sidebar-toggle" id="floatingSidebarToggle" aria-label="Toggle Sidebar">
    <i class="fas fa-bars"></i>
    <span class="badge-dot"></span>
</button>

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
// SIDEBAR TOGGLE - INAFANYA KAZI 100%
// ================================================================
(function() {
    'use strict';
    
    var sidebar, overlay, closeBtn, floatingBtn, headerToggle;
    var isInitialized = false;
    
    function initSidebar() {
        if (isInitialized) return;
        
        sidebar = document.getElementById('sidebar');
        overlay = document.getElementById('sidebarOverlay');
        closeBtn = document.getElementById('sidebarCloseBtn');
        floatingBtn = document.getElementById('floatingSidebarToggle');
        headerToggle = document.getElementById('sidebarToggle');
        
        if (!sidebar) {
            console.warn('❌ Sidebar not found');
            return;
        }
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            document.body.appendChild(overlay);
        }
        
        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            overlay.style.display = 'block';
            document.body.classList.add('sidebar-open');
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.height = '100%';
            sidebar.style.zIndex = '99999';
            overlay.style.zIndex = '99998';
            
            if (floatingBtn) {
                var icon = floatingBtn.querySelector('i');
                if (icon) icon.className = 'fas fa-times';
            }
            
            if (headerToggle) {
                var hIcon = headerToggle.querySelector('i');
                if (hIcon) hIcon.className = 'fas fa-times';
            }
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            overlay.style.display = 'none';
            document.body.classList.remove('sidebar-open');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
            sidebar.style.zIndex = '';
            overlay.style.zIndex = '';
            
            if (floatingBtn) {
                var icon = floatingBtn.querySelector('i');
                if (icon) icon.className = 'fas fa-bars';
            }
            
            if (headerToggle) {
                var hIcon = headerToggle.querySelector('i');
                if (hIcon) hIcon.className = 'fas fa-bars';
            }
        }
        
        function toggleSidebar() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        window.toggleSidebar = toggleSidebar;
        window.openSidebar = openSidebar;
        window.closeSidebar = closeSidebar;
        
        // FLOATING BUTTON
        if (floatingBtn) {
            var newFloatBtn = floatingBtn.cloneNode(true);
            floatingBtn.parentNode.replaceChild(newFloatBtn, floatingBtn);
            floatingBtn = newFloatBtn;
            
            floatingBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            floatingBtn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            }, { passive: false });
        }
        
        // HEADER TOGGLE
        if (headerToggle) {
            var newHeaderToggle = headerToggle.cloneNode(true);
            headerToggle.parentNode.replaceChild(newHeaderToggle, headerToggle);
            headerToggle = newHeaderToggle;
            
            headerToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // CLOSE BUTTON
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });
            
            closeBtn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            }, { passive: false });
        }
        
        // OVERLAY CLICK
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeSidebar();
        });
        
        // ESC KEY
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        // RESIZE
        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            }, 200);
        });
        
        // CLOSE ON LINK CLICK (mobile)
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('open')) {
                    setTimeout(closeSidebar, 200);
                }
            });
        });
        
        // SWIPE TO CLOSE
        var touchStartX = 0;
        var touchEndX = 0;
        
        sidebar.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        sidebar.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        function handleSwipe() {
            var swipeDistance = touchEndX - touchStartX;
            if (swipeDistance < -80 && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        }
        
        isInitialized = true;
        console.log('%c✅ Sidebar V6 initialized', 'color:#10B981; font-weight:bold;');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        setTimeout(initSidebar, 50);
    }
    
    window.addEventListener('load', function() {
        if (!isInitialized) initSidebar();
    });
    
    window.initAdminAuditSidebar = initSidebar;
})();

console.log('%c👑 Braick - Admin Audit Sidebar V6', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ SIZE: Imepunguzwa (260px)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ HOVER: GREEN (#10B981)', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c✅ FONT: JetBrains Mono', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ ICONS: NZURI ZAIDI', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>