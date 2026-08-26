<?php
// ================================================================
// FILE: frontend/components/pharmacy_sidebar.php
// PHARMACY - SHARED SIDEBAR (USING API FOR REAL-TIME UPDATES)
// ✅ TOGGLE BUTTON KWENYE HEADER (SI FLOATING)
// ✅ BLUE COLOR THEME
// ✅ JINA NA LOGO KUTOKA system_settings
// ✅ FONTS ZIMEONGEZWA SIZE
// ✅ PROFILE SECTION IMETOLEWA
// ✅ API INTEGRATION - get_pharmacy_sidebar_stats.php
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
// CHECK USER ACCESS
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
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
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy';
$user_role = $_SESSION['role'] ?? 'pharmacy';
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
// GET SYSTEM SETTINGS - JINA NA LOGO LA DISPENSARY
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
// SITE LOGO PATH
// ================================================================
if (!empty($site_logo)) {
    $site_logo_path = '/dispensary_system/frontend/assets/uploads/settings/' . $site_logo;
} else {
    $site_logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
}

// ================================================================
// GET INITIAL STATISTICS FOR BADGES
// ================================================================
$pending_prescriptions = 0;
$low_stock_count = 0;
$expired_count = 0;
$today_sales = 0;
$today_otc = 0;

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
        ");
        $stmt->execute([$user_branch_id]);
        $low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? 
            AND expiry_date IS NOT NULL 
            AND expiry_date < CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $expired_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions 
            WHERE branch_id = ? AND status = 'dispensed' AND DATE(dispensed_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $today_sales = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM otc_sales 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $today_otc = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Pharmacy sidebar initial stats error: " . $e->getMessage());
    }
}

// ================================================================
// GENERATE INITIAL HASH
// ================================================================
$initial_hash = md5(json_encode([
    'pending_prescriptions' => $pending_prescriptions,
    'low_stock' => $low_stock_count,
    'expired' => $expired_count,
    'today_prescriptions' => $today_sales,
    'today_otc' => $today_otc
]));

// ================================================================
// DETECT CURRENT PAGE
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// PASS DATA TO JAVASCRIPT
// ================================================================
$initial_data = [
    'pending_prescriptions' => $pending_prescriptions,
    'low_stock' => $low_stock_count,
    'expired' => $expired_count,
    'today_prescriptions' => $today_sales,
    'today_otc' => $today_otc,
    'branch_id' => $user_branch_id,
    'branch_name' => $user_branch_name,
    'user_name' => $user_full_name
];
?>

<style>
    /* ================================================================
       SIDEBAR - BLUE THEME (BIGGER FONTS)
       ================================================================ */
    
    .sidebar-modern {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 270px;
        background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
        color: white;
        z-index: 9999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(-100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        padding-bottom: 16px;
        font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
    }
    
    [data-theme="dark"] .sidebar-modern {
        background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar-modern.open {
        transform: translateX(0) !important;
    }
    
    .sidebar-modern::-webkit-scrollbar { width: 4px; }
    .sidebar-modern::-webkit-scrollbar-track { background: transparent; }
    .sidebar-modern::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
    .sidebar-modern::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.35); }
    
    /* ================================================================
       OVERLAY
       ================================================================ */
    #sidebarOverlayModern {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9998;
        display: none;
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
        transition: opacity 0.3s ease;
    }
    
    #sidebarOverlayModern.active {
        display: block !important;
    }
    
    /* ================================================================
       SIDEBAR BRAND - BLUE THEME (BIGGER FONTS)
       ================================================================ */
    .sidebar-brand-modern {
        padding: 18px 18px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.06);
        background: rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 5;
        backdrop-filter: blur(10px);
    }
    
    .sidebar-brand-modern .logo-modern {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        object-fit: cover;
        background: white;
        padding: 4px;
        border: 1px solid rgba(255,255,255,0.08);
        transition: transform 0.3s ease;
    }
    
    .sidebar-brand-modern .logo-modern:hover {
        transform: rotate(-5deg) scale(1.05);
    }
    
    .sidebar-brand-modern .brand-text-modern {
        color: white;
        font-weight: 700;
        font-size: 1rem;
        line-height: 1.2;
        letter-spacing: -0.01em;
    }
    
    .sidebar-brand-modern .brand-sub-modern {
        color: rgba(255,255,255,0.5);
        font-size: 0.6rem;
        font-weight: 500;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    
    .sidebar-close-btn-modern {
        display: none;
        background: rgba(255,255,255,0.06);
        border: none;
        color: rgba(255,255,255,0.6);
        font-size: 1.1rem;
        cursor: pointer;
        padding: 4px 10px;
        border-radius: 6px;
        transition: all 0.3s ease;
        margin-left: auto;
    }
    
    .sidebar-close-btn-modern:hover {
        background: rgba(255,255,255,0.12);
        color: white;
        transform: rotate(90deg);
    }
    
    @media (max-width: 1024px) {
        .sidebar-close-btn-modern { display: block; }
    }
    
    /* ================================================================
       PROFILE SECTION IMETOLEWA - HAKUNA USER PROFILE
       ================================================================ */
    
    /* ================================================================
       NAVIGATION (BIGGER FONTS)
       ================================================================ */
    .sidebar-nav-modern {
        padding: 8px 10px 16px;
    }
    
    .sidebar-nav-modern .nav-label-modern {
        font-size: 0.55rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgba(255,255,255,0.35);
        padding: 8px 12px 4px;
        font-weight: 700;
    }
    
    .sidebar-nav-modern .nav-label-modern:first-of-type {
        padding-top: 6px;
    }
    
    /* ================================================================
       SIDEBAR LINKS - BLUE THEME (BIGGER FONTS)
       ================================================================ */
    .sidebar-link-modern {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        color: rgba(255,255,255,0.65);
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 0.78rem;
        font-weight: 500;
        margin: 2px 0;
        background: transparent;
        cursor: pointer;
        border: none;
        width: 100%;
        text-align: left;
        position: relative;
        letter-spacing: 0.01em;
    }
    
    .sidebar-link-modern:hover {
        background: rgba(255,255,255,0.08);
        color: white;
        transform: translateX(3px);
    }
    
    .sidebar-link-modern.active {
        background: rgba(255,255,255,0.12);
        color: white;
        box-shadow: inset 3px 0 0 #0AA84F;
    }
    
    .sidebar-link-modern i {
        width: 18px;
        text-align: center;
        font-size: 0.85rem;
        flex-shrink: 0;
        color: rgba(255,255,255,0.4);
    }
    
    .sidebar-link-modern.active i {
        color: white;
    }
    
    .sidebar-link-modern:hover i {
        color: rgba(255,255,255,0.7);
    }
    
    /* ================================================================
       BADGES (BIGGER FONTS)
       ================================================================ */
    .sidebar-link-modern .badge-modern {
        margin-left: auto;
        background: rgba(255,255,255,0.06);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        color: rgba(255,255,255,0.5);
        transition: all 0.3s ease;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
    }
    
    .sidebar-link-modern .badge-modern.danger {
        background: rgba(239, 68, 68, 0.2);
        color: #F87171;
        animation: pulse-badge-modern 2s infinite;
    }
    
    .sidebar-link-modern .badge-modern.green {
        background: rgba(52, 211, 153, 0.15);
        color: #34D399;
    }
    
    .sidebar-link-modern .badge-modern.orange {
        background: rgba(251, 191, 36, 0.15);
        color: #FBBF24;
    }
    
    .sidebar-link-modern .badge-modern.red {
        background: rgba(239, 68, 68, 0.25);
        color: #F87171;
        animation: pulse-badge-modern 2s infinite;
    }
    
    .sidebar-link-modern .badge-modern.blue {
        background: rgba(96, 165, 250, 0.15);
        color: #60A5FA;
    }
    
    .sidebar-link-modern:hover .badge-modern {
        background: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.7);
    }
    
    .sidebar-link-modern.active .badge-modern {
        background: rgba(255,255,255,0.1);
        color: white;
    }
    
    .sidebar-link-modern.active .badge-modern.danger {
        background: rgba(239, 68, 68, 0.3);
        color: #FCA5A5;
    }
    
    /* ================================================================
       BADGE UPDATE ANIMATION
       ================================================================ */
    .badge-update-modern {
        animation: badgePop-modern 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    @keyframes badgePop-modern {
        0% { transform: scale(0.3); opacity: 0; }
        60% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ================================================================
       DATA CHANGED FLASH
       ================================================================ */
    .sidebar-data-flash-modern {
        animation: flashBlue 0.6s ease;
    }
    @keyframes flashBlue {
        0% { background: rgba(52, 211, 153, 0.15); }
        50% { background: rgba(52, 211, 153, 0.03); }
        100% { background: transparent; }
    }
    
    /* ================================================================
       LOGOUT LINK
       ================================================================ */
    .sidebar-link-modern.logout-link-modern {
        border-top: 1px solid rgba(255,255,255,0.05);
        padding-top: 10px;
        margin-top: 6px;
        color: rgba(252, 165, 165, 0.5);
        font-size: 0.78rem;
    }
    
    .sidebar-link-modern.logout-link-modern:hover {
        background: rgba(220, 38, 38, 0.15);
        color: #F87171;
        box-shadow: none;
    }
    
    .sidebar-link-modern.logout-link-modern:hover i {
        color: #F87171;
    }
    
    /* ================================================================
       SIDEBAR STATUS FOOTER
       ================================================================ */
    .sidebar-status-modern {
        padding: 8px 18px;
        border-top: 1px solid rgba(255,255,255,0.04);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.1);
        position: sticky;
        bottom: 0;
        backdrop-filter: blur(10px);
    }
    
    .sidebar-status-modern .status-dot-modern {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .sidebar-status-modern .status-dot-modern.online {
        background: #34D399;
        animation: pulse-dot-modern 1.5s infinite;
        box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
    }
    
    .sidebar-status-modern .status-dot-modern.offline {
        background: #94A3B8;
    }
    
    .sidebar-status-modern .status-text-modern {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.4);
        font-weight: 500;
    }
    
    .sidebar-status-modern .status-time-modern {
        font-size: 0.55rem;
        color: rgba(255,255,255,0.25);
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .sidebar-status-modern .status-time-modern .live-dot-modern {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #34D399;
        display: inline-block;
        animation: pulse-dot-modern 1.5s infinite;
    }
    
    @keyframes pulse-dot-modern {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
    }
    
    /* ================================================================
       RESPONSIVE BREAKPOINTS
       ================================================================ */
    
    @media (min-width: 1025px) {
        .sidebar-modern {
            transform: translateX(0) !important;
            z-index: 50;
            box-shadow: 2px 0 16px rgba(0,0,0,0.08);
        }
        #sidebarOverlayModern { display: none !important; }
        .sidebar-close-btn-modern { display: none !important; }
        .sidebar-toggle-float-modern { display: none !important; }
    }
    
    @media (max-width: 1024px) {
        .sidebar-modern {
            width: 290px;
            transform: translateX(-100%);
            z-index: 9999;
            border-radius: 0 12px 12px 0;
            box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        }
        .sidebar-modern.open { transform: translateX(0) !important; }
        #sidebarOverlayModern { display: none; z-index: 9998; }
        #sidebarOverlayModern.active { display: block !important; }
        .sidebar-brand-modern { padding: 14px 16px 12px; }
        .sidebar-brand-modern .logo-modern { width: 36px; height: 36px; }
        .sidebar-brand-modern .brand-text-modern { font-size: 0.9rem; }
        .sidebar-link-modern { padding: 6px 12px; font-size: 0.7rem; gap: 8px; }
        .sidebar-link-modern i { width: 16px; font-size: 0.75rem; }
        .sidebar-link-modern .badge-modern { font-size: 0.55rem; padding: 2px 7px; min-width: 18px; }
        .sidebar-nav-modern .nav-label-modern { font-size: 0.5rem; padding: 6px 12px 3px; }
        .sidebar-status-modern { padding: 6px 16px; }
        .sidebar-status-modern .status-text-modern { font-size: 0.55rem; }
        .sidebar-status-modern .status-time-modern { font-size: 0.5rem; }
        .sidebar-toggle-float-modern { display: none !important; }
    }
    
    @media (max-width: 768px) {
        .sidebar-modern { width: 310px; border-radius: 0 16px 16px 0; }
        .sidebar-brand-modern { padding: 12px 14px 10px; }
        .sidebar-brand-modern .logo-modern { width: 32px; height: 32px; }
        .sidebar-brand-modern .brand-text-modern { font-size: 0.85rem; }
        .sidebar-link-modern { padding: 5px 12px; font-size: 0.65rem; gap: 7px; }
        .sidebar-link-modern i { width: 15px; font-size: 0.7rem; }
        .sidebar-link-modern .badge-modern { font-size: 0.5rem; padding: 1px 6px; min-width: 16px; }
        .sidebar-nav-modern .nav-label-modern { font-size: 0.45rem; padding: 5px 12px 2px; }
        .sidebar-status-modern { padding: 5px 14px; }
        .sidebar-status-modern .status-text-modern { font-size: 0.5rem; }
        .sidebar-status-modern .status-time-modern { font-size: 0.45rem; }
        .sidebar-toggle-float-modern { display: none !important; }
    }
    
    @media (max-width: 480px) {
        .sidebar-modern {
            width: 100%;
            max-width: 320px;
            border-radius: 0 20px 20px 0;
        }
        .sidebar-brand-modern { padding: 10px 12px 8px; }
        .sidebar-brand-modern .logo-modern { width: 28px; height: 28px; }
        .sidebar-brand-modern .brand-text-modern { font-size: 0.75rem; }
        .sidebar-brand-modern .brand-sub-modern { font-size: 0.5rem; }
        .sidebar-link-modern { padding: 4px 10px; font-size: 0.6rem; gap: 6px; }
        .sidebar-link-modern i { width: 14px; font-size: 0.65rem; }
        .sidebar-link-modern .badge-modern { font-size: 0.45rem; padding: 1px 5px; min-width: 14px; }
        .sidebar-nav-modern .nav-label-modern { font-size: 0.4rem; padding: 4px 10px 2px; }
        .sidebar-status-modern { padding: 4px 12px; }
        .sidebar-status-modern .status-text-modern { font-size: 0.45rem; }
        .sidebar-status-modern .status-time-modern { font-size: 0.4rem; }
        .sidebar-status-modern .status-dot-modern { width: 5px; height: 5px; }
        .sidebar-toggle-float-modern { display: none !important; }
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
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
<div id="sidebarOverlayModern"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar-modern" id="sidebarModern">
    
    <!-- ================================================================ -->
    <!-- BRAND / HEADER - JINA NA LOGO KUTOKA SYSTEM SETTINGS -->
    <!-- ================================================================ -->
    <div class="sidebar-brand-modern">
        <div class="flex items-center gap-3">
            <img src="<?= $site_logo_path ?>" 
                 alt="<?= htmlspecialchars($site_name) ?>" 
                 class="logo-modern"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2234%22 height=%2234%22%3E%3Crect width=%2234%22 height=%2234%22 fill=%22%230B4EA8%22 rx=%228%22/%3E%3Ctext x=%2217%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text-modern" id="sidebarSiteName"><?= htmlspecialchars($site_name) ?></p>
                <p class="brand-sub-modern">💊 Pharmacy Panel</p>
            </div>
            <button class="sidebar-close-btn-modern" id="sidebarCloseBtnModern" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- PROFILE SECTION IMETOLEWA KABISA -->
    <!-- ================================================================ -->
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav-modern">
        
        <!-- Pharmacy -->
        <div class="nav-label-modern">📋 Pharmacy</div>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/dashboard.php" class="sidebar-link-modern <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- Prescription Sales -->
        <div class="nav-label-modern">💊 Prescription Sales</div>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/pending_prescriptions.php" class="sidebar-link-modern <?= isActive('pending_prescriptions.php') ?>">
            <i class="fas fa-prescription"></i> Prescriptions
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge-modern danger" id="sidebarPendingBadgeModern"><?= $pending_prescriptions ?></span>
            <?php else: ?>
                <span class="badge-modern" id="sidebarPendingBadgeModern">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/prescription_history.php" class="sidebar-link-modern <?= isActive('prescription_history.php') ?>">
            <i class="fas fa-history"></i> Prescription History
            <span class="badge-modern green" id="sidebarTodayPrescriptionsModern"><?= $today_sales ?></span>
        </a>
        
        <!-- OTC Sales -->
        <div class="nav-label-modern">🛒 OTC Sales</div>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/new_otc_sale.php" class="sidebar-link-modern <?= isActive('new_otc_sale.php') ?>">
            <i class="fas fa-plus-circle"></i> New OTC Sale
        </a>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/otc_history.php" class="sidebar-link-modern <?= isActive('otc_history.php') ?>">
            <i class="fas fa-shopping-cart"></i> OTC History
            <span class="badge-modern green" id="sidebarTodayOtcModern"><?= $today_otc ?></span>
        </a>
        
        <!-- Medicines -->
        <div class="nav-label-modern">📦 Medicines</div>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/inventory.php" class="sidebar-link-modern <?= isActive('inventory.php') ?>">
            <i class="fas fa-warehouse"></i> Inventory
        </a>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/low_stock.php" class="sidebar-link-modern <?= isActive('low_stock.php') ?>">
            <i class="fas fa-exclamation-triangle"></i> Low Stock
            <?php if ($low_stock_count > 0): ?>
                <span class="badge-modern danger" id="sidebarLowStockBadgeModern"><?= $low_stock_count ?></span>
            <?php else: ?>
                <span class="badge-modern" id="sidebarLowStockBadgeModern">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/expired.php" class="sidebar-link-modern <?= isActive('expired.php') ?>">
            <i class="fas fa-skull"></i> Expired Stock
            <?php if ($expired_count > 0): ?>
                <span class="badge-modern red" id="sidebarExpiredBadgeModern"><?= $expired_count ?></span>
            <?php else: ?>
                <span class="badge-modern" id="sidebarExpiredBadgeModern">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Account -->
        <div class="nav-label-modern">👤 Account</div>
        
        <a href="/dispensary_system/frontend/pages/pharmacy/profile.php" class="sidebar-link-modern <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link-modern logout-link-modern">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- ================================================================ -->
    <!-- SIDEBAR STATUS -->
    <!-- ================================================================ -->
    <div class="sidebar-status-modern">
        <span class="status-dot-modern online" id="sidebarStatusDotModern"></span>
        <span class="status-text-modern" id="sidebarStatusTextModern">Online</span>
        <span class="status-time-modern" id="sidebarStatusTimeModern">
            <span class="live-dot-modern"></span>
            <span id="sidebarLiveTimeModern"><?= date('H:i:s') ?></span>
        </span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT - WITH API INTEGRATION -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var SIDEBAR_CONFIG = {
        API_URL: '/dispensary_system/backend/api/get_pharmacy_sidebar_stats.php',
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
    // SIDEBAR TOGGLE - INAITWA KUTOKA HEADER
    // ================================================================
    (function() {
        function initSidebar() {
            var sidebar = document.getElementById('sidebarModern');
            var closeBtn = document.getElementById('sidebarCloseBtnModern');
            var overlay = document.getElementById('sidebarOverlayModern');
            
            if (!sidebar) return;
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlayModern';
                document.body.appendChild(overlay);
            }
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                var headerBtn = document.getElementById('sidebarToggleBtn');
                if (headerBtn) {
                    headerBtn.innerHTML = '<i class="fas fa-times"></i><span class="toggle-label">CLOSE</span>';
                }
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                var headerBtn = document.getElementById('sidebarToggleBtn');
                if (headerBtn) {
                    headerBtn.innerHTML = '<i class="fas fa-bars"></i><span class="toggle-label">MENU</span>';
                }
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            window.togglePharmacySidebar = toggleSidebar;
            window.openPharmacySidebar = openSidebar;
            window.closePharmacySidebar = closeSidebar;
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
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
            
            console.log('✅ Pharmacy Sidebar toggle ready');
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            initSidebar();
        }
    })();

    // ================================================================
    // UPDATE BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        if (!data) return false;
        
        var hasChanges = false;
        
        // 1. Pending Prescriptions
        var pendingBadge = document.getElementById('sidebarPendingBadgeModern');
        if (pendingBadge && data.pending_prescriptions !== undefined) {
            var oldVal = pendingBadge.textContent;
            var newVal = data.pending_prescriptions;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                pendingBadge.textContent = newVal;
                pendingBadge.className = parseInt(newVal) > 0 ? 'badge-modern danger' : 'badge-modern';
                pendingBadge.classList.remove('badge-update-modern');
                void pendingBadge.offsetWidth;
                pendingBadge.classList.add('badge-update-modern');
            }
        }
        
        // 2. Low Stock
        var lowStockBadge = document.getElementById('sidebarLowStockBadgeModern');
        if (lowStockBadge && data.low_stock !== undefined) {
            var oldVal = lowStockBadge.textContent;
            var newVal = data.low_stock;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                lowStockBadge.textContent = newVal;
                lowStockBadge.className = parseInt(newVal) > 0 ? 'badge-modern danger' : 'badge-modern';
                lowStockBadge.classList.remove('badge-update-modern');
                void lowStockBadge.offsetWidth;
                lowStockBadge.classList.add('badge-update-modern');
            }
        }
        
        // 3. Expired
        var expiredBadge = document.getElementById('sidebarExpiredBadgeModern');
        if (expiredBadge && data.expired !== undefined) {
            var oldVal = expiredBadge.textContent;
            var newVal = data.expired;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                expiredBadge.textContent = newVal;
                expiredBadge.className = parseInt(newVal) > 0 ? 'badge-modern red' : 'badge-modern';
                expiredBadge.classList.remove('badge-update-modern');
                void expiredBadge.offsetWidth;
                expiredBadge.classList.add('badge-update-modern');
            }
        }
        
        // 4. Today Prescriptions
        var todayPrescBadge = document.getElementById('sidebarTodayPrescriptionsModern');
        if (todayPrescBadge && data.today_prescriptions !== undefined) {
            var oldVal = todayPrescBadge.textContent;
            var newVal = data.today_prescriptions;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                todayPrescBadge.textContent = newVal;
                todayPrescBadge.className = parseInt(newVal) > 0 ? 'badge-modern green' : 'badge-modern';
                todayPrescBadge.classList.remove('badge-update-modern');
                void todayPrescBadge.offsetWidth;
                todayPrescBadge.classList.add('badge-update-modern');
            }
        }
        
        // 5. Today OTC
        var todayOtcBadge = document.getElementById('sidebarTodayOtcModern');
        if (todayOtcBadge && data.today_otc !== undefined) {
            var oldVal = todayOtcBadge.textContent;
            var newVal = data.today_otc;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                todayOtcBadge.textContent = newVal;
                todayOtcBadge.className = parseInt(newVal) > 0 ? 'badge-modern green' : 'badge-modern';
                todayOtcBadge.classList.remove('badge-update-modern');
                void todayOtcBadge.offsetWidth;
                todayOtcBadge.classList.add('badge-update-modern');
            }
        }
        
        // 6. Update timestamp
        var timeEl = document.getElementById('sidebarLiveTimeModern');
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
            var sidebarEl = document.getElementById('sidebarModern');
            if (sidebarEl) {
                sidebarEl.classList.remove('sidebar-data-flash-modern');
                void sidebarEl.offsetWidth;
                sidebarEl.classList.add('sidebar-data-flash-modern');
            }
            sidebarState.changeCount++;
            console.log('📊 Pharmacy sidebar updated: ' + sidebarState.changeCount + ' changes');
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
                    // Update UI with new data
                    updateSidebarBadges(data.data);
                    sidebarState.dataHash = data.hash;
                    sidebarState.hasInitialData = true;
                    
                    // Dispatch custom event
                    var event = new CustomEvent('sidebarDataUpdated', {
                        detail: {
                            data: data.data,
                            summary: data.summary,
                            timestamp: data.timestamp
                        }
                    });
                    document.dispatchEvent(event);
                    
                } else if (data.has_changed === false) {
                    // Just update timestamp
                    var timeEl = document.getElementById('sidebarLiveTimeModern');
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
            } else {
                if (data.message && data.message.includes('Unauthorized')) {
                    window.location.href = '/dispensary_system/frontend/pages/login.php';
                }
            }
        })
        .catch(function(error) {
            sidebarState.isUpdating = false;
            if (forceUpdate) {
                console.warn('Pharmacy sidebar API error:', error.message);
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
        
        console.log('🔄 Pharmacy sidebar auto-update started (check: ' + 
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
        console.log('🔄 Pharmacy sidebar auto-update stopped');
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
    console.log('%c💊 Braick Pharmacy Sidebar (API Integrated)', 
        'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Site: <?= htmlspecialchars($site_name) ?>', 
        'font-size:12px; color:#34D399;');
    console.log('%c📊 Initial Data:', 'font-size:13px; font-weight:bold; color:#D97706;');
    console.log('   Pending: <?= $pending_prescriptions ?>, Low Stock: <?= $low_stock_count ?>');
    console.log('   Expired: <?= $expired_count ?>, Today: <?= $today_sales ?>, OTC: <?= $today_otc ?>');
    console.log('%c⚡ Smart Updates: Every 2s (only if data changed)', 
        'font-size:13px; color:#34D399;');
    console.log('%c🔄 Force refresh: Every 10s (safety net)', 
        'font-size:13px; color:#F59E0B;');
    console.log('%c📡 API Endpoint: ' + SIDEBAR_CONFIG.API_URL, 
        'font-size:12px; color:#94A3B8;');
    console.log('%c💡 Call window.refreshSidebarData() to manually update', 
        'font-size:12px; color:#6EA8FE;');
    console.log('%c📱 Click ☰ in header to open sidebar on mobile', 
        'font-size:12px; color:#34D399;');
</script>