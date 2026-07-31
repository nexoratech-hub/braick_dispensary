<?php
// ================================================================
// FILE: frontend/components/cashier_sidebar.php
// CASHIER - SHARED SIDEBAR (GREEN THEME)
// WITH REAL-TIME STATS AUTO-UPDATE (3 SECONDS) - SELF-CONTAINED
// REMOVED: Patients, Invoice History, Receive Payment, Reports
// FULLY RESPONSIVE - WORKS WITH HEADER
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$paid_today = 0;
$total_paid = 0;
$patients_waiting = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // Pending Bills
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Partial Payments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'partial'");
        $stmt->execute([$user_branch_id]);
        $partial_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Paid Today
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM patient_bills 
            WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $paid_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Total Paid Bills (All time)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM patient_bills 
            WHERE branch_id = ? AND status = 'paid'
        ");
        $stmt->execute([$user_branch_id]);
        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Patients Waiting for Payment (pending + partial)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT patient_id) as count 
            FROM patient_bills 
            WHERE branch_id = ? AND status IN ('pending', 'partial')
        ");
        $stmt->execute([$user_branch_id]);
        $patients_waiting = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    } catch (Exception $e) {
        // Keep counts as 0
        error_log("Cashier sidebar stats error: " . $e->getMessage());
    }
}

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
// HANDLE AJAX REQUEST FOR SIDEBAR DATA (SELF-CONTAINED)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_cashier_sidebar_data') {
    header('Content-Type: application/json');
    
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    
    $response = [
        'success' => false,
        'pending_bills' => 0,
        'partial_payments' => 0,
        'paid_today' => 0,
        'total_paid' => 0,
        'patients_waiting' => 0,
        'hash' => ''
    ];
    
    if (isset($db) && $db !== null) {
        try {
            // Pending Bills
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'pending'");
            $stmt->execute([$branch_id]);
            $response['pending_bills'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Partial Payments
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'partial'");
            $stmt->execute([$branch_id]);
            $response['partial_payments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Paid Today
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM patient_bills 
                WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
            ");
            $stmt->execute([$branch_id]);
            $response['paid_today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Total Paid Bills (All time)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM patient_bills 
                WHERE branch_id = ? AND status = 'paid'
            ");
            $stmt->execute([$branch_id]);
            $response['total_paid'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Patients Waiting for Payment
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT patient_id) as count 
                FROM patient_bills 
                WHERE branch_id = ? AND status IN ('pending', 'partial')
            ");
            $stmt->execute([$branch_id]);
            $response['patients_waiting'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $response['success'] = true;
            
            // Create hash to detect changes
            $response['hash'] = md5(
                $response['pending_bills'] . 
                $response['partial_payments'] . 
                $response['paid_today'] . 
                $response['total_paid'] . 
                $response['patients_waiting']
            );
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
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
    
    /* Sidebar Container - CRITICAL: Works with header toggle */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        background: #065F46;
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
        background: #064E3A;
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    /* Sidebar Open State - CRITICAL: !important ensures it works */
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    /* Scrollbar */
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #064E3A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EE7B7; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #A7F3D0; }
    
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
       SIDEBAR BRAND / HEADER
       ================================================================ */
    .sidebar-brand {
        padding: 18px 16px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: #065F46;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    [data-theme="dark"] .sidebar-brand {
        background: #064E3A;
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
        color: #A7F3D0;
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
        color: #A7F3D0;
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
        color: #D1FAE5;
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
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
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
    
    .sidebar-link .badge.orange {
        background: #D97706;
    }
    
    .sidebar-link .badge.green {
        background: #059669;
    }
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
    }
    
    .sidebar-link .badge.purple {
        background: #7C3AED;
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
        background: #065F46;
        position: sticky;
        bottom: 0;
    }
    
    [data-theme="dark"] .sidebar-status {
        background: #064E3A;
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
        color: #D1FAE5;
    }
    
    .sidebar-status .status-time {
        font-size: 0.55rem;
        color: #A7F3D0;
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
    
    /* Desktop: Sidebar always visible */
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
    
    /* Tablet and below: Sidebar hidden by default */
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
<!-- SIDEBAR - CASHIER (GREEN THEME) -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- ================================================================ -->
    <!-- BRAND / HEADER -->
    <!-- ================================================================ -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%23065F46%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Cashier Panel</p>
            </div>
            <!-- Close button for mobile -->
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav">
        
        <!-- ============================================================ -->
        <!-- CASHIER MENU -->
        <!-- ============================================================ -->
        <div class="nav-label">Cashier</div>
        
        <!-- 1. Dashboard -->
        <a href="../cashier/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ============================================================ -->
        <!-- BILLING -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Billing</div>
        
        <!-- Pending Bills -->
        <a href="../cashier/pending_bills.php" class="sidebar-link <?= isActive('pending_bills.php') ?>">
            <i class="fas fa-clock"></i> Pending Bills
            <?php if ($pending_bills > 0): ?>
                <span class="badge orange" id="sidebarPendingBadge"><?= $pending_bills ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPendingBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Paid Bills -->
        <a href="../cashier/paid_bills.php" class="sidebar-link <?= isActive('paid_bills.php') ?>">
            <i class="fas fa-check-circle"></i> Paid Bills
            <span class="badge green" id="sidebarPaidBadge"><?= $total_paid ?></span>
        </a>
        
        <!-- Partial Payments -->
        <a href="../cashier/partial_payments.php" class="sidebar-link <?= isActive('partial_payments.php') ?>">
            <i class="fas fa-hand-holding-usd"></i> Partial Payments
            <?php if ($partial_payments > 0): ?>
                <span class="badge blue" id="sidebarPartialBadge"><?= $partial_payments ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPartialBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Cancelled Bills -->
        <a href="../cashier/cancelled_bills.php" class="sidebar-link <?= isActive('cancelled_bills.php') ?>">
            <i class="fas fa-times-circle"></i> Cancelled Bills
        </a>
        
        <!-- ============================================================ -->
        <!-- PAYMENTS -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Payments</div>
        
        <!-- Payment History -->
        <a href="../cashier/payment_history.php" class="sidebar-link <?= isActive('payment_history.php') ?>">
            <i class="fas fa-history"></i> Payment History
        </a>
        
        <!-- ============================================================ -->
        <!-- RECEIPTS -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Receipts</div>
        
        <!-- Receipt History -->
        <a href="../cashier/receipt_history.php" class="sidebar-link <?= isActive('receipt_history.php') ?>">
            <i class="fas fa-receipt"></i> Receipt History
        </a>
        
        <!-- ============================================================ -->
        <!-- ACCOUNT -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Account</div>
        
        <a href="../cashier/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="../../../logout.php" class="sidebar-link logout-link">
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
        // Wait for DOM to be ready
        function initSidebar() {
            console.log('🔧 Initializing Cashier Sidebar...');
            
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            // Create overlay if not exists
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
            
            // Toggle function
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
            
            // ================================================================
            // EVENT: Toggle button (hamburger icon from header)
            // ================================================================
            if (toggleBtn) {
                // Remove all existing listeners to avoid duplicates
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
                console.warn('⚠️ Toggle button not found - trying fallback');
                // Try to find by class
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
            
            // ================================================================
            // EVENT: Close button (X icon in sidebar)
            // ================================================================
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
                console.log('✅ Close button attached');
            }
            
            // ================================================================
            // EVENT: Close sidebar when clicking overlay
            // ================================================================
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
                console.log('✅ Overlay click handler attached');
            }
            
            // ================================================================
            // EVENT: Close sidebar with ESC key
            // ================================================================
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // ================================================================
            // EVENT: Auto-close on window resize (desktop)
            // ================================================================
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            console.log('✅ Cashier Sidebar fully initialized!');
            console.log('📱 Sidebar element:', sidebar);
            console.log('🔘 Toggle button:', document.getElementById('sidebarToggle'));
            console.log('❌ Close button:', document.getElementById('sidebarCloseBtn'));
            console.log('📐 Window width:', window.innerWidth);
            console.log('📱 Is mobile:', window.innerWidth <= 1024);
        }
        
        // Run on DOM ready
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
        // Pending Bills
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge) {
            var pending = data.pending_bills || 0;
            pendingBadge.textContent = pending;
            pendingBadge.className = pending > 0 ? 'badge orange' : 'badge';
            pendingBadge.classList.remove('badge-update');
            void pendingBadge.offsetWidth;
            pendingBadge.classList.add('badge-update');
        }
        
        // Total Paid Bills (All time)
        var paidBadge = document.getElementById('sidebarPaidBadge');
        if (paidBadge) {
            var totalPaid = data.total_paid || 0;
            paidBadge.textContent = totalPaid;
            paidBadge.className = totalPaid > 0 ? 'badge green' : 'badge';
            paidBadge.classList.remove('badge-update');
            void paidBadge.offsetWidth;
            paidBadge.classList.add('badge-update');
        }
        
        // Partial Payments
        var partialBadge = document.getElementById('sidebarPartialBadge');
        if (partialBadge) {
            var partial = data.partial_payments || 0;
            partialBadge.textContent = partial;
            partialBadge.className = partial > 0 ? 'badge blue' : 'badge';
            partialBadge.classList.remove('badge-update');
            void partialBadge.offsetWidth;
            partialBadge.classList.add('badge-update');
        }
        
        // Update time
        var timeEl = document.getElementById('sidebarLiveTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            });
            timeEl.textContent = timeStr;
        }
    }

    // ================================================================
    // FETCH SIDEBAR DATA (Self-contained - uses same file)
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;
    var branchId = <?= json_encode($_SESSION['branch_id'] ?? 1) ?>;
    var lastDataHash = null;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_cashier_sidebar_data');
        formData.append('branch_id', branchId);
        
        // Send request to the SAME FILE (self-contained)
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
                // Only update if data has changed
                if (lastDataHash !== data.hash) {
                    lastDataHash = data.hash;
                    updateSidebarBadges(data);
                }
            }
            sidebarIsUpdating = false;
        })
        .catch(function(error) {
            // Silent fail - don't spam console
            sidebarIsUpdating = false;
        });
    }

    // ================================================================
    // START / STOP AUTO-UPDATE
    // ================================================================
    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // Initial update after 1 second
        setTimeout(function() {
            fetchSidebarData();
        }, 1000);
        // Then every 3 seconds
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
        console.log('%c🔄 Cashier Sidebar auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
            console.log('%c⏹️ Cashier Sidebar auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    // ================================================================
    // VISIBILITY CHANGE
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    // ================================================================
    // EXPOSE FUNCTIONS FOR OTHER SCRIPTS
    // ================================================================
    window.updateSidebarBadges = updateSidebarBadges;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    console.log('%c💰 Cashier Sidebar (FULLY FIXED - Works with Header)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c📋 Pending: <?= $pending_bills ?> | Partial: <?= $partial_payments ?> | Total Paid: <?= $total_paid ?>', 'font-size:12px; color:#A7F3D0;');
    console.log('%c🔄 Data fetched from the SAME file via AJAX POST', 'font-size:12px; color:#34D399;');
    console.log('%c✅ NO EXTERNAL API NEEDED - Self-contained', 'font-size:12px; color:#059669;');
    console.log('%c📱 Click ☰ in header to open sidebar on mobile', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Sidebar toggle works on all devices!', 'font-size:12px; color:#059669;');
</script>