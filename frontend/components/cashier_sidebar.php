<?php
// ================================================================
// FILE: frontend/components/cashier_sidebar.php
// CASHIER - SHARED SIDEBAR (USING API FOR REAL-TIME UPDATES)
// GREEN THEME - WITH API INTEGRATION
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
// CHECK USER ACCESS
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
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
// GET INITIAL DATA FOR BADGES
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$total_paid = 0;
$cancelled_bills = 0;
$total_expenses = 0;
$patients_waiting = 0;

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
        $stmt->execute([$user_branch_id]);
        $partial_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid'");
        $stmt->execute([$user_branch_id]);
        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'cancelled'");
        $stmt->execute([$user_branch_id]);
        $cancelled_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE branch_id = ? AND status = 'paid'");
        $stmt->execute([$user_branch_id]);
        $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
        $stmt->execute([$user_branch_id]);
        $patients_waiting = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Cashier sidebar initial data error: " . $e->getMessage());
    }
}

// ================================================================
// GENERATE INITIAL HASH
// ================================================================
$initial_hash = md5(json_encode([
    'pending_bills' => $pending_bills,
    'partial_payments' => $partial_payments,
    'total_paid' => $total_paid,
    'cancelled_bills' => $cancelled_bills,
    'total_expenses' => $total_expenses,
    'patients_waiting' => $patients_waiting
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
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PASS DATA TO JAVASCRIPT
// ================================================================
$initial_data = [
    'pending_bills' => $pending_bills,
    'partial_payments' => $partial_payments,
    'total_paid' => $total_paid,
    'cancelled_bills' => $cancelled_bills,
    'total_expenses' => $total_expenses,
    'patients_waiting' => $patients_waiting,
    'branch_id' => $user_branch_id,
    'branch_name' => $user_branch_name,
    'user_name' => $user_full_name,
    'user_is_online' => $user_is_online
];
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - GREEN THEME
       ================================================================ */
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        background: linear-gradient(180deg, #065F46 0%, #064E3A 100%);
        color: white;
        z-index: 9999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(-100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        padding-bottom: 20px;
        scroll-behavior: smooth;
    }
    
    [data-theme="dark"] .sidebar {
        background: linear-gradient(180deg, #064E3A 0%, #043A2C 100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: #6EE7B7; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #A7F3D0; }
    
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
        color: #A7F3D0;
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
        padding: 4px 10px;
        border-radius: 8px;
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
       NAVIGATION
       ================================================================ */
    .sidebar-nav {
        padding: 8px 8px 16px;
    }
    .sidebar-nav .nav-label {
        font-size: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #A7F3D0;
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
        color: #D1FAE5;
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
        background: rgba(5, 150, 105, 0.4);
        color: white;
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
    }
    .sidebar-link.active {
        background: rgba(5, 150, 105, 0.5);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 15%;
        bottom: 15%;
        width: 4px;
        background: #059669;
        border-radius: 0 4px 4px 0;
        box-shadow: 0 0 12px rgba(5, 150, 105, 0.5);
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
       BADGES
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
    .sidebar-link .badge.orange {
        background: #D97706;
        border-color: #D97706;
    }
    .sidebar-link .badge.green {
        background: #059669;
        border-color: #059669;
    }
    .sidebar-link .badge.blue {
        background: #0B5ED7;
        border-color: #0B5ED7;
    }
    .sidebar-link .badge.red {
        background: #DC2626;
        border-color: #DC2626;
    }
    .sidebar-link .badge.yellow {
        background: #D97706;
        border-color: #D97706;
    }
    .sidebar-link .badge.purple {
        background: #7C3AED;
        border-color: #7C3AED;
    }
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
    }
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.2);
        color: white;
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
        color: #D1FAE5;
        font-weight: 500;
    }
    .sidebar-status .update-time {
        font-size: 0.5rem;
        color: #A7F3D0;
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
       RESPONSIVE
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
    }
    
    @media (max-width: 768px) {
        .sidebar {
            width: 300px;
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
        }
        .sidebar-link i {
            width: 16px;
            font-size: 0.75rem;
        }
    }
    
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
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
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Cashier Sidebar">
    
    <!-- ================================================================ -->
    <!-- BRAND -->
    <!-- ================================================================ -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%23065F46%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">💰 Cashier Panel</p>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav">
        
        <!-- Cashier Menu -->
        <div class="nav-label"><span class="label-icon">📋</span> Cashier</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i>
            <span class="link-text">Dashboard</span>
        </a>
        
        <!-- Billing -->
        <div class="nav-label"><span class="label-icon">💰</span> Billing</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/pending_bills.php" class="sidebar-link <?= isActive('pending_bills.php') ?>">
            <i class="fas fa-clock"></i>
            <span class="link-text">Pending Bills</span>
            <?php if ($pending_bills > 0): ?>
                <span class="badge orange" id="sidebarPendingBadge"><?= $pending_bills ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPendingBadge">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/cashier/paid_bills.php" class="sidebar-link <?= isActive('paid_bills.php') ?>">
            <i class="fas fa-check-circle"></i>
            <span class="link-text">Paid Bills</span>
            <span class="badge green" id="sidebarPaidBadge"><?= $total_paid ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/cashier/partial_payments.php" class="sidebar-link <?= isActive('partial_payments.php') ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span class="link-text">Partial Payments</span>
            <?php if ($partial_payments > 0): ?>
                <span class="badge blue" id="sidebarPartialBadge"><?= $partial_payments ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPartialBadge">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/cashier/cancelled_bills.php" class="sidebar-link <?= isActive('cancelled_bills.php') ?>">
            <i class="fas fa-times-circle"></i>
            <span class="link-text">Cancelled Bills</span>
            <?php if ($cancelled_bills > 0): ?>
                <span class="badge red" id="sidebarCancelledBadge"><?= $cancelled_bills ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarCancelledBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Payments -->
        <div class="nav-label"><span class="label-icon">💳</span> Payments</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/payment_history.php" class="sidebar-link <?= isActive('payment_history.php') ?>">
            <i class="fas fa-history"></i>
            <span class="link-text">Payment History</span>
        </a>
        
        <!-- Receipts -->
        <div class="nav-label"><span class="label-icon">🧾</span> Receipts</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/receipt_history.php" class="sidebar-link <?= isActive('receipt_history.php') ?>">
            <i class="fas fa-receipt"></i>
            <span class="link-text">Receipt History</span>
        </a>
        
        <!-- Expenses -->
        <div class="nav-label"><span class="label-icon">💰</span> Expenses</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/expenses.php" class="sidebar-link <?= isActive('expenses.php') ?>">
            <i class="fas fa-coins"></i>
            <span class="link-text">Expenses</span>
            <?php if ($total_expenses > 0): ?>
                <span class="badge yellow" id="sidebarExpensesBadge">TSh <?= number_format($total_expenses) ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarExpensesBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Account -->
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
    <!-- ================================================================ -->
    <!-- STATUS FOOTER -->
    <!-- ================================================================ -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
        <span class="update-time" id="sidebarUpdateTime">
            <span class="sidebar-live-indicator">
                <span class="dot"></span> Live
            </span>
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
        API_URL: '/dispensary_system/backend/api/get_cashier_sidebar_stats.php',
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
    // SIDEBAR TOGGLE
    // ================================================================
    (function() {
        function initSidebar() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                document.body.appendChild(overlay);
            }
            
            if (!sidebar) return;
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
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
                        toggleSidebar();
                    });
                }
            }
            
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
        
        // 1. Pending Bills
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge && data.pending_bills !== undefined) {
            var oldVal = pendingBadge.textContent;
            var newVal = data.pending_bills;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                pendingBadge.textContent = newVal;
                pendingBadge.className = parseInt(newVal) > 0 ? 'badge orange' : 'badge';
                pendingBadge.classList.remove('badge-update');
                void pendingBadge.offsetWidth;
                pendingBadge.classList.add('badge-update');
            }
        }
        
        // 2. Paid Bills
        var paidBadge = document.getElementById('sidebarPaidBadge');
        if (paidBadge && data.paid_bills !== undefined) {
            var oldVal = paidBadge.textContent;
            var newVal = data.paid_bills;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                paidBadge.textContent = newVal;
                paidBadge.className = parseInt(newVal) > 0 ? 'badge green' : 'badge';
                paidBadge.classList.remove('badge-update');
                void paidBadge.offsetWidth;
                paidBadge.classList.add('badge-update');
            }
        }
        
        // 3. Partial Payments
        var partialBadge = document.getElementById('sidebarPartialBadge');
        if (partialBadge && data.partial_payments !== undefined) {
            var oldVal = partialBadge.textContent;
            var newVal = data.partial_payments;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                partialBadge.textContent = newVal;
                partialBadge.className = parseInt(newVal) > 0 ? 'badge blue' : 'badge';
                partialBadge.classList.remove('badge-update');
                void partialBadge.offsetWidth;
                partialBadge.classList.add('badge-update');
            }
        }
        
        // 4. Cancelled Bills
        var cancelledBadge = document.getElementById('sidebarCancelledBadge');
        if (cancelledBadge && data.cancelled_bills !== undefined) {
            var oldVal = cancelledBadge.textContent;
            var newVal = data.cancelled_bills;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                cancelledBadge.textContent = newVal;
                cancelledBadge.className = parseInt(newVal) > 0 ? 'badge red' : 'badge';
                cancelledBadge.classList.remove('badge-update');
                void cancelledBadge.offsetWidth;
                cancelledBadge.classList.add('badge-update');
            }
        }
        
        // 5. Expenses
        var expensesBadge = document.getElementById('sidebarExpensesBadge');
        if (expensesBadge && data.total_expenses !== undefined) {
            var oldVal = expensesBadge.textContent;
            var newVal = data.total_expenses > 0 ? 'TSh ' + Number(data.total_expenses).toLocaleString() : '0';
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                expensesBadge.textContent = newVal;
                expensesBadge.className = data.total_expenses > 0 ? 'badge yellow' : 'badge';
                expensesBadge.classList.remove('badge-update');
                void expensesBadge.offsetWidth;
                expensesBadge.classList.add('badge-update');
            }
        }
        
        // 6. Update timestamp
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
    console.log('%c💰 Braick Dispensary - Cashier Sidebar (API Integrated)', 
        'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 
        'font-size:13px; color:#34D399;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 
        'font-size:13px; color:#6EA8FE;');
    console.log('%c📊 Initial Data:', 'font-size:13px; font-weight:bold; color:#D97706;');
    console.log('   Pending: <?= $pending_bills ?>, Partial: <?= $partial_payments ?>');
    console.log('   Paid: <?= $total_paid ?>, Cancelled: <?= $cancelled_bills ?>');
    console.log('   Expenses: TSh <?= number_format($total_expenses) ?>');
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
</script>