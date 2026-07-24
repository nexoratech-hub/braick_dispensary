<?php
// ================================================================
// FILE: frontend/components/pharmacy_sidebar.php
// PHARMACY - SHARED SIDEBAR (FIXED - WITH AUTO-UPDATE)
// AUTO-UPDATE EVERY 3 SECONDS - SELF-CONTAINED
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
// ================================================================
$pending_prescriptions = 0;
$low_stock_count = 0;
$today_sales = 0;
$today_otc = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // Pending Prescriptions
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Low Stock Medicines
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
        ");
        $stmt->execute([$user_branch_id]);
        $low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Today's Prescription Sales
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescription_sales 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $today_sales = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Today's OTC Sales
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM otc_sales 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $today_otc = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    } catch (Exception $e) {
        // Keep counts as 0
        error_log("Pharmacy sidebar stats error: " . $e->getMessage());
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_pharmacy_sidebar_data') {
    header('Content-Type: application/json');
    
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    
    $response = [
        'success' => false,
        'pending_prescriptions' => 0,
        'low_stock' => 0,
        'today_prescriptions' => 0,
        'today_otc' => 0
    ];
    
    if (isset($db) && $db !== null) {
        try {
            // Pending Prescriptions
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
            $stmt->execute([$branch_id]);
            $response['pending_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Low Stock Medicines
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medications_inventory 
                WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
            ");
            $stmt->execute([$branch_id]);
            $response['low_stock'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Today's Prescription Sales
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM prescription_sales 
                WHERE branch_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$branch_id]);
            $response['today_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Today's OTC Sales
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM otc_sales 
                WHERE branch_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$branch_id]);
            $response['today_otc'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $response['success'] = true;
            
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
       SIDEBAR STYLES - BLUE THEME
       ================================================================ */
    .sidebar {
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0;
        width: 270px; 
        background: #0B4EA8;
        color: white;
        z-index: 50; 
        overflow-y: auto;
        transition: transform 0.3s ease;
    }
    
    [data-theme="dark"] .sidebar {
        background: #0A3D7A;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0A3D7A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    
    .sidebar-brand {
        padding: 22px 20px 16px;
        border-bottom: 2px solid #0A3D7A;
    }
    
    .sidebar-brand .logo {
        width: 48px; 
        height: 48px; 
        border-radius: 12px;
        object-fit: cover; 
        background: white; 
        padding: 4px;
    }
    
    .sidebar-brand .brand-text { 
        color: white; 
        font-weight: 700; 
        font-size: 1rem; 
    }
    
    .sidebar-brand .brand-sub { 
        color: #9EC5FE; 
        font-size: 0.7rem; 
    }
    
    .sidebar-nav { 
        padding: 14px 10px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.55rem; 
        text-transform: uppercase;
        letter-spacing: 0.1em; 
        color: #9EC5FE;
        padding: 0 12px; 
        margin: 12px 0 6px; 
        font-weight: 700;
    }
    
    .sidebar-link {
        display: flex; 
        align-items: center; 
        gap: 12px;
        padding: 9px 14px; 
        border-radius: 10px;
        color: #D2E3FC; 
        text-decoration: none;
        transition: all 0.3s ease; 
        font-size: 0.85rem; 
        font-weight: 500;
        margin: 2px 0;
        background: transparent;
        cursor: pointer;
    }
    
    .sidebar-link:hover {
        background: #0AA84F;
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #0AA84F;
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4);
    }
    
    .sidebar-link i { 
        width: 20px; 
        text-align: center; 
        font-size: 1rem; 
    }
    
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        padding: 1px 9px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        min-width: 18px;
        text-align: center;
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
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
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.1);
        padding-top: 12px;
        margin-top: 8px;
        color: #FCA5A5;
    }
    
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    .sidebar-status {
        padding: 12px 20px;
        border-top: 2px solid #0A3D7A;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-status .status-dot {
        width: 10px;
        height: 10px;
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
        font-size: 0.75rem;
        color: #D2E3FC;
    }
    
    .sidebar-status .status-time {
        font-size: 0.6rem;
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
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    
    .mt-2 { margin-top: 8px; }
    
    @media (max-width: 1024px) {
        .sidebar { 
            transform: translateX(-100%); 
        }
        .sidebar.open { 
            transform: translateX(0); 
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR - PHARMACY -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Pharmacy Panel</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ===== PHARMACY MENU ===== -->
        <div class="nav-label">Pharmacy</div>
        
        <!-- 1. Dashboard -->
        <a href="../pharmacy/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ===== PRESCRIPTION SALES ===== -->
        <div class="nav-label mt-2">Prescription Sales</div>
        
        <!-- Pending Prescriptions -->
        <a href="../pharmacy/pending_prescriptions.php" class="sidebar-link <?= isActive('pending_prescriptions.php') ?>">
            <i class="fas fa-clock"></i> Pending Prescriptions
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge danger" id="sidebarPendingBadge"><?= $pending_prescriptions ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPendingBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Dispensing -->
        <a href="../pharmacy/dispensing.php" class="sidebar-link <?= isActive('dispensing.php') ?>">
            <i class="fas fa-prescription"></i> Dispensing
        </a>
        
        <!-- Prescription History -->
        <a href="../pharmacy/prescription_history.php" class="sidebar-link <?= isActive('prescription_history.php') ?>">
            <i class="fas fa-history"></i> Prescription History
            <span class="badge" id="sidebarTodayPrescriptions"><?= $today_sales ?></span>
        </a>
        
        <!-- ===== OTC SALES ===== -->
        <div class="nav-label mt-2">OTC Sales</div>
        
        <!-- New OTC Sale -->
        <a href="../pharmacy/new_otc_sale.php" class="sidebar-link <?= isActive('new_otc_sale.php') ?>">
            <i class="fas fa-plus-circle"></i> New OTC Sale
        </a>
        
        <!-- OTC History -->
        <a href="../pharmacy/otc_history.php" class="sidebar-link <?= isActive('otc_history.php') ?>">
            <i class="fas fa-shopping-cart"></i> OTC History
            <span class="badge" id="sidebarTodayOtc"><?= $today_otc ?></span>
        </a>
        
        <!-- ===== MEDICINES ===== -->
        <div class="nav-label mt-2">Medicines</div>
        
        <!-- Inventory -->
        <a href="../pharmacy/inventory.php" class="sidebar-link <?= isActive('inventory.php') ?>">
            <i class="fas fa-warehouse"></i> Inventory
        </a>
        
        <!-- Low Stock -->
        <a href="../pharmacy/low_stock.php" class="sidebar-link <?= isActive('low_stock.php') ?>">
            <i class="fas fa-exclamation-triangle"></i> Low Stock
            <?php if ($low_stock_count > 0): ?>
                <span class="badge danger" id="sidebarLowStockBadge"><?= $low_stock_count ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarLowStockBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ===== REPORTS ===== -->
        <a href="../pharmacy/reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- Profile -->
        <a href="../pharmacy/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- Logout -->
        <a href="../../../logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- Online Status with Live Update Indicator -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
        <span class="status-time" id="sidebarStatusTime">
            <span class="live-dot"></span>
            <span id="sidebarLiveTime"><?= date('H:i:s') ?></span>
        </span>
    </div>
</aside>

<script>
    // ================================================================
    // SIDEBAR TOGGLE (Mobile)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (sidebar && sidebarToggle) {
                    if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                        sidebar.classList.remove('open');
                    }
                }
            }
        });
    });

    // ================================================================
    // UPDATE SIDEBAR BADGES (AJAX every 3 seconds)
    // ================================================================
    function updateSidebarBadges(pending, lowStock, todayPrescriptions, todayOtc) {
        // Update Pending Prescriptions Badge
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge) {
            pendingBadge.textContent = pending;
            pendingBadge.className = pending > 0 ? 'badge danger' : 'badge';
        }
        
        // Update Low Stock Badge
        var lowStockBadge = document.getElementById('sidebarLowStockBadge');
        if (lowStockBadge) {
            lowStockBadge.textContent = lowStock;
            lowStockBadge.className = lowStock > 0 ? 'badge danger' : 'badge';
        }
        
        // Update Today Prescriptions Badge
        var todayPrescriptionsBadge = document.getElementById('sidebarTodayPrescriptions');
        if (todayPrescriptionsBadge) {
            todayPrescriptionsBadge.textContent = todayPrescriptions;
            todayPrescriptionsBadge.className = todayPrescriptions > 0 ? 'badge green' : 'badge';
        }
        
        // Update Today OTC Badge
        var todayOtcBadge = document.getElementById('sidebarTodayOtc');
        if (todayOtcBadge) {
            todayOtcBadge.textContent = todayOtc;
            todayOtcBadge.className = todayOtc > 0 ? 'badge green' : 'badge';
        }
        
        // Update status time
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
    // AJAX AUTO-UPDATE (Self-contained - uses same file)
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;
    var branchId = <?= json_encode($_SESSION['branch_id'] ?? 1) ?>;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_pharmacy_sidebar_data');
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
                updateSidebarBadges(
                    data.pending_prescriptions || 0,
                    data.low_stock || 0,
                    data.today_prescriptions || 0,
                    data.today_otc || 0
                );
            }
            sidebarIsUpdating = false;
        })
        .catch(function(error) {
            // Silent fail - don't spam console
            // console.warn('Sidebar update error:', error.message);
            sidebarIsUpdating = false;
        });
    }

    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // Initial update
        fetchSidebarData();
        // Then every 3 seconds
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
        console.log('%c🔄 Pharmacy Sidebar auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
            console.log('%c⏹️ Pharmacy Sidebar auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    // ================================================================
    // HANDLE PAGE VISIBILITY - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    // ================================================================
    // EXPOSE FUNCTIONS FOR OTHER SCRIPTS
    // ================================================================
    window.updateSidebarBadges = updateSidebarBadges;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;

    console.log('%c💊 Pharmacy Sidebar (SELF-CONTAINED - Auto-update every 3s)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Pending: <?= $pending_prescriptions ?> | Low Stock: <?= $low_stock_count ?> | Today Rx: <?= $today_sales ?> | Today OTC: <?= $today_otc ?>', 'font-size:12px; color:#9EC5FE;');
    console.log('%c🔄 Data fetched from the SAME file via AJAX POST', 'font-size:12px; color:#34D399;');
    console.log('%c✅ NO EXTERNAL API NEEDED - Self-contained', 'font-size:12px; color:#059669;');
</script>